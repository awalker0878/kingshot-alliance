<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Actions;

use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveEventObjectiveAssignment
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventWorkflowGuard $workflows,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $occurrenceId, string $assignmentId): void
    {
        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $assignmentId): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->authorization->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::BattleAssignments);

            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id)->sharedLock()->firstOrFail();
            $assignment = EventObjectiveAssignment::query()->whereKey($assignmentId)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();
            $objective = EventObjective::query()->whereKey($assignment->objective_id)->where('occurrence_id', $occurrence->id)->sharedLock()->firstOrFail();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'objective_id' => (string) $objective->id,
                'assignment_id' => (string) $assignment->id,
                'roster_id' => $assignment->roster_id === null ? null : (string) $assignment->roster_id,
                'player_id' => $assignment->player_id === null ? null : (string) $assignment->player_id,
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record('event.objective.assignment_removed', $context->actor, $assignment, $context->target->allianceId, $metadata);
            $this->outbox->record('event.objective.assignment_removed', $context->target->allianceId, $assignment, $metadata, partitionKey: $context->target->partitionKey());
            $assignment->delete();
        });
    }
}
