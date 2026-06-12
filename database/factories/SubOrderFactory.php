<?php

namespace Database\Factories;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only — application code must never insert sub-orders outside
 * CheckoutService, and must never change status outside SubOrderStatusService
 * (CLAUDE.md hard rule 2).
 *
 * @extends Factory<SubOrder>
 */
class SubOrderFactory extends Factory
{
    public function definition(): array
    {
        $itemsSen = fake()->numberBetween(1000, 30000);
        $shippingSen = fake()->numberBetween(0, 1000);

        return [
            'sub_order_no' => SubOrder::generateSubOrderNo(),
            'order_id' => Order::factory(),
            'store_id' => Store::factory()->approved(),
            'status' => SubOrderStatus::Confirmed,
            'items_subtotal_sen' => $itemsSen,
            'shipping_fee_sen' => $shippingSen,
            'shop_discount_sen' => 0,
            'total_sen' => $itemsSen + $shippingSen,
            'commission_rate' => '5.00',
        ];
    }

    /** Mirror CheckoutService: every sub-order is born with a history row. */
    public function configure(): static
    {
        return $this->afterCreating(function (SubOrder $subOrder) {
            if ($subOrder->statusHistories()->doesntExist()) {
                $subOrder->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => $subOrder->status->value,
                    'actor_type' => ActorType::System,
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function status(SubOrderStatus $status): static
    {
        return $this->state(fn () => array_merge(['status' => $status], match ($status) {
            SubOrderStatus::Shipped => $this->trackingAttributes(),
            SubOrderStatus::Delivered => $this->trackingAttributes() + ['delivered_at' => now()],
            SubOrderStatus::Completed => $this->trackingAttributes() + ['delivered_at' => now()->subDay(), 'completed_at' => now()],
            SubOrderStatus::Cancelled => ['cancelled_at' => now()],
            default => [],
        }));
    }

    private function trackingAttributes(): array
    {
        return [
            'shipped_at' => now()->subDay(),
            'tracking_courier' => 'J&T Express',
            'tracking_no' => strtoupper(fake()->bothify('JT##########MY')),
        ];
    }
}
