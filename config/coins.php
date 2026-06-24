<?php

return [

    // Master switch. When off, earning/redemption/check-in/spin are all inert
    // and the storefront UI hides itself — the checkout default of 0 coins
    // keeps the money path identical (Hard Rule 1 / backward compatibility).
    'enabled' => env('COINS_ENABLED', true),

    // Redemption: 1 coin is worth this many sen off the bill.
    'redemption_rate_sen' => (int) env('COINS_REDEMPTION_RATE_SEN', 1),

    // Most coin value redeemable on a single order (sen). RM 50 default.
    'max_redemption_sen' => (int) env('COINS_MAX_REDEMPTION_SEN', 5000),

    // Coins lapse this many days after they are earned (FIFO).
    'expiry_days' => (int) env('COINS_EXPIRY_DAYS', 180),

    // Earning: coins per whole Ringgit of items subtotal on a completed order.
    'earn_coins_per_rm' => (int) env('COINS_EARN_PER_RM', 1),

    // Daily check-in reward by streak day (cycles every 7 days).
    'checkin_rewards' => [1 => 5, 2 => 5, 3 => 10, 4 => 10, 5 => 15, 6 => 15, 7 => 30],

    // Spin-to-win deck: each entry is weighted. Coin prizes credit the wallet;
    // voucher prizes issue a personal voucher via VoucherService.
    'spin_deck' => [
        ['type' => 'coins', 'coins' => 5, 'weight' => 30],
        ['type' => 'coins', 'coins' => 20, 'weight' => 18],
        ['type' => 'coins', 'coins' => 50, 'weight' => 7],
        ['type' => 'voucher', 'voucher' => 'fixed', 'value_sen' => 500, 'min_spend_sen' => 3000, 'weight' => 12],
        ['type' => 'voucher', 'voucher' => 'free_shipping', 'weight' => 13],
        ['type' => 'nothing', 'weight' => 20],
    ],

];
