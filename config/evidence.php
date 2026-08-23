<?php

declare(strict_types=1);

return [
    'disk' => env('EVIDENCE_DISK', env('FILESYSTEM_DISK', 'local')),
    'max_kilobytes' => (int) env('EVIDENCE_MAX_KB', 12288),
    'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'visual_duplicate_distance' => (int) env('EVIDENCE_VISUAL_DUPLICATE_DISTANCE', 8),
    'ocr' => [
        'binary' => env('EVIDENCE_OCR_BINARY', 'tesseract'),
        'language' => env('EVIDENCE_OCR_LANGUAGE', 'eng'),
        'page_segmentation_mode' => (int) env('EVIDENCE_OCR_PSM', 6),
    ],
    'retention' => [
        'deleted_days' => (int) env('EVIDENCE_DELETED_RETENTION_DAYS', 14),
        'failed_days' => (int) env('EVIDENCE_FAILED_RETENTION_DAYS', 30),
        'uncommitted_days' => (int) env('EVIDENCE_UNCOMMITTED_RETENTION_DAYS', 90),
        'committed_binary_days' => (int) env('EVIDENCE_COMMITTED_BINARY_RETENTION_DAYS', 180),
    ],
];
