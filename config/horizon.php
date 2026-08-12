<?php

declare(strict_types=1);

use Laravel\Horizon\Http\Middleware\Authenticate;

return [
    'middleware' => [
        'web',
        Authenticate::class,
    ],

    'environments' => [
        'production' => [
            'core' => [
                'queue' => ['default', 'notifications'],
                'balance' => 'auto',
                'maxProcesses' => (int) env('HORIZON_PRODUCTION_CORE_MAX_PROCESSES', 8),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'integrations' => [
                'queue' => ['integrations'],
                'balance' => 'auto',
                'maxProcesses' => (int) env('HORIZON_PRODUCTION_INTEGRATION_MAX_PROCESSES', 4),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'kingdoms-ingestion' => [
                'queue' => ['kingdoms-ingestion'],
                'balance' => 'simple',
                'maxProcesses' => (int) env('HORIZON_PRODUCTION_KINGDOM_INGESTION_MAX_PROCESSES', 2),
            ],
            'maintenance' => [
                'queue' => ['maintenance'],
                'balance' => 'simple',
                'maxProcesses' => (int) env('HORIZON_PRODUCTION_MAINTENANCE_MAX_PROCESSES', 2),
            ],
        ],

        'staging' => [
            'core' => [
                'queue' => ['default', 'notifications'],
                'balance' => 'auto',
                'maxProcesses' => (int) env('HORIZON_STAGING_CORE_MAX_PROCESSES', 3),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'integrations' => [
                'queue' => ['integrations'],
                'balance' => 'auto',
                'maxProcesses' => (int) env('HORIZON_STAGING_INTEGRATION_MAX_PROCESSES', 2),
            ],
            'kingdoms-ingestion' => [
                'queue' => ['kingdoms-ingestion'],
                'balance' => 'simple',
                'maxProcesses' => (int) env('HORIZON_STAGING_KINGDOM_INGESTION_MAX_PROCESSES', 1),
            ],
            'maintenance' => [
                'queue' => ['maintenance'],
                'balance' => 'simple',
                'maxProcesses' => 1,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'queue' => ['default', 'notifications', 'integrations', 'kingdoms-ingestion', 'maintenance'],
                'maxProcesses' => (int) env('HORIZON_LOCAL_MAX_PROCESSES', 3),
            ],
        ],
    ],
];
