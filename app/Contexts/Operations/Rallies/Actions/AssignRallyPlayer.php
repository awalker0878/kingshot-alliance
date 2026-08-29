<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Services\RallyWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignRallyPlayer
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $eventAuthority,
        private EventWorkflowGuard $workflows,
        private RallyWriteState $rallyWriteState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $groupId,
        string $playerId,
        RallyAssignmentRole $role,
        ?int $slotNumber = null,
        ?string $notes = null,
    ): void {
        if ($slotNumber !== null && $slotNumber < 1) {
            throw ValidationException::withMessages(['slot_number' => 'Slot number must be at least one.']);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $groupId, $playerId, $role, $slotNumber, $notes): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->eventAuthority->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Rallies);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $group = RallyGroup::query()
                ->whereKey($groupId)
                ->where('occurrence_id', $occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();
            $alliance = $this->rallyWriteState->lockAllianceForTarget($context->target, (string) $group->alliance_id);
            $player = $this->rallyWriteState->lockEligiblePlayer($context->target, $alliance, $playerId);

            $occupying = $this->occupyingStatuses();
            $conflicts = RallyAssignment::query()
                ->where('player_id', $player->playerId)
                ->whereIn('status', $occupying)
                ->where('rally_group_id', '!=', $group->id)
                ->whereHas('rallyGroup', static fn ($query) => $query
                    ->where('occurrence_id', $occurrence->id)
                    ->where('alliance_id', $alliance->allianceId))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $movedFrom = [];
            foreach ($conflicts as $conflict) {
                $movedFrom[] = (string) $conflict->rally_group_id;
                $conflict->forceFill([
                    'status' => RallyAssignmentStatus::Removed,
                    'slot_number' => null,
                    'removed_by_player_id' => $context->actor->playerId,
                    'removed_at' => now(),
                ])->save();
            }

            $assignment = RallyAssignment::query()
                ->where('rally_group_id', $group->id)
                ->where('player_id', $player->playerId)
                ->lockForUpdate()
                ->first();
            $assignmentId = $assignment instanceof RallyAssignment ? (string) $assignment->id : null;
            $alreadyOccupies = $assignment instanceof RallyAssignment && $assignment->statusEnum()->occupiesAssignment();

            if ($role === RallyAssignmentRole::Joiner
                && ! ($alreadyOccupies && $assignment instanceof RallyAssignment && $assignment->roleEnum() === RallyAssignmentRole::Joiner)) {
                $joiners = RallyAssignment::query()
                    ->where('rally_group_id', $group->id)
                    ->where('role', RallyAssignmentRole::Joiner->value)
                    ->whereIn('status', $occupying)
                    ->count();
                if ($group->max_joiners !== null && $joiners >= (int) $group->max_joiners) {
                    throw ValidationException::withMessages(['role' => 'This Rally group has reached its maximum joiners.']);
                }
            }

            if ($role === RallyAssignmentRole::Lead && RallyAssignment::query()
                ->where('rally_group_id', $group->id)
                ->where('role', RallyAssignmentRole::Lead->value)
                ->whereIn('status', $occupying)
                ->when($assignmentId !== null, static fn ($query) => $query->where('id', '!=', $assignmentId))
                ->exists()) {
                throw ValidationException::withMessages(['role' => 'This Rally group already has an active lead.']);
            }

            if ($slotNumber !== null && RallyAssignment::query()
                ->where('rally_group_id', $group->id)
                ->where('slot_number', $slotNumber)
                ->whereIn('status', $occupying)
                ->when($assignmentId !== null, static fn ($query) => $query->where('id', '!=', $assignmentId))
                ->exists()) {
                throw ValidationException::withMessages(['slot_number' => 'This Rally slot is already occupied.']);
            }

            $created = ! ($assignment instanceof RallyAssignment);
            $assignment ??= new RallyAssignment([
                'rally_group_id' => $group->id,
                'player_id' => $player->playerId,
            ]);
            $assignment->forceFill([
                'role' => $role,
                'slot_number' => $slotNumber,
                'status' => RallyAssignmentStatus::Assigned,
                'assigned_by_player_id' => $context->actor->playerId,
                'assigned_at' => now(),
                'responded_by_player_id' => null,
                'responded_at' => null,
                'recorded_by_player_id' => null,
                'recorded_at' => null,
                'removed_by_player_id' => null,
                'removed_at' => null,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ])->save();

            $eventName = $created ? 'rally.assignment.created' : 'rally.assignment.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => $alliance->allianceId,
                'rally_group_id' => (string) $group->id,
                'player_id' => $player->playerId,
                'role' => $role->value,
                'slot_number' => $slotNumber,
                'moved_from_group_ids' => $movedFrom,
                'actor_player_id' => $context->actor->playerId,
            ];
            $this->audit->record($eventName, $context->actor, $assignment, $alliance->allianceId, $metadata);
            $this->outbox->record(
                $eventName,
                $alliance->allianceId,
                $assignment,
                $metadata,
                partitionKey: $context->target->partitionKey(),
            );
        });
    }

    /** @return list<string> */
    private function occupyingStatuses(): array
    {
        return array_map(
            static fn (RallyAssignmentStatus $status): string => $status->value,
            array_values(array_filter(
                RallyAssignmentStatus::cases(),
                static fn (RallyAssignmentStatus $status): bool => $status->occupiesAssignment(),
            )),
        );
    }
}
