<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignEventObjectiveTarget
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventParticipantAuthorization $participants,
        private EventWorkflowGuard $workflows,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $roster,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $objectiveId,
        ?string $rosterId = null,
        ?string $playerId = null,
        ?string $notes = null,
    ): void {
        if (($rosterId === null) === ($playerId === null)) {
            throw ValidationException::withMessages(['assignment' => 'Choose exactly one roster or Player target.']);
        }
        if ($notes !== null && mb_strlen(trim($notes)) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Assignment notes must be 10000 characters or fewer.']);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $objectiveId, $rosterId, $playerId, $notes): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->authorization->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::BattleAssignments);

            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id)->sharedLock()->firstOrFail();
            $objective = EventObjective::query()->whereKey($objectiveId)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();

            $targetRoster = $rosterId === null ? null : EventRoster::query()
                ->whereKey($rosterId)->where('occurrence_id', $occurrence->id)->sharedLock()->firstOrFail();
            $targetPlayerId = null;
            if ($playerId !== null) {
                $player = $this->players->lockCurrent($playerId);
                $activeRosterPresence = $context->target->scope === EventScope::Alliance
                    && $context->target->allianceId !== null
                    && $this->roster->lockActiveRosterPresence($context->target->allianceId, $playerId);
                if (! $this->participants->eligibleAgainstTarget($context->target, $player, $activeRosterPresence)) {
                    throw ValidationException::withMessages(['player' => 'This Player is not eligible for the Event target.']);
                }
                $targetPlayerId = $player->playerId;
            }

            $query = EventObjectiveAssignment::query()->where('objective_id', $objective->id);
            $targetRoster !== null ? $query->where('roster_id', $targetRoster->id) : $query->where('player_id', $targetPlayerId);
            $assignment = $query->lockForUpdate()->first();
            $created = ! $assignment instanceof EventObjectiveAssignment;
            $assignment ??= new EventObjectiveAssignment(['objective_id' => $objective->id, 'occurrence_id' => $occurrence->id]);
            $assignment->forceFill([
                'roster_id' => $targetRoster?->id,
                'player_id' => $targetPlayerId,
                'assigned_by_player_id' => $actorPlayerId,
                'assigned_at' => now(),
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ])->save();

            $eventName = $created ? 'event.objective.assignment_created' : 'event.objective.assignment_updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'objective_id' => (string) $objective->id,
                'assignment_id' => (string) $assignment->id,
                'roster_id' => $assignment->roster_id === null ? null : (string) $assignment->roster_id,
                'player_id' => $assignment->player_id === null ? null : (string) $assignment->player_id,
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record($eventName, $context->actor, $assignment, $context->target->allianceId, $metadata);
            $this->outbox->record($eventName, $context->target->allianceId, $assignment, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
