<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout method registry (M1.9)
    |--------------------------------------------------------------------------
    | Data-driven payment methods. `via` is the settlement gateway (null = COD).
    | `channel` is the gateway sub-method (iPay88 PaymentId / FPX bank code).
    | The checkout UI renders from this list; launch surfaces COD + iPay88 rails.
    */
    'methods' => [
        'cod' => ['label' => 'Cash on Delivery', 'via' => null, 'channel' => null, 'launch' => true],
        'fpx' => ['label' => 'FPX Online Banking', 'via' => 'ipay88', 'channel' => 'FPX', 'launch' => true],
        'card' => ['label' => 'Credit / Debit Card', 'via' => 'ipay88', 'channel' => 'CC', 'launch' => true],
        'tng' => ['label' => "Touch 'n Go eWallet", 'via' => 'ipay88', 'channel' => 'TNG', 'launch' => true],
        'grabpay' => ['label' => 'GrabPay', 'via' => 'ipay88', 'channel' => 'GrabPay', 'launch' => true],
        'boost' => ['label' => 'Boost', 'via' => 'ipay88', 'channel' => 'Boost', 'launch' => true],
        'shopeepay' => ['label' => 'ShopeePay', 'via' => 'ipay88', 'channel' => 'ShopeePay', 'launch' => false],
        'atome' => ['label' => 'Atome (Buy Now Pay Later)', 'via' => 'ipay88', 'channel' => 'Atome', 'launch' => false],
        'card_intl' => ['label' => 'International Card', 'via' => 'stripe', 'channel' => null, 'launch' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settlement currency (worldwide groundwork)
    |--------------------------------------------------------------------------
    | The platform settles to sellers in these currencies; MYR at launch.
    | Multi-currency settlement is staged here for the worldwide roadmap —
    | display conversion already exists (CurrencyConverter).
    */
    'settlement_currencies' => ['MYR'],
    'default_settlement_currency' => env('SETTLEMENT_CURRENCY', 'MYR'),

    /*
    |--------------------------------------------------------------------------
    | Geo defaults (geo-pricing groundwork)
    |--------------------------------------------------------------------------
    | Default buyer country for tax jurisdiction + display, and the country
    | whose prices are authoritative. Per-region price lists plug in later.
    */
    'geo' => [
        'default_country' => env('GEO_DEFAULT_COUNTRY', 'MY'),
        'price_country' => env('GEO_PRICE_COUNTRY', 'MY'),
    ],
];
