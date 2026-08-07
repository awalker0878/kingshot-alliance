<?php

declare(strict_types=1);

namespace App\Domain\Content\ValueObjects;

final readonly class MediaScanResult
{
    public function __construct(
        public bool $clean,
        public ?string $reason = null,
    ) {}
}
