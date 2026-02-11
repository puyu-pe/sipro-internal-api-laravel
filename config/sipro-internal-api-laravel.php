<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('SIPRO_INTERNAL_API_LARAVEL_ENABLED', true),

    'hmac' => [
        'allowed_clock_skew_seconds' => (int) env('SIPRO_INTERNAL_API_HMAC_ALLOWED_CLOCK_SKEW_SECONDS', 300),
        'keys' => (array) env('SIPRO_INTERNAL_API_HMAC_KEYS', []),
        'nonce' => [
            'enabled' => (bool) env('SIPRO_INTERNAL_API_HMAC_NONCE_ENABLED', true),
            'cache_store' => env('SIPRO_INTERNAL_API_HMAC_NONCE_CACHE_STORE'),
            'cache_prefix' => (string) env('SIPRO_INTERNAL_API_HMAC_NONCE_CACHE_PREFIX', 'sipro_internal_nonce:'),
        ],
    ],

    'adapter' => [
        'class' => env('SIPRO_INTERNAL_API_ADAPTER_CLASS'),
    ],
];
