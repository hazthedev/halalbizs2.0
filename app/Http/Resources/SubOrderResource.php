<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\OrderItem;
use App\Models\SubOrder;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One sub-order as the buyer's orders list and detail show it (mobile plan
 * §4). Item fields are the order_items snapshot (hard rule 5); `can` mirrors
 * the web buttons: Orders (confirm received, review), OrderDetail (cancel,
 * return). Pass detail: true for tracking, history and the address snapshot.
 *
 * @mixin SubOrder
 */
class SubOrderResource extends JsonResource
{
    public function __construct(SubOrder $resource, private bool $detail = false)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $items = $this->items;

        $data = [
            'id' => $this->id,
            'sub_order_no' => $this->sub_order_no,
            'order_no' => $order->order_no,
            'status' => $this->status->value,
            // Lapsed but not yet swept by orders:expire-unpaid (every minute):
            // it can no longer be paid, so it must not read "Pending payment".
            'status_label' => $this->status === SubOrderStatus::PendingPayment && ! $order->isAwaitingPayment()
                && $order->payment_method === PaymentMethod::Ipay88 && $order->payment_status === PaymentStatus::Pending
                ? __('Payment expired')
                : $this->status->label(),
            'store' => [
                'id' => $this->store->id,
                'slug' => $this->store->slug,
                'name' => $this->store->name,
                'logo' => $this->store->getFirstMediaUrl('logo', 'thumb') ?: null,
            ],
            // name / variant_label / prices are the order_items snapshot (hard rule 5);
            // only product_slug and product_image read the live product, for the link and picture.
            'items' => $items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_slug' => $item->product?->slug,
                // The app's "Buy again" re-adds this variant via POST cart/items; null once the variant is deleted.
                'variant_id' => $item->product_variant_id,
                'name' => $item->product_name,
                'variant_label' => $item->variant_label,
                'qty' => $item->qty,
                'unit_price_sen' => $item->unit_price_sen,
                'line_total_sen' => $item->line_total_sen,
                'product_image' => $item->product?->getFirstMediaUrl('images', 'thumb') ?: null,
                'reviewed' => $item->review !== null,
            ])->values()->all(),
            'subtotal_sen' => $this->items_subtotal_sen,
            'shipping_sen' => $this->shipping_fee_sen,
            'discount_sen' => $this->shop_discount_sen,
            'total_sen' => $this->total_sen,
            'payment' => [
                'method' => $order->payment_method->value,
                'status' => $order->payment_status->value,
            ],
            'placed_at' => $order->placed_at?->toIso8601String(),
            'can' => [
                'cancel' => $this->status === SubOrderStatus::Confirmed
                    || ($this->status === SubOrderStatus::PendingPayment && $order->isAwaitingPayment()),
                'confirm_received' => $this->status === SubOrderStatus::Delivered,
                'return' => app(ReturnService::class)->canRequest($this->resource),
                'review' => $this->status === SubOrderStatus::Completed
                    && $items->contains(fn (OrderItem $item) => $item->review === null),
            ],
            'return_request' => $this->returnRequest === null ? null : [
                'id' => $this->returnRequest->id,
                'status' => $this->returnRequest->status->value,
                'reason' => $this->returnRequest->reason?->label,
                'created_at' => $this->returnRequest->created_at?->toIso8601String(),
            ],
        ];

        if (! $this->detail) {
            return $data;
        }

        return $data + [
            'tracking' => $this->tracking_no === null ? null : [
                'courier' => $this->tracking_courier,
                'no' => $this->tracking_no,
                'url' => $this->tracking_url,
                'status' => $this->tracking_status?->value,
            ],
            'history' => $this->statusHistories->map(fn ($history) => [
                'status' => $history->to_status,
                'label' => SubOrderStatus::tryFrom($history->to_status)?->label() ?? $history->to_status,
                'at' => $history->created_at?->toIso8601String(),
                'note' => $history->note,
            ])->values()->all(),
            'address' => $order->shipping_address,
        ];
    }
}
