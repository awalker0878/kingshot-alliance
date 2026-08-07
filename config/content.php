<?php

declare(strict_types=1);

return [
    'media_disk' => env('CONTENT_MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
    'media_max_kilobytes' => (int) env('CONTENT_MEDIA_MAX_KB', 8192),
    'media_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
    ],
    'public_search_limit' => 50,
    'member_search_limit' => 100,
];
