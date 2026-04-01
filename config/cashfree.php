<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cashfree API Configuration
    |--------------------------------------------------------------------------
    */
    
    'mode' => env('CASHFREE_MODE', 'sandbox'),
    
    /*
    |--------------------------------------------------------------------------
    | Verification API Configuration (DL Verification Only)
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'client_id' => env('CASHFREE_VERIFICATION_CLIENT_ID'),
        'client_secret' => env('CASHFREE_VERIFICATION_CLIENT_SECRET'),
        'base_url' => env('CASHFREE_MODE') === 'production' 
            ? 'https://api.cashfree.com'
            : 'https://sandbox.cashfree.com',
        'api_version' => '2025-01-01',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Payment API Configuration (If using Cashfree Payment Gateway)
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'client_id' => env('CASHFREE_PAYMENT_CLIENT_ID'),
        'client_secret' => env('CASHFREE_PAYMENT_CLIENT_SECRET'),
        'base_url' => env('CASHFREE_MODE') === 'production'
            ? 'https://api.cashfree.com'
            : 'https://sandbox.cashfree.com',
        'api_version' => '2022-09-01',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook_secret' => env('CASHFREE_WEBHOOK_SECRET'),
];