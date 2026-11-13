<?php

return [
    'local_quota_bytes' => (int) env('LMS_LOCAL_STORAGE_QUOTA_BYTES', 10 * 1024 * 1024 * 1024),
    'max_upload_kb' => (int) env('LMS_MAX_UPLOAD_KB', 51200),
    'warning_thresholds' => [70, 85, 95],
    'cpanel' => [
        'host' => env('CPANEL_API_HOST'),
        'username' => env('CPANEL_API_USERNAME'),
        'api_token' => env('CPANEL_API_TOKEN'),
    ],
];
