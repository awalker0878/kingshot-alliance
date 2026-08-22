<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

final readonly class EvidenceUploadResult
{
    public function __construct(
        public string $evidenceId,
        public bool $duplicate,
    ) {}
}
