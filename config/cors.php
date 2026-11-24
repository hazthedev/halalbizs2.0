<?php

/*
 * Laravel's default CORS config with one change, HB-G180 (HB-DEC-1124f item 3):
 * cross-origin browser calls to the API are allowed from the live site only,
 * not from '*'. The phone app sends no Origin header, so CORS never applies
 * to it, and the website calls its own origin. A local tool on another origin
 * sets CORS_ALLOWED_ORIGINS (comma separated) in its own .env.
 */
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://halalbizs.com'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
