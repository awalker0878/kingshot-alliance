<?php

declare(strict_types=1);

return [
    'disk' => env('EVIDENCE_DISK', env('FILESYSTEM_DISK', 'local')),
    'max_kilobytes' => (int) env('EVIDENCE_MAX_KB', 12288),
    'mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
    'retention' => [
        'rejected_days' => (int) env('EVIDENCE_REJECTED_DAYS', 14),
        'failed_days' => (int) env('EVIDENCE_FAILED_DAYS', 30),
        'uncommitted_days' => (int) env('EVIDENCE_UNCOMMITTED_DAYS', 90),
        'committed_binary_days' => (int) env('EVIDENCE_COMMITTED_BINARY_DAYS', 180),
    ],
];
