<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Theme Package Signing Key
    |--------------------------------------------------------------------------
    |
    | Configure the institution's PEM-encoded public key in THEME_PUBLIC_KEY.
    | The private signing key must never be stored on this application server
    | or in the repository. ZIP installation stays unavailable until this key
    | is provisioned. Sign the exact UTF-8 bytes of theme.json using SHA-256;
    | store the detached signature as base64 in theme.sig.
    |
    */
    'public_key' => env('THEME_PUBLIC_KEY'),
    'max_package_kb' => 10240,
    'max_expanded_kb' => 2048,
    'max_assets' => 8,
];
