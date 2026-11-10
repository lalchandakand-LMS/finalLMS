<?php

return [

    'bkash' => [
        'enabled' => env('BKASH_ENABLED', false),
        'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
        'username' => env('BKASH_USERNAME'),
        'password' => env('BKASH_PASSWORD'),
        'app_key' => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'verify_ssl' => env('BKASH_VERIFY_SSL', true),
    ],

    'nagad' => [
        'enabled' => env('NAGAD_ENABLED', false),
        'mode' => env('NAGAD_MODE', 'sandbox'),
        'merchant_id' => env('NAGAD_MERCHANT_ID'),
        'merchant_number' => env('NAGAD_MERCHANT_NUMBER'),
        'merchant_private_key_path' => env('NAGAD_MERCHANT_PRIVATE_KEY_PATH'),
        'gateway_public_key_path' => env('NAGAD_GATEWAY_PUBLIC_KEY_PATH'),
        'base_url' => env('NAGAD_BASE_URL'),
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

    'lms_updates' => [
        'enabled' => (bool) env('LMS_UPDATES_ENABLED', false),
        'manifest_url' => env('LMS_UPDATES_MANIFEST_URL'),
        'public_key_path' => env('LMS_UPDATES_PUBLIC_KEY_PATH'),
        'channel' => env('LMS_UPDATES_CHANNEL', 'stable'),
        'current_version' => env('LMS_VERSION', '0.0.0'),
    ],

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

];
