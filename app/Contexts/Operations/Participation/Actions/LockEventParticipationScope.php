<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Actions;

use App\Contexts\Operations\Participation\Services\EventParticipationWriteState;

/** Compose the existing owner lock order before acquiring external integration authority. */
final readonly class LockEventParticipationScope
{
    public function __construct(private EventParticipationWriteState $state) {}

    public function handle(string $actorId, string $occurrenceId, bool $exclusiveOccurrence): void
    {
        // The caller owns the encompassing transaction; no Operations models cross this boundary.
        $this->state->lock($actorId, $occurrenceId, $exclusiveOccurrence);
    }
}
