<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

final readonly class EvidenceCommitReceipt
{
    public function __construct(
        public string $reportId,
        public int $entryCount,
        public bool $idempotentReplay,
    ) {}
}
