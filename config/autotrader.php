<?php

return [

    'key' => env('AUTOTRADER_KEY'),
    'secret' => env('AUTOTRADER_SECRET'),
    'advertiser_id' => env('AUTOTRADER_ADVERTISER_ID'),
    'base_url' => rtrim((string) env('AUTOTRADER_BASE_URL', 'https://api-sandbox.autotrader.co.uk'), '/'),
    'webhook_secret' => env('AUTOTRADER_WEBHOOK_SECRET'),
    /*
    | Autotrader's notification guide is the source of truth for the hash.
    | hmac-sha256 signs the raw body with the webhook secret.
    | The secret is never stored in the database and never written to the call log.
    */
    'webhook_hash' => env('AUTOTRADER_WEBHOOK_HASH', 'hmac-sha256'),
    'environment' => env('AUTOTRADER_ENVIRONMENT', 'sandbox'),
    /*
    | Sandbox credentials expire on a date Autotrader gives you.
    | Store it here so the dealer overview can warn before access drops.
    | Example: 2026-12-31
    */
    'sandbox_expires_at' => env('AUTOTRADER_SANDBOX_EXPIRES_AT'),
    'page_size' => (int) env('AUTOTRADER_PAGE_SIZE', 20),
    'timeout' => (int) env('AUTOTRADER_TIMEOUT', 20),
    'baseline_refresh_hours' => (int) env('AUTOTRADER_BASELINE_REFRESH_HOURS', 12),

    'paths' => [
        'authenticate' => '/authenticate',
        'stock' => '/stock',
        'advertisers' => '/advertisers',
        'features' => '/stock/{stockId}/features',
        'charge_times' => '/stock/{stockId}/charge-times',
    ],

];
