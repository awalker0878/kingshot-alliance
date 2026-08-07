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
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_PRODUCTION_MAX_PROCESSES', 10),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'staging' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_STAGING_MAX_PROCESSES', 3),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_LOCAL_MAX_PROCESSES', 3),
            ],
        ],
    ],
];
