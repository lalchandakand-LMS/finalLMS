<?php

return [

    'ai_developer' => [
        'provider' => env('AI_DEVELOPER_PROVIDER', 'openai-compatible'),
        'base_url' => env('AI_DEVELOPER_BASE_URL', 'https://api.openai.com/v1'),
        'allowed_hosts' => array_filter(array_map('trim', explode(',', env('AI_DEVELOPER_ALLOWED_HOSTS', 'api.openai.com')))),
        'api_key' => env('AI_DEVELOPER_API_KEY'),
        'model' => env('AI_DEVELOPER_MODEL'),
        'max_tokens' => env('AI_DEVELOPER_MAX_TOKENS', 1200),
        'repository_context_enabled' => env('AI_DEVELOPER_REPOSITORY_CONTEXT_ENABLED', false),
        'github_token' => env('AI_DEVELOPER_GITHUB_TOKEN'),
        'github_repository' => env('AI_DEVELOPER_GITHUB_REPOSITORY'),
        'github_read_ref' => env('AI_DEVELOPER_GITHUB_READ_REF', 'main'),
        'allowed_repository_paths' => array_filter(array_map('trim', explode(',', env('AI_DEVELOPER_ALLOWED_REPOSITORY_PATHS', '')))),
        'repository_max_file_bytes' => env('AI_DEVELOPER_REPOSITORY_MAX_FILE_BYTES', 12000),
        'repository_max_total_bytes' => env('AI_DEVELOPER_REPOSITORY_MAX_TOTAL_BYTES', 40000),
    ],

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

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/admin/storage/google/callback'),
    ],

    'lms_updates' => [
        'enabled' => (bool) env('LMS_UPDATES_ENABLED', false),
        'manifest_url' => env('LMS_UPDATES_MANIFEST_URL'),
        'public_key_path' => env('LMS_UPDATES_PUBLIC_KEY_PATH', storage_path('app/secure/release-public.pem')),
        'channel' => env('LMS_UPDATES_CHANNEL', 'stable'),
        'current_version' => env('LMS_VERSION') ?: (is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : '0.0.0'),
        'max_package_bytes' => (int) env('LMS_UPDATE_MAX_PACKAGE_BYTES', 536870912),
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
