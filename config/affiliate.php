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

    // Buffer added on top of OrderSettings::return_window_days before a booked
    // commission stops being `pending` and becomes payable. It exists to catch
    // late disputes and delayed vendor confirmations — the return window closing
    // is not the same instant as everyone agreeing the sale stuck.
    //
    // Commission holds until: sub_order.delivered_at + return_window_days + this.
    // Anchoring on DELIVERY, not order date, is deliberate: sellers here fulfil
    // independently, so an order-date lock would free commission on things that
    // never shipped.
    'lock_buffer_days' => (int) env('AFFILIATE_LOCK_BUFFER_DAYS', 7),

    // How long a referral attribution cookie lives (days, last-click wins).
    'cookie_days' => (int) env('AFFILIATE_COOKIE_DAYS', 30),

    // Attribution cookie name.
    'cookie' => 'aff_ref',

];
