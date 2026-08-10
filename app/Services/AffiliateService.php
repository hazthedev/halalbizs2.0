<?php

namespace App\Services;

use App\Enums\AffiliatePayoutStatus;
use App\Enums\AffiliateReferralStatus;
use App\Enums\AffiliateStatus;
use App\Exceptions\CheckoutException;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\SubOrder;
use App\Models\User;
use App\Settings\OrderSettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Affiliate / creator program (M2.5). Enrolment, last-click attribution from the
 * request cookie, and commission booking when a referred sub-order completes.
 * Commission is integer sen, recorded post-commit (never inside checkout).
 */
class AffiliateService
{
    public function enabled(): bool
    {
        return (bool) config('affiliate.enabled', true);
    }

    /** Enrol a creator (idempotent) — mints a share code on first call. */
    public function enroll(User $user): Affiliate
    {
        return Affiliate::firstOrCreate(
            ['user_id' => $user->id],
            [
                'code' => $this->uniqueCode(),
                'status' => AffiliateStatus::Active,
                'commission_rate_bp' => (int) config('affiliate.commission_rate_bp', 500),
            ],
        );
    }

    /** The active affiliate referenced by the request's attribution cookie, if any. */
    public function fromRequestCookie(): ?Affiliate
    {
        $code = request()?->cookie((string) config('affiliate.cookie', 'aff_ref'));

        if (! is_string($code) || $code === '') {
            return null;
        }

        return Affiliate::where('code', $code)->where('status', AffiliateStatus::Active)->first();
    }

    /**
     * Book commission for a completed referred sub-order. Idempotent per
     * sub-order; skips self-referrals and inactive affiliates.
     */
    public function recordCommission(SubOrder $subOrder): ?AffiliateReferral
    {
        if (! $this->enabled()) {
            return null;
        }

        $order = $subOrder->order;
        $affiliateId = $order?->affiliate_id;

        if ($affiliateId === null) {
            return null;
        }

        return DB::transaction(function () use ($subOrder, $order, $affiliateId) {
            if (AffiliateReferral::where('sub_order_id', $subOrder->id)->exists()) {
                return null; // already booked
            }

            $affiliate = Affiliate::whereKey($affiliateId)->lockForUpdate()->first();

            if ($affiliate === null || ! $affiliate->isActive() || $affiliate->user_id === $order->user_id) {
                return null; // gone, suspended, or self-referral
            }

            $itemsSubtotalSen = (int) $subOrder->items_subtotal_sen;
            $commissionSen = intdiv($itemsSubtotalSen * $affiliate->commission_rate_bp + 5000, 10000);

            return AffiliateReferral::create([
                'affiliate_id' => $affiliate->id,
                'sub_order_id' => $subOrder->id,
                'buyer_id' => $order->user_id,
                'items_subtotal_sen' => $itemsSubtotalSen,
                'commission_sen' => $commissionSen,
                'reversed_sen' => 0,
                'status' => AffiliateReferralStatus::Pending,
                'locks_at' => $this->locksAtFor($subOrder),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * When this commission stops being pending.
     *
     * Anchored on DELIVERY, not on the order or completion: sellers fulfil
     * independently here, so an order-date hold would free commission on
     * something that never shipped, and completion can be triggered manually by
     * a seller the same day it arrives. `delivered_at` is the first moment the
     * return window can honestly be said to have started.
     *
     * Falls back to now() when a sub-order reached completion without ever being
     * marked delivered (COD flows can). That is the conservative direction: the
     * hold starts later, never earlier.
     */
    private function locksAtFor(SubOrder $subOrder): \Illuminate\Support\Carbon
    {
        $from = $subOrder->delivered_at ?? now();

        return $from
            ->copy()
            ->addDays((int) app(OrderSettings::class)->return_window_days)
            ->addDays((int) config('affiliate.lock_buffer_days', 7));
    }

    /**
     * Promote every held commission whose window has passed. Idempotent, so the
     * hourly schedule can run it forever; returns how many moved.
     */
    public function lockDueCommissions(): int
    {
        return AffiliateReferral::query()
            ->where('status', AffiliateReferralStatus::Pending)
            ->whereNotNull('locks_at')
            ->where('locks_at', '<=', now())
            ->update(['status' => AffiliateReferralStatus::Confirmed]);
    }

    /**
     * A refund landed on a referred sub-order: void the matching slice of the
     * commission, pro-rata.
     *
     * Scaled against `total_sen` (not items_subtotal_sen) to match the fraction
     * of the SALE being reversed, and mirroring the seller-ledger reversal in
     * RefundService step 1 — using the items subtotal as the denominator would
     * over-cut, because a shipping-only refund would eat item commission.
     *
     * Applies whether the commission is still PENDING or already locked.
     *
     * Pending is the quiet case the hold exists to create: the creator was told
     * the money was not theirs yet, so a reduction is a non-event. Locked is the
     * exception — the money was presented as available and may already have been
     * withdrawn, so the reduction can push the balance NEGATIVE. That is the
     * intended behaviour: the shortfall carries forward and is absorbed by later
     * commissions (see availableForPayoutSen). Nobody is ever invoiced.
     */
    public function reduceForRefund(SubOrder $subOrder, int $refundedSen): int
    {
        if (! $this->enabled() || $refundedSen <= 0) {
            return 0;
        }

        $referral = AffiliateReferral::where('sub_order_id', $subOrder->id)
            ->whereIn('status', [AffiliateReferralStatus::Pending, AffiliateReferralStatus::Confirmed])
            ->lockForUpdate()
            ->first();

        if ($referral === null) {
            return 0;
        }

        $totalSen = (int) $subOrder->total_sen;

        if ($totalSen <= 0) {
            return 0;
        }

        $commissionSen = (int) $referral->commission_sen;

        // Same half-up integer form as the seller reversal, so a refund split
        // into parts sums back to the whole and never over-reverses.
        $slice = intdiv($commissionSen * min($refundedSen, $totalSen) + intdiv($totalSen, 2), $totalSen);
        $slice = min($slice, $commissionSen - (int) $referral->reversed_sen);

        if ($slice <= 0) {
            return 0;
        }

        $referral->increment('reversed_sen', $slice);

        // Fully clawed back: mark it, so the creator's list shows a reversed row
        // rather than a silent RM0.00 one. Applies to a locked commission too —
        // it leaves the payable set, which is what drives the balance negative
        // if it had already been withdrawn.
        if ($referral->fresh()->payableSen() === 0) {
            $referral->update(['status' => AffiliateReferralStatus::Reversed]);
        }

        return $slice;
    }

    /** Payable now: locked, less anything a refund voided. */
    public function confirmedEarningsSen(Affiliate $affiliate): int
    {
        return (int) $affiliate->referrals()
            ->whereIn('status', AffiliateReferralStatus::payable())
            ->sum(DB::raw('commission_sen - reversed_sen'));
    }

    /** Booked but still inside its hold — shown separately, never withdrawable. */
    public function pendingEarningsSen(Affiliate $affiliate): int
    {
        return (int) $affiliate->referrals()
            ->where('status', AffiliateReferralStatus::Pending)
            ->sum(DB::raw('commission_sen - reversed_sen'));
    }

    /** The soonest a pending commission becomes available, for the dashboard. */
    public function nextUnlockAt(Affiliate $affiliate): ?\Illuminate\Support\Carbon
    {
        $at = $affiliate->referrals()
            ->where('status', AffiliateReferralStatus::Pending)
            ->whereNotNull('locks_at')
            ->min('locks_at');

        return $at === null ? null : \Illuminate\Support\Carbon::parse($at);
    }

    /**
     * Locked earnings minus what's already requested/paid out. CAN BE NEGATIVE,
     * and that is the whole carry-forward mechanism.
     *
     * It used to be clamped with max(0, …). That clamp silently forgave any
     * shortfall: withdraw RM5, have the sale refunded afterwards, and the debt
     * evaporated at the clamp. Letting it go negative makes the balance carry —
     * later commissions absorb it, and a withdrawal is only possible once the
     * balance is positive again, because requestPayout() caps on this figure.
     *
     * That is "net against future earnings, never invoice": the creator is never
     * asked for money, they simply earn back to zero first. A creator who is
     * owed RM8 and carries a RM5 shortfall can withdraw RM3.
     *
     * ponytail: the "ledger" here is derived, not a table — referral rows carry
     * commission_sen/reversed_sen and payouts are rows, so the history is fully
     * reconstructable. Build a real affiliate_ledger only if manual adjustments,
     * write-offs or multi-currency arrive; today a table would duplicate what
     * these two already record.
     */
    public function availableForPayoutSen(Affiliate $affiliate): int
    {
        $earmarked = (int) $affiliate->payouts()
            ->whereIn('status', [AffiliatePayoutStatus::Requested, AffiliatePayoutStatus::Paid])
            ->sum('amount_sen');

        return $this->confirmedEarningsSen($affiliate) - $earmarked;
    }

    /**
     * Creator requests a withdrawal: ≥ the min threshold, ≤ available, one open
     * request at a time. Bank details are snapshotted for the admin to pay.
     *
     * @param  array<string, mixed>  $bankDetails
     *
     * @throws CheckoutException with a creator-facing reason
     */
    public function requestPayout(Affiliate $affiliate, int $amountSen, array $bankDetails = []): AffiliatePayout
    {
        return DB::transaction(function () use ($affiliate, $amountSen, $bankDetails) {
            // Serialize on the affiliate row so available can't be double-spent.
            Affiliate::whereKey($affiliate->id)->lockForUpdate()->first();

            if ($affiliate->payouts()->where('status', AffiliatePayoutStatus::Requested)->exists()) {
                throw new CheckoutException(__('You already have a withdrawal in progress.'));
            }

            $minSen = (int) config('affiliate.min_payout_sen', 5000);

            if ($amountSen < $minSen) {
                throw new CheckoutException(__('Minimum withdrawal is :min.', ['min' => Money::format($minSen)]));
            }

            if ($amountSen > $this->availableForPayoutSen($affiliate)) {
                throw new CheckoutException(__('Only :amount is available to withdraw.', ['amount' => Money::format($this->availableForPayoutSen($affiliate))]));
            }

            return $affiliate->payouts()->create([
                'amount_sen' => $amountSen,
                'status' => AffiliatePayoutStatus::Requested,
                'bank_snapshot' => $bankDetails,
                'requested_at' => now(),
            ]);
        });
    }

    public function markPayoutPaid(AffiliatePayout $payout, ?string $reference = null): void
    {
        $payout->update([
            'status' => AffiliatePayoutStatus::Paid,
            'reference' => $reference,
            'processed_at' => now(),
        ]);
    }

    /** Rejecting releases the earmark (the amount returns to available). */
    public function rejectPayout(AffiliatePayout $payout, ?string $reason = null): void
    {
        $payout->update([
            'status' => AffiliatePayoutStatus::Rejected,
            'reference' => $reason,
            'processed_at' => now(),
        ]);
    }

    public function referralLink(Affiliate $affiliate, ?string $to = null): string
    {
        return route('affiliate.refer', array_filter([
            'code' => $affiliate->code,
            'to' => $to,
        ]));
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Affiliate::where('code', $code)->exists());

        return $code;
    }
}
