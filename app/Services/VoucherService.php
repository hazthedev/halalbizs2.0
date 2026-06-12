<?php

namespace App\Services;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use App\Support\VoucherDiscount;

/**
 * The M8 voucher engine (docs/09 §B, replaces voucher_lite). One validation
 * pipeline with human reasons at every gate: exists+active+window → scope
 * match → min_spend (platform: order items subtotal; shop: THAT store's
 * subtotal) → quota remaining → per-user count → compute discount.
 *
 * Stacking (Shopee model): one platform + one shop voucher per order —
 * CheckoutService validates each slot separately with `lock: true` so both
 * voucher rows are consumed under lockForUpdate inside the transaction.
 */
class VoucherService
{
    /**
     * @param  array<int, int>  $storeSubtotals  [store_id => items_subtotal_sen]
     *
     * @throws CheckoutException with a buyer-facing reason
     */
    public function validate(
        string $code,
        User $user,
        array $storeSubtotals,
        ?VoucherScope $scope = null,
        bool $lock = false,
    ): VoucherDiscount {
        $voucher = $this->lookup($code, $scope, $storeSubtotals, $lock);

        if ($voucher === null) {
            throw new CheckoutException(__("We can't find that voucher — check the code and try again."));
        }

        if (! $voucher->is_active) {
            throw new CheckoutException(__('This voucher is no longer active.'));
        }

        if (now()->lt($voucher->starts_at)) {
            throw new CheckoutException(__("This voucher isn't live yet — it starts :date.", [
                'date' => $voucher->starts_at->format('j M'),
            ]));
        }

        if (now()->gt($voucher->ends_at)) {
            throw new CheckoutException(__('This voucher expired on :date.', [
                'date' => $voucher->ends_at->format('j M Y'),
            ]));
        }

        // Scope match: a shop voucher only applies when its store is in the cart.
        if ($voucher->scope === VoucherScope::Shop && ! array_key_exists($voucher->store_id, $storeSubtotals)) {
            throw new CheckoutException(__('This voucher belongs to a different shop.'));
        }

        $basisSen = $voucher->scope === VoucherScope::Platform
            ? (int) array_sum($storeSubtotals)
            : (int) $storeSubtotals[$voucher->store_id];

        if ($voucher->min_spend_sen > 0 && $basisSen < $voucher->min_spend_sen) {
            throw new CheckoutException(__("This voucher needs a :min minimum — you're :short away.", [
                'min' => Money::format($voucher->min_spend_sen),
                'short' => Money::format($voucher->min_spend_sen - $basisSen),
            ]));
        }

        if (! $voucher->hasQuotaRemaining()) {
            throw new CheckoutException(__('This voucher has been fully redeemed.'));
        }

        if ($voucher->usages()->where('user_id', $user->id)->count() >= $voucher->per_user_limit) {
            throw new CheckoutException(__("You've already used this voucher the maximum number of times."));
        }

        // Free shipping zeroes the flagged stores' fees — the waived amount
        // is only knowable at checkout, so totalDiscountSen stays 0 here.
        if ($voucher->type === VoucherType::FreeShipping) {
            return new VoucherDiscount(
                voucher: $voucher,
                scope: $voucher->scope,
                totalDiscountSen: 0,
                perStoreDiscountSen: [],
                freeShippingStoreIds: $voucher->scope === VoucherScope::Platform
                    ? array_map('intval', array_keys($storeSubtotals))
                    : [(int) $voucher->store_id],
            );
        }

        $totalDiscountSen = min($voucher->discountSenFor($basisSen), $basisSen);

        return new VoucherDiscount(
            voucher: $voucher,
            scope: $voucher->scope,
            totalDiscountSen: $totalDiscountSen,
            perStoreDiscountSen: $voucher->scope === VoucherScope::Platform
                ? $this->prorate($totalDiscountSen, $storeSubtotals)
                : [(int) $voucher->store_id => $totalDiscountSen],
        );
    }

    /**
     * Allocate a platform discount across sub-orders by items-subtotal share
     * using LARGEST REMAINDER in sen, so the parts sum EXACTLY to the total
     * (docs/09 §B). e.g. 1000 over 3333/3333/3334 → 333 + 333 + 334.
     *
     * @param  array<int, int>  $storeSubtotals  [store_id => items_subtotal_sen]
     * @return array<int, int> [store_id => allocated_discount_sen]
     */
    public function prorate(int $totalSen, array $storeSubtotals): array
    {
        $grandSen = (int) array_sum($storeSubtotals);

        if ($totalSen <= 0 || $grandSen <= 0) {
            return array_map(fn () => 0, $storeSubtotals);
        }

        $shares = [];
        $remainders = [];
        $allocated = 0;

        foreach ($storeSubtotals as $storeId => $subtotalSen) {
            $numerator = $totalSen * $subtotalSen; // integer math only — fits 64-bit
            $shares[$storeId] = intdiv($numerator, $grandSen);
            $remainders[$storeId] = $numerator % $grandSen;
            $allocated += $shares[$storeId];
        }

        // Hand the leftover sen to the largest remainders (stable sort —
        // ties break by cart order, deterministically).
        arsort($remainders);

        $left = $totalSen - $allocated;

        foreach (array_keys($remainders) as $storeId) {
            if ($left <= 0) {
                break;
            }

            $shares[$storeId]++;
            $left--;
        }

        return $shares;
    }

    /**
     * Code lookup. The same code may exist platform-wide AND per store
     * (unique is store_id+code), so without an explicit scope we prefer
     * platform, then a shop voucher for a store in this cart, then any —
     * keeping the wrong-shop error reachable.
     *
     * @param  array<int, int>  $storeSubtotals
     */
    private function lookup(string $code, ?VoucherScope $scope, array $storeSubtotals, bool $lock): ?Voucher
    {
        $query = Voucher::query()->where('code', strtoupper(trim($code)));

        if ($scope === VoucherScope::Platform) {
            $query->where('scope', VoucherScope::Platform)->whereNull('store_id');
        } elseif ($scope === VoucherScope::Shop) {
            $query->where('scope', VoucherScope::Shop);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        $candidates = $query->get();

        return $candidates->first(fn (Voucher $voucher) => $voucher->scope === VoucherScope::Platform)
            ?? $candidates->first(fn (Voucher $voucher) => array_key_exists($voucher->store_id, $storeSubtotals))
            ?? $candidates->first();
    }
}
