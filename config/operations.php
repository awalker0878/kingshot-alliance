<?php

declare(strict_types=1);

return [
    'version' => env('APP_VERSION', 'dev'),
    'release_sha' => env('RELEASE_SHA', 'local'),
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),
    'allow_trust_all_proxies' => (bool) env('ALLOW_TRUST_ALL_PROXIES', false),
    'allow_insecure_loopback_staging' => (bool) env('ALLOW_INSECURE_LOOPBACK_STAGING', false),
    'outbox' => [
        'maximum_attempts' => (int) env('OUTBOX_MAXIMUM_ATTEMPTS', 10),
    ],
    'required_configuration' => [
        'app.key',
        'app.url',
        'database.connections.pgsql.host',
        'database.connections.pgsql.database',
        'database.connections.pgsql.username',
        'database.connections.pgsql.password',
    ],
    'launch' => [
        'minimum_platform_administrators' => (int) env('LAUNCH_MINIMUM_PLATFORM_ADMINISTRATORS', 2),
        'outbox_grace_minutes' => (int) env('LAUNCH_OUTBOX_GRACE_MINUTES', 15),
        'maximum_overdue_outbox' => (int) env('LAUNCH_MAXIMUM_OVERDUE_OUTBOX', 0),
        'maximum_failed_jobs' => (int) env('LAUNCH_MAXIMUM_FAILED_JOBS', 0),
        'webhook_failure_window_minutes' => (int) env('LAUNCH_WEBHOOK_FAILURE_WINDOW_MINUTES', 60),
        'maximum_recent_webhook_failures' => (int) env('LAUNCH_MAXIMUM_RECENT_WEBHOOK_FAILURES', 25),
    ],
];
