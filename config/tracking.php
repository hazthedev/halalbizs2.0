<?php

return [

    /*
    | Shipment tracking is deliberately separate from shipment booking.
    | EasyParcel may book a Malaysian parcel; AfterShip normalizes tracking
    | checkpoints from that courier or any supported international courier.
    */
    'provider' => env('TRACKING_PROVIDER', 'none'),

    'aftership' => [
        'api_key' => env('AFTERSHIP_API_KEY'),
        'webhook_secret' => env('AFTERSHIP_WEBHOOK_SECRET'),
        'base_url' => env('AFTERSHIP_BASE_URL', 'https://api.aftership.com/tracking/2026-07'),
        'timeout' => (int) env('AFTERSHIP_TIMEOUT', 10),
    ],

];
