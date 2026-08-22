<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;

final readonly class ClassificationDecision
{
    public function __construct(
        public EvidenceKind $kind,
        public float $confidence,
        public string $reason,
    ) {}
}
