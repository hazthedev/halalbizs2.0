<?php

return [

    // Master switch. When off, the PDP subscribe option and the account
    // manager hide, and the processor is a no-op.
    'enabled' => env('SUBSCRIPTIONS_ENABLED', true),

    // Standing subscribe-and-save discount, basis points of the item price.
    // 500 bp = 5% off every recurring order.
    'discount_bp' => (int) env('SUBSCRIPTIONS_DISCOUNT_BP', 500),

];
