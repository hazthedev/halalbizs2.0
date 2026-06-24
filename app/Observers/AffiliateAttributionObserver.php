<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\AffiliateService;
use Throwable;

/**
 * Snapshots the referring affiliate onto an order at creation time (M2.5),
 * reading the last-click attribution cookie from the current request. This is
 * how affiliate attribution stays CHECKOUT-SAFE — CheckoutService is never
 * touched, and any failure here can never break order placement.
 */
class AffiliateAttributionObserver
{
    public function __construct(private AffiliateService $affiliates) {}

    public function created(Order $order): void
    {
        if (! $this->affiliates->enabled() || $order->affiliate_id !== null) {
            return;
        }

        try {
            $affiliate = $this->affiliates->fromRequestCookie();

            // No self-referral: a creator can't earn commission on their own order.
            if ($affiliate !== null && $affiliate->user_id !== $order->user_id) {
                $order->forceFill(['affiliate_id' => $affiliate->id])->saveQuietly();
            }
        } catch (Throwable) {
            // Attribution is best-effort; never disrupt checkout.
        }
    }
}
