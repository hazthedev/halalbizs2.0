<?php

namespace App\Services;

use App\Enums\CoinTransactionType;
use App\Exceptions\CheckoutException;
use App\Models\CoinTransaction;
use App\Models\CoinWallet;
use App\Models\Order;
use App\Models\User;
use App\Support\CoinRedemptionResult;
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

        if ($wallet === null || $wallet->balance <= 0) {
            return CoinRedemptionResult::none();
        }

        $rate = max(1, (int) config('coins.redemption_rate_sen', 1));
        $maxSen = min((int) config('coins.max_redemption_sen', 5000), max(0, $maxSpendableSen - 1));
        $coins = min($requestedCoins, (int) $wallet->balance, intdiv($maxSen, $rate));

        if ($coins <= 0) {
            return CoinRedemptionResult::none();
        }

        $sen = $coins * $rate;
        $txn = $this->consumeLocked($wallet, $coins, CoinTransactionType::Redeem, null, null, $sen);

        return new CoinRedemptionResult($coins, $sen, $txn);
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
            $today = now()->toDateString();

            if ($wallet->last_checkin_on?->toDateString() === $today) {
                throw new CheckoutException(__('You have already checked in today.'));
            }

            $continuing = $wallet->last_checkin_on?->toDateString() === now()->subDay()->toDateString();
            $streak = $continuing ? $wallet->checkin_streak + 1 : 1;
            $day = (($streak - 1) % 7) + 1;

            $rewards = (array) config('coins.checkin_rewards', []);
            $coins = (int) ($rewards[$day] ?? 5);

            $wallet->forceFill(['last_checkin_on' => now(), 'checkin_streak' => $streak])->save();
            $this->creditLocked($wallet, $coins, CoinTransactionType::Checkin, null, __('Daily check-in — day :n', ['n' => $day]));

            return ['coins' => $coins, 'streak' => $streak, 'day' => $day];
        });
    }

    public function canCheckInToday(User $user): bool
    {
        $last = CoinWallet::where('user_id', $user->id)->value('last_checkin_on');

        return $last === null || (string) $last < now()->toDateString();
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

    // ----- locked primitives -----

    private function lockWallet(int $walletId): CoinWallet
    {
        return CoinWallet::whereKey($walletId)->lockForUpdate()->first();
    }

    private function creditLocked(CoinWallet $wallet, int $coins, CoinTransactionType $type, ?Model $reference, ?string $description, ?int $expiryDays = null): CoinTransaction
    {
        $expiryDays ??= (int) config('coins.expiry_days', 180);

        $txn = $wallet->transactions()->create([
            'type' => $type,
            'amount' => $coins,
            'remaining' => $coins,
            'expires_at' => $expiryDays > 0 ? now()->addDays($expiryDays) : null,
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

    private function expireLot(int $lotId): int
    {
        return DB::transaction(function () use ($lotId) {
            $lot = CoinTransaction::whereKey($lotId)->lockForUpdate()->first();

            if ($lot === null || $lot->remaining <= 0) {
                return 0;
            }

            $wallet = $this->lockWallet($lot->coin_wallet_id);
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
