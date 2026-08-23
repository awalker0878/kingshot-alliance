<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\ValueObjects;

final readonly class BearHuntEvidenceTarget
{
    public function __construct(
        public string $occurrenceId,
        public string $eventId,
        public string $allianceId,
    ) {}
}
