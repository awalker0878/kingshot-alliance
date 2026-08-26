<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;

final readonly class GovernorProgressionEvidenceSchema
{
    /**
     * @param list<string> $supportedFields
     * @param list<string> $requiredFields
     */
    public function __construct(
        public EvidenceKind $kind,
        public string $version,
        public array $supportedFields,
        public array $requiredFields,
        public float $minimumClassificationConfidence,
        public float $minimumFieldConfidence,
        public string $fixtureCorpus,
        public string $destinationAction,
    ) {}
}
