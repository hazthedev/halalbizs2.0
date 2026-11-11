<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\SubOrder;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sub-order as the seller centre shows it (mobile plan §4). Items and the
 * address are the purchase-time snapshots (hard rule 5); `history` appears
 * only when the controller loaded statusHistories (detail).
 *
 * @mixin SubOrder
 */
class SellerOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $address = $order?->shipping_address ?? [];
        $return = $this->returnRequest;

        return [
            'id' => $this->id,
            'sub_order_no' => $this->sub_order_no,
            'order_no' => $order?->order_no,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'placed_at' => ($order?->placed_at ?? $this->created_at)?->toIso8601String(),
            // The seller detail page shows the buyer's phone in full — they ship to it.
            'buyer' => [
                'name' => $order?->user?->name,
                'phone' => $address['phone'] ?? $order?->user?->phone,
            ],
            'address' => $address,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'variant_name' => $item->variant_label,
                'qty' => $item->qty,
                'unit_price_sen' => $item->unit_price_sen,
                'line_total_sen' => $item->line_total_sen,
                // Display only: name/variant/price above are the order_items
                // snapshot (hard rule 5); the picture is the live product's,
                // as on the web seller page. Null once the product is gone.
                'product_image' => $item->product?->getFirstMediaUrl('images', 'thumb') ?: null,
            ])->values()),
            'subtotal_sen' => $this->items_subtotal_sen,
            'shipping_sen' => $this->shipping_fee_sen,
            'discount_sen' => $this->shop_discount_sen,
            'tax_sen' => $this->tax_sen,
            'total_sen' => $this->total_sen,
            'commission_rate' => (string) $this->commission_rate,
            // Stamped at completion. Before that, the same formula the ledger
            // will book (LedgerService::commissionSen), flagged not settled.
            'commission_sen' => $this->commission_sen
                ?? ($this->relationLoaded('items') ? app(LedgerService::class)->commissionSen($this->resource) : null),
            'commission_settled' => $this->commission_sen !== null,
            'payment' => [
                'method' => $order?->payment_method?->value,
                'status' => $order?->payment_status?->value,
            ],
            'tracking' => [
                'courier' => $this->tracking_courier,
                'no' => $this->tracking_no,
                'url' => $this->tracking_url,
                'status' => $this->tracking_status?->value,
            ],
            'return_request' => $return === null ? null : [
                'id' => $return->id,
                'status' => $return->status->value,
                'status_label' => $return->status->label(),
                'reason' => $return->reason?->getTranslation('label', app()->getLocale()),
                'description' => $return->description,
                'respond_by' => $return->respond_by?->toIso8601String(),
            ],
            'history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn (OrderStatusHistory $row) => [
                'status' => $row->to_status,
                'from' => $row->from_status,
                'at' => $row->created_at?->toIso8601String(),
                'actor' => $row->actor_type?->value,
                'note' => $row->note,
            ])->values()),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
