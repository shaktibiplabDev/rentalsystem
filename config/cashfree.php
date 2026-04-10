<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cashfree Environment
    |--------------------------------------------------------------------------
    */
    'environment' => env('CASHFREE_ENV', 'sandbox'), // 'sandbox' or 'production'

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway API Credentials (for wallet recharge)
    |--------------------------------------------------------------------------
    */
    'app_id' => env('CASHFREE_APP_ID'),           // Payment Client ID
    'secret_key' => env('CASHFREE_SECRET_KEY'),   // Payment Client Secret
    'api_version' => env('CASHFREE_API_VERSION', '2023-08-01'),

    /*
    |--------------------------------------------------------------------------
    | Verification API Credentials (for Driving License verification)
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'client_id' => env('CASHFREE_VERIFICATION_CLIENT_ID'),
        'client_secret' => env('CASHFREE_VERIFICATION_CLIENT_SECRET'),
        'base_url' => env('CASHFREE_ENV') === 'production'
            ? 'https://api.cashfree.com'
            : 'https://sandbox.cashfree.com',
        'api_version' => '2025-01-01',
    ],

];