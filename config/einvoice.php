<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active provider
    |--------------------------------------------------------------------------
    | Which e-invoicing provider issues documents. 'null' records documents
    | locally without filing (default — safe for local/dev and any market where
    | no e-invoice regime is wired). 'myinvois' files with LHDN (Malaysia).
    */
    'provider' => env('EINVOICE_PROVIDER', 'null'),

    /*
    | Orders at or above this grand total (in sen) must get an individual
    | e-invoice and can never be consolidated (LHDN: RM10,000 from 2026).
    */
    'individual_threshold_sen' => (int) env('EINVOICE_INDIVIDUAL_THRESHOLD_SEN', 1_000_000),

    /*
    |--------------------------------------------------------------------------
    | LHDN MyInvois (Malaysia)
    |--------------------------------------------------------------------------
    | The platform files as an Intermediary on behalf of its sellers. Live
    | filing needs Intermediary client credentials + a digital-signature cert
    | (authorised dependency). Until those are supplied, keep provider = 'null'.
    | Base URLs: preprod (sandbox) vs production.
    */
    'myinvois' => [
        'sandbox' => (bool) env('MYINVOIS_SANDBOX', true),
        'base_url' => env('MYINVOIS_BASE_URL', 'https://preprod-api.myinvois.hasil.gov.my'),
        'identity_url' => env('MYINVOIS_IDENTITY_URL', 'https://preprod-api.myinvois.hasil.gov.my'),
        'client_id' => env('MYINVOIS_CLIENT_ID'),
        'client_secret' => env('MYINVOIS_CLIENT_SECRET'),
        // Intermediary acting on behalf of a taxpayer (the seller / platform TIN).
        'on_behalf_of' => env('MYINVOIS_ON_BEHALF_OF'),
        'signing_cert_path' => env('MYINVOIS_SIGNING_CERT_PATH'),
        'signing_key_path' => env('MYINVOIS_SIGNING_KEY_PATH'),
    ],

    /*
    | Supplier identity for the platform when it issues on a seller's behalf
    | and the seller has no TIN of their own (consolidated B2C fallback).
    */
    'platform' => [
        'name' => env('EINVOICE_PLATFORM_NAME', 'HalalBizs'),
        'tin' => env('EINVOICE_PLATFORM_TIN'),
    ],
];
