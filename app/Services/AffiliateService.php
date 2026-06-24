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
                'status' => AffiliateReferralStatus::Confirmed,
                'created_at' => now(),
            ]);
        });
    }

    public function confirmedEarningsSen(Affiliate $affiliate): int
    {
        return (int) $affiliate->referrals()
            ->where('status', AffiliateReferralStatus::Confirmed)
            ->sum('commission_sen');
    }

    /** Confirmed earnings minus what's already requested/paid out. */
    public function availableForPayoutSen(Affiliate $affiliate): int
    {
        $earmarked = (int) $affiliate->payouts()
            ->whereIn('status', [AffiliatePayoutStatus::Requested, AffiliatePayoutStatus::Paid])
            ->sum('amount_sen');

        return max(0, $this->confirmedEarningsSen($affiliate) - $earmarked);
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
