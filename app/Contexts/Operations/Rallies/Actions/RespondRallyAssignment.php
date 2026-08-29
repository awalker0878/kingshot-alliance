<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Services\RallyWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RespondRallyAssignment
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
        if (! in_array($status, [RallyAssignmentStatus::Confirmed, RallyAssignmentStatus::Declined], true)) {
            throw ValidationException::withMessages(['status' => 'Rally assignment response must be confirmed or declined.']);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $assignmentId, $status): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockSelfScope($actorPlayerId, (string) $route->event_id, $actorPlayerId);
            $this->eventAuthority->authorizeSelf($context, $actorPlayerId);
            $this->workflows->require($context->event, EventWorkflowDimension::Rallies);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $assignmentRoute = RallyAssignment::query()
                ->select(['id', 'rally_group_id', 'player_id'])
                ->whereKey($assignmentId)
                ->firstOrFail();
            if ((string) $assignmentRoute->player_id !== $actorPlayerId) {
                throw new AuthorizationException;
            }

            $group = RallyGroup::query()
                ->whereKey($assignmentRoute->rally_group_id)
                ->where('occurrence_id', $occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();
            $alliance = $this->rallyWriteState->lockAllianceForTarget($context->target, (string) $group->alliance_id);
            $this->rallyWriteState->lockEligiblePlayer($context->target, $alliance, $actorPlayerId);

            $assignment = RallyAssignment::query()
                ->whereKey($assignmentId)
                ->where('rally_group_id', $group->id)
                ->where('player_id', $actorPlayerId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($assignment->statusEnum(), [RallyAssignmentStatus::Removed, RallyAssignmentStatus::Participated, RallyAssignmentStatus::Absent], true)) {
                throw ValidationException::withMessages(['status' => 'This Rally assignment can no longer be confirmed or declined.']);
            }

            if ($status === RallyAssignmentStatus::Confirmed && ! $assignment->statusEnum()->occupiesAssignment()) {
                $occupying = $this->occupyingStatuses();

                if ($assignment->roleEnum() === RallyAssignmentRole::Joiner) {
                    $joiners = RallyAssignment::query()
                        ->where('rally_group_id', $group->id)
                        ->where('role', RallyAssignmentRole::Joiner->value)
                        ->whereIn('status', $occupying)
                        ->where('id', '!=', $assignment->id)
                        ->count();
                    if ($group->max_joiners !== null && $joiners >= (int) $group->max_joiners) {
                        throw ValidationException::withMessages(['status' => 'This Rally group has reached its maximum joiners.']);
                    }
                }

                if ($assignment->roleEnum() === RallyAssignmentRole::Lead && RallyAssignment::query()
                    ->where('rally_group_id', $group->id)
                    ->where('role', RallyAssignmentRole::Lead->value)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $assignment->id)
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'The Rally lead slot has been reassigned.']);
                }

                if ($assignment->slot_number !== null && RallyAssignment::query()
                    ->where('rally_group_id', $group->id)
                    ->where('slot_number', $assignment->slot_number)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $assignment->id)
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'This Rally slot has been reassigned.']);
                }

                if (RallyAssignment::query()
                    ->where('player_id', $actorPlayerId)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $assignment->id)
                    ->whereHas('rallyGroup', static fn ($query) => $query
                        ->where('occurrence_id', $occurrence->id)
                        ->where('alliance_id', $alliance->allianceId))
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'This Player has another active Rally assignment for the same Alliance.']);
                }

                $this->contexts->freeze($occurrence, $context->actor, $alliance->allianceId);
            }

            $assignment->forceFill([
                'status' => $status,
                'responded_by_player_id' => $context->actor->playerId,
                'responded_at' => now(),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => $alliance->allianceId,
                'rally_group_id' => (string) $group->id,
                'player_id' => $context->actor->playerId,
                'status' => $status->value,
            ];
            $this->audit->record('rally.assignment.responded', $context->actor, $assignment, $alliance->allianceId, $metadata);
            $this->outbox->record(
                'rally.assignment.responded',
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
