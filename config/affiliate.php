<?php

return [

    // Master switch. When off, enrollment, link capture and commission accrual
    // are all inert and the creator UI hides itself.
    'enabled' => env('AFFILIATE_ENABLED', true),

    // Default commission as basis points of a referred sub-order's items
    // subtotal (pre-tax, pre-shipping). 500 bp = 5%.
    'commission_rate_bp' => (int) env('AFFILIATE_COMMISSION_BP', 500),

    // Minimum withdrawal a creator can request (sen). RM 50 default.
    'min_payout_sen' => (int) env('AFFILIATE_MIN_PAYOUT_SEN', 5000),

    // How long a referral attribution cookie lives (days, last-click wins).
    'cookie_days' => (int) env('AFFILIATE_COOKIE_DAYS', 30),

    // Attribution cookie name.
    'cookie' => 'aff_ref',

];
