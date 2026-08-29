<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Services\RallyWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveRallyPlayer
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $eventAuthority,
        private EventWorkflowGuard $workflows,
        private RallyWriteState $rallyWriteState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $occurrenceId, string $groupId, string $playerId): void
    {
        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $groupId, $playerId): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->eventAuthority->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Rallies);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();
            $group = RallyGroup::query()
                ->whereKey($groupId)
                ->where('occurrence_id', $occurrence->id)
                ->sharedLock()
                ->firstOrFail();
            $alliance = $this->rallyWriteState->lockAllianceForTarget($context->target, (string) $group->alliance_id);

            $assignment = RallyAssignment::query()
                ->where('rally_group_id', $group->id)
                ->where('player_id', $playerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->statusEnum() === RallyAssignmentStatus::Removed) {
                return;
            }

            $assignment->forceFill([
                'status' => RallyAssignmentStatus::Removed,
                'slot_number' => null,
                'removed_by_player_id' => $context->actor->playerId,
                'removed_at' => now(),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => $alliance->allianceId,
                'rally_group_id' => (string) $group->id,
                'player_id' => (string) $assignment->player_id,
                'actor_player_id' => $context->actor->playerId,
            ];
            $this->audit->record('rally.assignment.removed', $context->actor, $assignment, $alliance->allianceId, $metadata);
            $this->outbox->record(
                'rally.assignment.removed',
                $alliance->allianceId,
                $assignment,
                $metadata,
                partitionKey: $context->target->partitionKey(),
            );
        });
    }
}
