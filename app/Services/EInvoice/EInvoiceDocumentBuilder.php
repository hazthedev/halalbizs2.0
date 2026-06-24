<?php

namespace App\Services\EInvoice;

use App\Models\Store;
use App\Models\SubOrder;
use Illuminate\Support\Collection;

/**
 * Builds a provider-neutral e-invoice document from the SACRED order snapshots
 * (order_items / sub_orders / orders) — never from live product data (Hard
 * Rule 5). The supplier is always the seller (store); the platform may file on
 * their behalf as Intermediary. All amounts stay integer sen.
 */
class EInvoiceDocumentBuilder
{
    /** Individual e-invoice for a single sub-order. */
    public function forSubOrder(SubOrder $subOrder): array
    {
        $subOrder->loadMissing(['items', 'store', 'order.user']);
        $order = $subOrder->order;

        $lines = $subOrder->items->map(fn ($item) => [
            'description' => trim($item->product_name.' '.($item->variant_label ?? '')),
            'qty' => (int) $item->qty,
            'unitPriceSen' => (int) $item->unit_price_sen,
            'lineTotalSen' => (int) $item->line_total_sen,
            'taxSen' => (int) $item->tax_sen,
            'taxRateBp' => (int) $item->tax_rate_bp,
        ])->all();

        return [
            'type' => 'individual',
            'reference' => $subOrder->sub_order_no,
            'currency' => 'MYR',
            'issuedAt' => now()->toIso8601String(),
            'supplier' => $this->supplier($subOrder->store),
            'buyer' => [
                'name' => $order->shipping_address['recipient_name'] ?? ($order->user->name ?? 'Buyer'),
                'tin' => $order->user->tin ?? null,
            ],
            'lines' => $lines,
            'totals' => [
                'subtotalSen' => (int) $subOrder->items_subtotal_sen,
                'shippingSen' => (int) $subOrder->shipping_fee_sen,
                'discountSen' => (int) $subOrder->shop_discount_sen,
                'taxSen' => (int) $subOrder->tax_sen,
                'totalSen' => (int) $subOrder->total_sen,
            ],
        ];
    }

    /**
     * One consolidated B2C document for a store across a period. Each
     * un-requested receipt becomes a line (the LHDN consolidated model).
     *
     * @param  Collection<int, SubOrder>  $subOrders
     */
    public function forConsolidated(Store $store, string $period, Collection $subOrders): array
    {
        $lines = $subOrders->map(fn (SubOrder $subOrder) => [
            'description' => __('Receipt :no', ['no' => $subOrder->sub_order_no]),
            'qty' => 1,
            'unitPriceSen' => (int) ($subOrder->total_sen - $subOrder->tax_sen),
            'lineTotalSen' => (int) ($subOrder->total_sen - $subOrder->tax_sen),
            'taxSen' => (int) $subOrder->tax_sen,
            'taxRateBp' => 0,
        ])->all();

        $taxSen = (int) $subOrders->sum('tax_sen');
        $totalSen = (int) $subOrders->sum('total_sen');

        return [
            'type' => 'consolidated',
            'reference' => "{$store->id}-{$period}",
            'currency' => 'MYR',
            'issuedAt' => now()->toIso8601String(),
            'period' => $period,
            'supplier' => $this->supplier($store),
            'buyer' => [
                'name' => 'General Public',  // LHDN consolidated B2C buyer
                'tin' => 'EI00000000010',
            ],
            'lines' => $lines,
            'totals' => [
                'subtotalSen' => $totalSen - $taxSen,
                'taxSen' => $taxSen,
                'totalSen' => $totalSen,
            ],
        ];
    }

    private function supplier(Store $store): array
    {
        return [
            'name' => $store->name,
            'tin' => $store->tin ?? config('einvoice.platform.tin'),
            'sstNumber' => $store->sst_number,
            'state' => $store->state,
        ];
    }
}
