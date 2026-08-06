<?php

declare(strict_types=1);

return [
    'version' => env('APP_VERSION', 'dev'),
    'release_sha' => env('RELEASE_SHA', 'local'),
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),
    'allow_trust_all_proxies' => (bool) env('ALLOW_TRUST_ALL_PROXIES', false),
    'required_configuration' => [
        'app.key',
        'app.url',
        'database.connections.pgsql.host',
        'database.connections.pgsql.database',
        'database.connections.pgsql.username',
        'database.connections.pgsql.password',
    ],
];
