<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [
    'routes' => false,

    'stateful' => array_values(array_filter(array_map(
        static fn (string $domain): string => trim($domain),
        explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
            'localhost,localhost:8080,127.0.0.1,127.0.0.1:8080%s',
            Sanctum::currentApplicationUrlWithPort(),
        ))),
    ))),

    'guard' => ['web'],
    'expiration' => null,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'last_used_at' => true,

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
