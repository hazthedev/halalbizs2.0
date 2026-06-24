<?php

return [

    /*
    | Default parcel weight when a product has none, so a rate can still be
    | quoted. Grams (integer).
    */
    'default_weight_grams' => (int) env('SHIPPING_DEFAULT_WEIGHT_GRAMS', 500),

    /*
    |--------------------------------------------------------------------------
    | EasyParcel (Malaysian multi-courier aggregator)
    |--------------------------------------------------------------------------
    | One API for live rates + booking across J&T / Pos Laju / Ninja Van /
    | City-Link etc. Inert until enabled + an API key is supplied; until then
    | stores set to 'easyparcel' fall back to their flat fee so checkout works.
    */
    'easyparcel' => [
        'enabled' => (bool) env('EASYPARCEL_ENABLED', false),
        'api_key' => env('EASYPARCEL_API_KEY'),
        'base_url' => env('EASYPARCEL_BASE_URL', 'https://connect.easyparcel.my'),
        'sandbox' => (bool) env('EASYPARCEL_SANDBOX', true),
        'origin_postcode' => env('EASYPARCEL_ORIGIN_POSTCODE'),
        'origin_state' => env('EASYPARCEL_ORIGIN_STATE'),
        'webhook_token' => env('EASYPARCEL_WEBHOOK_TOKEN'),
    ],
];
