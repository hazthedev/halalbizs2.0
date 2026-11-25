<?php

namespace App\Services;

use App\Enums\CoinTransactionType;
use App\Exceptions\CheckoutException;
use App\Models\CoinTransaction;
use App\Models\CoinWallet;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\CoinsExpiringNotification;
use App\Support\CoinRedemptionResult;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty Coins core (M2.1), modelled on LedgerService's locked-row discipline.
 * Every balance mutation happens under a wallet lockForUpdate together with the
 * coin_transactions row that justifies it, so the cached `balance` never drifts
 * from SUM(remaining) of the live earn lots. Redemption value is integer sen
 * (Hard Rule 1). Earn/refund are idempotent on their reference.
 */
class CoinService
{
    public function enabled(): bool
    {
        return (bool) config('coins.enabled', true);
    }

    public function walletFor(User $user): CoinWallet
    {
        return CoinWallet::firstOrCreate(['user_id' => $user->id]);
    }

    public function balance(User $user): int
    {
        return (int) (CoinWallet::where('user_id', $user->id)->value('balance') ?? 0);
    }

    /**
     * Credit coins (earn / check-in / spin / refund / positive adjustment).
     * Idempotent when a $reference is given — a second call for the same
     * (type, reference) is a no-op. Opens an expiring FIFO lot.
     */
    public function credit(User $user, int $coins, CoinTransactionType $type, ?Model $reference = null, ?string $description = null, ?int $expiryDays = null): ?CoinTransaction
    {
        if (! $this->enabled() || $coins <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $coins, $type, $reference, $description, $expiryDays) {
            $wallet = $this->lockWallet($this->walletFor($user)->id);

            if ($reference !== null && $this->hasReferencedEntry($wallet, $type, $reference)) {
                return null; // already credited for this reference
            }

            return $this->creditLocked($wallet, $coins, $type, $reference, $description, $expiryDays);
        });
    }

    /**
     * Redeem coins at checkout. MUST be called inside the checkout transaction
     * (it takes the wallet lock as the LAST link in the canonical lock order:
     * variants → flash-sale → vouchers → wallet). Caps redemption to the wallet
     * balance, the per-order config ceiling, and leaves ≥ 1 sen payable.
     */
    public function redeemForCheckout(User $user, int $requestedCoins, int $maxSpendableSen): CoinRedemptionResult
    {
        if (! $this->enabled() || $requestedCoins <= 0 || $maxSpendableSen <= 0) {
            return CoinRedemptionResult::none();
        }

        $wallet = CoinWallet::where('user_id', $user->id)->lockForUpdate()->first();

        $coins = $wallet === null ? 0 : $this->cappedCoins($requestedCoins, (int) $wallet->balance, $maxSpendableSen);

        if ($wallet === null || $coins <= 0) {
            return CoinRedemptionResult::none();
        }

        $sen = $coins * $this->redemptionRateSen();
        $txn = $this->consumeLocked($wallet, $coins, CoinTransactionType::Redeem, null, null, $sen);

        return new CoinRedemptionResult($coins, $sen, $txn);
    }

    /**
     * What redeemForCheckout() WOULD consume for this bill — same cap, no lock,
     * no debit. The checkout quote shows this so the preview and the charge
     * cannot drift apart.
     */
    public function previewRedemption(User $user, int $requestedCoins, int $maxSpendableSen): CoinRedemptionResult
    {
        if (! $this->enabled() || $requestedCoins <= 0 || $maxSpendableSen <= 0) {
            return CoinRedemptionResult::none();
        }

        $coins = $this->cappedCoins($requestedCoins, $this->balance($user), $maxSpendableSen);

        return $coins <= 0
            ? CoinRedemptionResult::none()
            : new CoinRedemptionResult($coins, $coins * $this->redemptionRateSen(), null);
    }

    /** Coins actually spendable: wallet balance, per-order ceiling, and ≥ 1 sen left payable. */
    private function cappedCoins(int $requestedCoins, int $balance, int $maxSpendableSen): int
    {
        if ($balance <= 0) {
            return 0;
        }

        $maxSen = min((int) config('coins.max_redemption_sen', 5000), max(0, $maxSpendableSen - 1));

        return min($requestedCoins, $balance, intdiv($maxSen, $this->redemptionRateSen()));
    }

    private function redemptionRateSen(): int
    {
        return max(1, (int) config('coins.redemption_rate_sen', 1));
    }

    /**
     * Return coins redeemed on an order that was cancelled before payment.
     * Idempotent: refunds once per order, re-crediting a fresh expiring lot.
     */
    public function refundForOrder(Order $order): void
    {
        if (! $this->enabled()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $redeem = CoinTransaction::where('type', CoinTransactionType::Redeem)
                ->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->id)
                ->first();

            if ($redeem === null) {
                return; // nothing was redeemed
            }

            $wallet = $this->lockWallet($redeem->coin_wallet_id);

            $alreadyRefunded = $wallet->transactions()
                ->where('type', CoinTransactionType::Refund)
                ->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->id)
                ->exists();

            if ($alreadyRefunded) {
                return;
            }

            $this->creditLocked(
                $wallet,
                (int) abs($redeem->amount),
                CoinTransactionType::Refund,
                $order,
                __('Coins refunded for cancelled order :no', ['no' => $order->order_no]),
            );
        });
    }

    /**
     * Return a proportional share of the coins a buyer redeemed when a PAID
     * order is (partly) refunded (M2.1 money integrity). Bounded so cumulative
     * reversals across partial refunds never exceed what was originally
     * redeemed; a full refund returns everything. Re-credits a fresh expiring
     * lot. Safe to call inside RefundService's transaction.
     */
    public function reverseForRefund(Order $order, int $refundAmountSen, int $payableBasisSen): int
    {
        if (! $this->enabled() || $refundAmountSen <= 0 || $payableBasisSen <= 0 || (int) $order->coin_redemption_sen <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($order, $refundAmountSen, $payableBasisSen) {
            $redeem = CoinTransaction::where('type', CoinTransactionType::Redeem)
                ->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->id)
                ->first();

            if ($redeem === null) {
                return 0;
            }

            $redeemedCoins = (int) abs($redeem->amount);
            $wallet = $this->lockWallet($redeem->coin_wallet_id);

            // Coins already returned for this order (cap against over-reversal).
            $alreadyReversed = (int) $wallet->transactions()
                ->where('type', CoinTransactionType::Refund)
                ->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->id)
                ->sum('amount');

            $remaining = $redeemedCoins - $alreadyReversed;

            if ($remaining <= 0) {
                return 0;
            }

            $proportional = intdiv($redeemedCoins * min($refundAmountSen, $payableBasisSen) + intdiv($payableBasisSen, 2), $payableBasisSen);
            $coins = min($remaining, $proportional);

            if ($coins <= 0) {
                return 0;
            }

            $this->creditLocked($wallet, $coins, CoinTransactionType::Refund, $order,
                __('Coins returned for refund on order :no', ['no' => $order->order_no]));

            return $coins;
        });
    }

    /**
     * HB-G129: coins a completed sub-order earns once $refundedSen of its total
     * is refunded: the refunded share of the items subtotal comes off first
     * (rounded, the way RefundService scales commission), then the per-RM rate.
     */
    public function earnedCoinsFor(SubOrder $subOrder, int $refundedSen): int
    {
        $itemsSen = (int) $subOrder->items_subtotal_sen;
        $totalSen = (int) $subOrder->total_sen;
        $refundedSen = max(0, min($refundedSen, $totalSen));

        $refundedItemsSen = $totalSen > 0 ? intdiv($itemsSen * $refundedSen + intdiv($totalSen, 2), $totalSen) : 0;

        return intdiv(max(0, $itemsSen - $refundedItemsSen), 100) * (int) config('coins.earn_coins_per_rm', 1);
    }

    /**
     * HB-G004: take back the coins EARNED on a sub-order when it is refunded, pro
     * rata to the refund, bounded so cumulative take-backs never exceed what was
     * earned and never drive the wallet below zero (coins already spent stay
     * spent, same rule as an admin clawback). Written as an Adjustment that
     * references the sub-order, which is what makes the cap countable. Safe to
     * call inside RefundService's transaction (wallet lock comes last).
     *
     * HB-G129: the take-back is the gap between what was earned and what the
     * sub-order still earns after this refund (earnedCoinsFor), not a share of
     * the Earn row. An Earn booked after an earlier refund is already net of it,
     * so a share of that row under-takes. $subOrder must be the locked row with
     * refunded_sen NOT yet including $refundAmountSen (RefundService order).
     */
    public function clawBackEarnedForRefund(SubOrder $subOrder, int $refundAmountSen): int
    {
        if (! $this->enabled() || $refundAmountSen <= 0 || (int) $subOrder->total_sen <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($subOrder, $refundAmountSen) {
            $earn = CoinTransaction::where('type', CoinTransactionType::Earn)
                ->where('reference_type', $subOrder->getMorphClass())
                ->where('reference_id', $subOrder->id)
                ->first();

            if ($earn === null) {
                return 0; // refunded before completion: nothing was earned
            }

            $earned = (int) $earn->amount;
            $wallet = $this->lockWallet($earn->coin_wallet_id);

            $alreadyTaken = (int) -$wallet->transactions()
                ->where('type', CoinTransactionType::Adjustment)
                ->where('reference_type', $subOrder->getMorphClass())
                ->where('reference_id', $subOrder->id)
                ->sum('amount');

            // ponytail: reads today's earn rate; a rate change between earn and
            //   refund shifts the take-back. Store the rate on the Earn row if that matters.
            $stillEarned = $this->earnedCoinsFor($subOrder, (int) $subOrder->refunded_sen + $refundAmountSen);
            $coins = min($earned - $alreadyTaken - $stillEarned, (int) $wallet->balance);

            if ($coins <= 0) {
                return 0;
            }

            $this->consumeLocked($wallet, $coins, CoinTransactionType::Adjustment, $subOrder,
                __('Coins earned on order :no taken back after a refund', ['no' => $subOrder->sub_order_no]), null);

            return $coins;
        });
    }

    /**
     * Daily check-in: awards streak-based coins once per calendar day.
     *
     * @return array{coins: int, streak: int, day: int}
     *
     * @throws CheckoutException when already checked in today
     */
    public function checkIn(User $user): array
    {
        if (! $this->enabled()) {
            throw new CheckoutException(__('Daily check-in is unavailable right now.'));
        }

        return DB::transaction(function () use ($user) {
            $wallet = $this->lockWallet($this->walletFor($user)->id);
            $today = DisplayTime::today()->toDateString();

            if ($wallet->last_checkin_on?->toDateString() === $today) {
                throw new CheckoutException(__('You have already checked in today.'));
            }

            $continuing = $wallet->last_checkin_on?->toDateString() === DisplayTime::today()->subDay()->toDateString();
            $streak = $continuing ? $wallet->checkin_streak + 1 : 1;
            $day = (($streak - 1) % 7) + 1;

            $rewards = (array) config('coins.checkin_rewards', []);
            $coins = (int) ($rewards[$day] ?? 5);

            $wallet->forceFill(['last_checkin_on' => DisplayTime::today(), 'checkin_streak' => $streak])->save();
            $this->creditLocked($wallet, $coins, CoinTransactionType::Checkin, null, __('Daily check-in — day :n', ['n' => $day]));

            return ['coins' => $coins, 'streak' => $streak, 'day' => $day];
        });
    }

    public function canCheckInToday(User $user): bool
    {
        $last = CoinWallet::where('user_id', $user->id)->value('last_checkin_on');

        return $last === null || (string) $last < DisplayTime::today()->toDateString();
    }

    /**
     * Admin grant (positive) or clawback (negative). A clawback consumes FIFO
     * and is capped at the wallet balance (never goes negative). Returns the
     * net coins applied.
     */
    public function adminAdjust(User $user, int $coins, string $reason): int
    {
        if (! $this->enabled() || $coins === 0) {
            return 0;
        }

        return DB::transaction(function () use ($user, $coins, $reason) {
            $wallet = $this->lockWallet($this->walletFor($user)->id);

            if ($coins > 0) {
                $this->creditLocked($wallet, $coins, CoinTransactionType::Adjustment, null, $reason);

                return $coins;
            }

            $clawback = min(abs($coins), (int) $wallet->balance);

            if ($clawback > 0) {
                $this->consumeLocked($wallet, $clawback, CoinTransactionType::Adjustment, null, $reason, null);
            }

            return -$clawback;
        });
    }

    /** Total spendable coins in circulation across all wallets. */
    public function circulationCoins(): int
    {
        return (int) CoinWallet::sum('balance');
    }

    /**
     * Expire lapsed lots (scheduled). Returns the total coins expired.
     */
    public function expireDue(): int
    {
        $total = 0;

        CoinTransaction::where('amount', '>', 0)
            ->where('remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(200, function ($lots) use (&$total) {
                foreach ($lots as $lot) {
                    $total += $this->expireLot($lot->id);
                }
            });

        return $total;
    }

    /**
     * BIZ-04: one reminder per lot, :days before it lapses. Lots of one wallet
     * entering the window on the same run share one notification. Returns the
     * number of buyers reminded.
     * ponytail: a daily check-in streak opens a lot a day, so an active buyer
     * can get a reminder a day; batch per week if buyers find it noisy.
     */
    public function remindExpiring(int $days = 30): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $lots = CoinTransaction::with('wallet.user')
            ->where('amount', '>', 0)
            ->where('remaining', '>', 0)
            ->whereNull('expiry_reminded_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($days))
            ->get();

        foreach ($lots->groupBy('coin_wallet_id') as $walletLots) {
            // Stamp first: a failed send loses one reminder, never sends two.
            CoinTransaction::whereKey($walletLots->modelKeys())->update(['expiry_reminded_at' => now()]);
            $walletLots->first()->wallet->user?->notify(
                new CoinsExpiringNotification((int) $walletLots->sum('remaining'), $walletLots->min('expires_at'))
            );
        }

        return $lots->groupBy('coin_wallet_id')->count();
    }

    // ----- locked primitives -----

    private function lockWallet(int $walletId): CoinWallet
    {
        return CoinWallet::whereKey($walletId)->lockForUpdate()->first();
    }

    private function creditLocked(CoinWallet $wallet, int $coins, CoinTransactionType $type, ?Model $reference, ?string $description, ?int $expiryDays = null): CoinTransaction
    {
        // BIZ-04: twelve calendar months after earning (config), not a day count.
        $months = (int) config('coins.expiry_months', 12);
        $expiresAt = $expiryDays !== null
            ? ($expiryDays > 0 ? now()->addDays($expiryDays) : null)
            : ($months > 0 ? now()->addMonthsNoOverflow($months) : null);

        $txn = $wallet->transactions()->create([
            'type' => $type,
            'amount' => $coins,
            'remaining' => $coins,
            'expires_at' => $expiresAt,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'description' => $description,
            'created_at' => now(),
        ]);

        $wallet->increment('balance', $coins);

        if ($type->countsLifetime()) {
            $wallet->increment('lifetime_earned', $coins);
        }

        return $txn;
    }

    /** Consume coins FIFO from the oldest live lots; write the debit row. */
    private function consumeLocked(CoinWallet $wallet, int $coins, CoinTransactionType $type, ?Model $reference, ?string $description, ?int $sen): CoinTransaction
    {
        $remaining = $coins;

        $lots = $wallet->transactions()
            ->where('amount', '>', 0)
            ->where('remaining', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $lot->remaining);
            $lot->decrement('remaining', $take);
            $remaining -= $take;
        }

        $txn = $wallet->transactions()->create([
            'type' => $type,
            'amount' => -$coins,
            'remaining' => 0,
            'sen' => $sen,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'description' => $description,
            'created_at' => now(),
        ]);

        $wallet->decrement('balance', $coins);

        return $txn;
    }

    /**
     * M-7: WALLET FIRST, THEN THE LOT — the house order, and this method used to
     * be the one exception.
     *
     * Every other path takes the wallet lock first (redeemForCheckout →
     * consumeLocked; adminAdjust / checkIn / credit via lockWallet). This one
     * locked the coin_transactions row and then reached for the wallet, which is
     * a textbook deadlock pair: an expiry sweep running while a buyer redeems at
     * checkout, each holding what the other is waiting for.
     *
     * The wallet id has to be read before the lot is locked, so the read is
     * unlocked — but it is an immutable FK, so nothing can move it underneath us,
     * and $lot is re-fetched under the lock afterwards regardless.
     */
    private function expireLot(int $lotId): int
    {
        return DB::transaction(function () use ($lotId) {
            $walletId = CoinTransaction::whereKey($lotId)->value('coin_wallet_id');

            if ($walletId === null) {
                return 0;
            }

            $wallet = $this->lockWallet((int) $walletId);
            $lot = CoinTransaction::whereKey($lotId)->lockForUpdate()->first();

            if ($lot === null || $lot->remaining <= 0) {
                return 0;
            }

            $amount = (int) $lot->remaining;

            $lot->forceFill(['remaining' => 0])->save();

            $wallet->transactions()->create([
                'type' => CoinTransactionType::Expire,
                'amount' => -$amount,
                'remaining' => 0,
                'description' => __('Coins expired'),
                'created_at' => now(),
            ]);

            $wallet->decrement('balance', $amount);

            return $amount;
        });
    }

    private function hasReferencedEntry(CoinWallet $wallet, CoinTransactionType $type, Model $reference): bool
    {
        return $wallet->transactions()
            ->where('type', $type)
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->exists();
    }
}
