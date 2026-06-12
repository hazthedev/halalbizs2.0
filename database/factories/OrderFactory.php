<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotalSen = fake()->numberBetween(1000, 50000);
        $shippingSen = fake()->numberBetween(0, 1500);

        return [
            'order_no' => Order::generateOrderNo(),
            'user_id' => User::factory(),
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => PaymentStatus::Pending,
            'shipping_address' => [
                'recipient_name' => fake()->name(),
                'phone' => '+60123456789',
                'line1' => fake()->streetAddress(),
                'line2' => null,
                'postcode' => '47500',
                'city' => 'Subang Jaya',
                'state' => 'Selangor',
                'country' => 'MY',
            ],
            'subtotal_sen' => $subtotalSen,
            'shipping_total_sen' => $shippingSen,
            'discount_total_sen' => 0,
            'grand_total_sen' => $subtotalSen + $shippingSen,
            'display_currency' => 'MYR',
            'display_rate' => 1,
            'placed_at' => now(),
        ];
    }

    /** Pending iPay88 order inside its payment window (the "To Pay" shape). */
    public function awaitingIpay88(int $minutesLeft = 45): static
    {
        return $this->state(fn () => [
            'payment_method' => PaymentMethod::Ipay88,
            'payment_status' => PaymentStatus::Pending,
            'expires_at' => now()->addMinutes($minutesLeft),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
