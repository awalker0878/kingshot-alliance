<?php

declare(strict_types=1);

$originList = (string) env('PASSKEY_ALLOWED_ORIGINS', (string) config('app.url'));

return [
    'relying_party_id' => env(
        'PASSKEY_RP_ID',
        parse_url((string) config('app.url'), PHP_URL_HOST),
    ),
    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', $originList),
    ))),
    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
    'timeout' => 60000,
    'guard' => 'web',
    'middleware' => ['web', 'throttle:passkeys'],
    'management_middleware' => ['auth', 'auth.session', 'verified', 'password.confirm'],
    'throttle' => null,
    'redirect' => '/dashboard',
];
