<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\ValueObjects;

/** Counts are invocation-local; they are not a claim that the live audience is exhausted. */
final readonly class KingPerkReminderSweepResult
{
    public function __construct(
        public int $workUnits,
        public int $sourcesExamined,
        public int $recipientsExamined,
        public int $queued,
        public int $supersededPages,
        public int $expiredCursorsRemoved,
    ) {}
}
