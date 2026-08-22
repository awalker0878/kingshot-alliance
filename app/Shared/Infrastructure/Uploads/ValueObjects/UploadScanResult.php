<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Uploads\ValueObjects;

final readonly class UploadScanResult
{
    public function __construct(
        public bool $clean,
        public ?string $reason = null,
    ) {}
}
