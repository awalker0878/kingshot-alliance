<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Models\EventPlayerContext;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Services\RallyWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordRallyParticipation
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $eventAuthority,
        private EventWorkflowGuard $workflows,
        private RallyWriteState $rallyWriteState,
        private EventPlayerContextFreezer $contexts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $assignmentId,
        RallyAssignmentStatus $status,
    ): void {
        if (! in_array($status, [RallyAssignmentStatus::Participated, RallyAssignmentStatus::Absent], true)) {
            throw ValidationException::withMessages(['status' => 'Participation must be recorded as participated or absent.']);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $assignmentId, $status): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->eventAuthority->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Rallies);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $assignmentRoute = RallyAssignment::query()
                ->select(['id', 'rally_group_id'])
                ->whereKey($assignmentId)
                ->firstOrFail();
            $group = RallyGroup::query()
                ->whereKey($assignmentRoute->rally_group_id)
                ->where('occurrence_id', $occurrence->id)
                ->sharedLock()
                ->firstOrFail();
            $alliance = $this->rallyWriteState->lockAllianceForTarget($context->target, (string) $group->alliance_id);

            $assignment = RallyAssignment::query()
                ->whereKey($assignmentId)
                ->where('rally_group_id', $group->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($assignment->statusEnum(), [RallyAssignmentStatus::Declined, RallyAssignmentStatus::Removed], true)) {
                throw ValidationException::withMessages(['status' => 'Declined or removed assignments cannot receive participation.']);
            }

            $frozenContext = $this->contexts->existing((string) $occurrence->id, (string) $assignment->player_id);
            if (! $frozenContext instanceof EventPlayerContext) {
                $player = $this->rallyWriteState->lockEligiblePlayer(
                    $context->target,
                    $alliance,
                    (string) $assignment->player_id,
                );
                $this->contexts->freeze($occurrence, $player, $alliance->allianceId);
            }

            $assignment->forceFill([
                'status' => $status,
                'recorded_by_player_id' => $context->actor->playerId,
                'recorded_at' => now(),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => $alliance->allianceId,
                'rally_group_id' => (string) $group->id,
                'player_id' => (string) $assignment->player_id,
                'status' => $status->value,
                'actor_player_id' => $context->actor->playerId,
            ];
            $this->audit->record('rally.participation.recorded', $context->actor, $assignment, $alliance->allianceId, $metadata);
            $this->outbox->record(
                'rally.participation.recorded',
                $alliance->allianceId,
                $assignment,
                $metadata,
                partitionKey: $context->target->partitionKey(),
            );
        });
    }
}
