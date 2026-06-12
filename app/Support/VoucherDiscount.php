<?php

namespace App\Support;

use App\Enums\VoucherScope;
use App\Models\Voucher;

/**
 * The result of VoucherService::validate() (docs/09 §B). All amounts are
 * integer sen. Platform vouchers carry per-store allocations prorated by
 * items-subtotal share (largest remainder — parts sum EXACTLY to total);
 * shop vouchers carry a single entry for the voucher's store.
 */
final class VoucherDiscount
{
    /**
     * @param  array<int, int>  $perStoreDiscountSen  [store_id => discount_sen]
     * @param  list<int>  $freeShippingStoreIds  stores whose shipping this voucher zeroes
     */
    public function __construct(
        public readonly Voucher $voucher,
        public readonly VoucherScope $scope,
        public readonly int $totalDiscountSen,
        public readonly array $perStoreDiscountSen,
        public readonly array $freeShippingStoreIds = [],
    ) {}

    public function discountForStore(int $storeId): int
    {
        return $this->perStoreDiscountSen[$storeId] ?? 0;
    }

    public function freesShippingFor(int $storeId): bool
    {
        return in_array($storeId, $this->freeShippingStoreIds, true);
    }
}
