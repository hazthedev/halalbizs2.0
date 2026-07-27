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

        // Explicit opt-in for the built-in payment SIMULATOR when no merchant
        // code is set. Local dev enables the mock automatically; any other
        // environment (incl. a preview that declares APP_ENV=production) must set
        // IPAY88_ALLOW_MOCK=true to use it — so a real production boot with an
        // unconfigured merchant code can NEVER hand out free "paid" orders.
        'allow_mock' => env('IPAY88_ALLOW_MOCK', false),
    ],

    // Cloudflare Turnstile keys live in SecuritySettings (admin panel). This
    // only gates the UNCONFIGURED passthrough: local/testing always pass, but
    // any other environment with no keys must opt in explicitly or every
    // register/login form fails closed (AL-C7). Must be read via config (not a
    // raw env() call in app code) — under `config:cache` the .env file is
    // never loaded, so env() there would silently return null in production.
    'turnstile' => [
        'allow_unconfigured' => (bool) env('TURNSTILE_ALLOW_UNCONFIGURED', false),
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

    // WhatsApp Cloud API — phone-verification OTP over WhatsApp (free tier)
    // instead of paid SMS. Bound as the SmsSender only when token +
    // phone_number_id are set; otherwise the app keeps logging (dev). Set these
    // in the server .env after creating the WhatsApp number + an approved
    // "authentication" template in Meta Business Manager. See AppServiceProvider.
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'template' => env('WHATSAPP_TEMPLATE', 'verification_code'),
        'template_lang' => env('WHATSAPP_TEMPLATE_LANG', 'en'),
        'version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

];
