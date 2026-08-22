<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\TerritoryPlanning\Models\EventTerritoryPlanRevision;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DetachTerritoryPlanRevisionFromEvent
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $eventAuthorization,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $purpose = 'positioning',
    ): bool {
        if (! preg_match('/^[a-z][a-z0-9_-]{1,39}$/', $purpose)) {
            throw ValidationException::withMessages([
                'purpose' => 'Territory plan purpose is invalid.',
            ]);
        }

        return DB::transaction(function () use ($actorPlayerId, $occurrenceId, $purpose): bool {
            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->lockForUpdate()
                ->firstOrFail();
            $eventContext = $this->eventWriteState->lockEventScope(
                $actorPlayerId,
                (string) $occurrence->event_id,
            );
            $this->eventAuthorization->authorizeManager($eventContext);

            $link = EventTerritoryPlanRevision::query()
                ->where('event_occurrence_id', $occurrenceId)
                ->where('purpose', $purpose)
                ->lockForUpdate()
                ->first();
            if (! $link instanceof EventTerritoryPlanRevision) {
                return false;
            }

            $revisionId = $link->territory_plan_revision_id;
            $link->delete();
            $this->audit->record(
                'territory.plan.event_revision_detached',
                $eventContext->actor,
                $occurrence,
                $eventContext->target->allianceId,
                [
                    'territory_plan_revision_id' => $revisionId,
                    'purpose' => $purpose,
                ],
            );

            return true;
        });
    }
}
