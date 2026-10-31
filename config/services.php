<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'api_key_secondary' => env('GEMINI_API_KEY_SECONDARY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'apiperu' => [
        'token' => env('API_PERU_TOKEN'),
        'document_url' => env('API_PERU_DOCUMENT_URL', 'https://api.apis.net.pe/v2'),
    ],

    'google_maps' => [
        'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
    ],

    'niubiz' => [
        'user' => env('NIUBIZ_USER'),
        'password' => env('NIUBIZ_PASSWORD'),
        'merchant_id' => env('NIUBIZ_MERCHANT_ID'),
        'env' => env('NIUBIZ_ENV', 'sandbox'),
    ],

];
