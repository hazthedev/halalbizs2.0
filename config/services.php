<?php

return [

    // Anthropic Claude — AI listing copy (M1.6) + shop concierge (M2.2).
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 30),
    ],

    // Bilingual shop concierge (M2.2). On by default; degrades to a
    // deterministic Scout-search assistant when no Claude key is configured.
    'concierge' => [
        'enabled' => env('CONCIERGE_ENABLED', true),
    ],

    // iPay88 optional refund endpoint (M0.4 automated refund).
    'ipay88' => [
        'refund_url' => env('IPAY88_REFUND_URL'),
    ],

    // Stripe — international cards/wallets (M1.9). Flagged for go-live approval.
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Populated at runtime from SecuritySettings (admin panel), not env —
    // see GoogleAuthController::configureGoogle().
    'google' => [
        'client_id' => null,
        'client_secret' => null,
        'redirect' => null,
    ],

];
