<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionOrderPlacedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Subscribe-and-save / predictive replenishment (M2.8). Recurring orders are
 * placed through the EXISTING checkout via explicit lines + a forced sub price,
 * so place()'s cart behaviour is untouched. The processor advances next_run_at
 * under a row lock BEFORE placing, so a failure skips the cycle instead of
 * retry-storming, and concurrent runs can't double-place. COD only in v1 (no
 * stored payment), so each delivery is paid on arrival.
 */
class SubscriptionService
{
    public function __construct(private CheckoutService $checkout) {}

    public function enabled(): bool
    {
        return (bool) config('subscriptions.enabled', true);
    }

    public function subscribe(User $user, ProductVariant $variant, Address $address, SubscriptionInterval $interval, int $qty = 1): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'address_id' => $address->id,
            'qty' => max(1, $qty),
            'interval_days' => $interval->days(),
            'discount_bp' => (int) config('subscriptions.discount_bp', 500),
            'payment_method' => PaymentMethod::Cod,
            'status' => SubscriptionStatus::Active,
            'next_run_at' => now()->addDays($interval->days()),
        ]);
    }

    public function pause(Subscription $subscription): void
    {
        $subscription->update(['status' => SubscriptionStatus::Paused]);
    }

    public function resume(Subscription $subscription): void
    {
        $next = $subscription->next_run_at?->isFuture()
            ? $subscription->next_run_at
            : now()->addDays($subscription->interval_days);

        $subscription->update(['status' => SubscriptionStatus::Active, 'next_run_at' => $next]);
    }

    public function cancel(Subscription $subscription): void
    {
        $subscription->update(['status' => SubscriptionStatus::Cancelled, 'next_run_at' => null]);
    }

    /** Discounted unit price in sen for a subscription line (≥ 1 sen). */
    public function discountedUnitSen(ProductVariant $variant, int $discountBp): int
    {
        $base = $variant->effectivePriceSen();

        return max(1, $base - intdiv($base * $discountBp, 10000));
    }

    /** Place one cycle's order for a subscription (no schedule advance). */
    public function placeOrderFor(Subscription $subscription): ?Order
    {
        $variant = $subscription->variant;
        $address = $subscription->address;

        if ($variant === null || $address === null || ! $variant->product->isLive()) {
            return null;
        }

        $order = $this->checkout->place(
            $subscription->user,
            $address,
            $subscription->payment_method,
            explicitLines: [[
                'variant_id' => $variant->id,
                'qty' => $subscription->qty,
                'price_sen' => $this->discountedUnitSen($variant, $subscription->discount_bp),
            ]],
        );

        $order->forceFill(['subscription_id' => $subscription->id])->save();

        return $order;
    }

    /** Process every due subscription (scheduled). Returns the orders placed. */
    public function processDue(): int
    {
        $placed = 0;

        Subscription::due()->orderBy('id')->pluck('id')->each(function ($id) use (&$placed) {
            $placed += $this->processOne((int) $id);
        });

        return $placed;
    }

    private function processOne(int $id): int
    {
        return DB::transaction(function () use ($id) {
            $subscription = Subscription::whereKey($id)->lockForUpdate()->first();

            if ($subscription === null
                || ! $subscription->isActive()
                || $subscription->next_run_at === null
                || $subscription->next_run_at->isFuture()) {
                return 0; // already handled, or no longer due
            }

            // Advance FIRST: a placement failure then skips this cycle rather
            // than retrying every tick.
            $subscription->forceFill([
                'next_run_at' => now()->addDays($subscription->interval_days),
                'last_ordered_at' => now(),
            ])->save();

            try {
                $order = $this->placeOrderFor($subscription);

                if ($order !== null) {
                    $subscription->user?->notify(new SubscriptionOrderPlacedNotification($order->order_no));

                    return 1;
                }

                return 0;
            } catch (Throwable $e) {
                Log::warning('Subscription order failed; skipping this cycle.', [
                    'subscription' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);

                return 0;
            }
        });
    }
}
