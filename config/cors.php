<?php

return [
    'paths' => [
        'api/*',
        'auth/*',
        'mlm/*',
        'wallet/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://app.progressbusiness24.com'
    ],

    'allowed_origins_patterns' => [
    '#^http?://localhost(:\d+)?$#',
    '#^http?://127\.0\.0\.1(:\d+)?$#',
    '#^http?://192\.168\.\d+\.\d+(:\d+)?$#',
    '#^http?://10\.\d+\.\d+\.\d+(:\d+)?$#',
    '#^https?://([a-z0-9-]+(\.))?progressbusiness24\.com(:\d+)?$#',
],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
