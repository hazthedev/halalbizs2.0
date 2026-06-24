<?php

namespace App\Services;

use App\Enums\CoinTransactionType;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\CoinWallet;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Spin-to-win (M2.1): one spin per buyer per calendar day, drawing a weighted
 * prize from the configured deck. Coin prizes credit the wallet via CoinService;
 * voucher prizes mint a single-use personal platform voucher (quota 1) whose
 * code is only shown to the winner.
 */
class SpinService
{
    public function __construct(private CoinService $coins) {}

    public function enabled(): bool
    {
        return $this->coins->enabled();
    }

    public function canSpinToday(User $user): bool
    {
        $last = CoinWallet::where('user_id', $user->id)->value('last_spin_on');

        return $last === null || (string) $last < now()->toDateString();
    }

    /**
     * @return array{type: string, label: string, coins?: int, voucher?: Voucher}
     *
     * @throws CheckoutException when disabled or already spun today
     */
    public function spin(User $user): array
    {
        if (! $this->enabled()) {
            throw new CheckoutException(__('The prize wheel is unavailable right now.'));
        }

        return DB::transaction(function () use ($user) {
            $wallet = CoinWallet::firstOrCreate(['user_id' => $user->id]);
            $wallet = CoinWallet::whereKey($wallet->id)->lockForUpdate()->first();

            if ($wallet->last_spin_on?->toDateString() === now()->toDateString()) {
                throw new CheckoutException(__('You have already spun the wheel today — come back tomorrow!'));
            }

            $wallet->forceFill(['last_spin_on' => now()])->save();

            return $this->grant($user, $this->draw());
        });
    }

    /**
     * Weighted random draw from config('coins.spin_deck').
     *
     * @return array<string, mixed>
     */
    public function draw(): array
    {
        $deck = array_values((array) config('coins.spin_deck', []));
        $total = array_sum(array_map(fn ($slot) => (int) ($slot['weight'] ?? 0), $deck));

        if ($total <= 0) {
            return ['type' => 'nothing'];
        }

        $roll = random_int(1, $total);
        $cursor = 0;

        foreach ($deck as $slot) {
            $cursor += (int) ($slot['weight'] ?? 0);

            if ($roll <= $cursor) {
                return $slot;
            }
        }

        return ['type' => 'nothing'];
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array{type: string, label: string, coins?: int, voucher?: Voucher}
     */
    public function grant(User $user, array $slot): array
    {
        return match ($slot['type'] ?? 'nothing') {
            'coins' => $this->grantCoins($user, (int) ($slot['coins'] ?? 0)),
            'voucher' => $this->grantVoucher($slot),
            default => ['type' => 'nothing', 'label' => __('No prize this time — try again tomorrow!')],
        };
    }

    /**
     * @return array{type: string, label: string, coins: int}
     */
    private function grantCoins(User $user, int $coins): array
    {
        $this->coins->credit($user, $coins, CoinTransactionType::Spin, null, __('Spin-to-win reward'));

        return ['type' => 'coins', 'coins' => $coins, 'label' => __('You won :n coins!', ['n' => $coins])];
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array{type: string, label: string, voucher: Voucher}
     */
    private function grantVoucher(array $slot): array
    {
        $isFixed = ($slot['voucher'] ?? 'free_shipping') === 'fixed';
        $valueSen = (int) ($slot['value_sen'] ?? 0);

        $voucher = Voucher::create([
            'scope' => VoucherScope::Platform,
            'store_id' => null,
            'code' => $this->uniqueCode(),
            'type' => $isFixed ? VoucherType::Fixed : VoucherType::FreeShipping,
            'funded_by' => 'platform',
            'value_sen' => $isFixed ? $valueSen : 0,
            'min_spend_sen' => (int) ($slot['min_spend_sen'] ?? 0),
            'quota' => 1,
            'per_user_limit' => 1,
            'used_count' => 0,
            'starts_at' => now(),
            'ends_at' => now()->addDays((int) config('coins.spin_voucher_days', 14)),
            'is_active' => true,
        ]);

        return [
            'type' => 'voucher',
            'voucher' => $voucher,
            'label' => $isFixed
                ? __('You won :amount off your next order!', ['amount' => Money::format($valueSen)])
                : __('You won a free-shipping voucher!'),
        ];
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'SPIN'.strtoupper(Str::random(6));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}
