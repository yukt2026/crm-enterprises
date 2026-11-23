<?php

return [

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

    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID', 'rzp_test_default_key'),
        'secret' => env('RAZORPAY_KEY_SECRET', 'rzp_test_default_secret'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', 'rzp_webhook_secret_default'),
    ],

    'whatsapp' => [
        'app_secret' => env('META_APP_SECRET', 'meta_app_secret_default_key'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'whatsapp_crm_verify_token_default'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    'meta' => [
        'app_secret' => env('META_APP_SECRET', 'meta_app_secret_test_2026'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', 'crm_meta_webhook_secret_2026'),
        'verify_signatures' => env('META_VERIFY_SIGNATURES', false),
    ],

];
