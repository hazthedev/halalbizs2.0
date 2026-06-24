<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once, after commit, when an order's payment is verified (COD on
 * delivery, or iPay88 BackendURL callback + requery). The hook point for
 * e-invoicing and any other post-payment automation.
 */
class OrderPaid
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
