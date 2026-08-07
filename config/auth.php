<?php

declare(strict_types=1);

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => null,
        ],
    ],
    'providers' => [],
    'passwords' => [],
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
