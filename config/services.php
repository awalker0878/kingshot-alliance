<?php

declare(strict_types=1);

$applicationUrl = rtrim((string) env('APP_URL', ''), '/');

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env(
            'GOOGLE_REDIRECT_URI',
            $applicationUrl === '' ? null : $applicationUrl.'/auth/google/callback',
        ),
    ],
];
