<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;

final readonly class TerritoryEvidenceSchema
{
    /** @param list<string> $supportedFields */
    public function __construct(
        public EvidenceKind $kind,
        public string $version,
        public array $supportedFields,
        public float $minimumClassificationConfidence,
        public float $minimumFieldConfidence,
        public string $fixtureCorpus,
        public string $destinationAction,
    ) {}
}
