<?php

declare(strict_types=1);

$applicationUrl = rtrim((string) env('APP_URL', ''), '/');
$pushHosts = array_values(array_filter(array_map(
    static fn (string $host): string => trim($host),
    explode(',', (string) env('WEB_PUSH_ALLOWED_HOSTS', '')),
)));

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env(
            'GOOGLE_REDIRECT_URI',
            $applicationUrl === '' ? null : $applicationUrl.'/auth/google/callback',
        ),
    ],

    'webpush' => [
        'public_key' => env('WEB_PUSH_VAPID_PUBLIC_KEY'),
        'private_key' => env('WEB_PUSH_VAPID_PRIVATE_KEY'),
        'subject' => env('WEB_PUSH_VAPID_SUBJECT'),
        'allowed_hosts' => $pushHosts,
    ],
];
