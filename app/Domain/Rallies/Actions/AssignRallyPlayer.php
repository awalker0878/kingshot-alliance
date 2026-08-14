<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Enums\RallyAssignmentRole;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Rallies\Models\RallyAssignment;
use App\Domain\Rallies\Models\RallyGroup;
use App\Domain\Rallies\Services\RallyPlayerEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignRallyPlayer
{
    public function __construct(
        private EventMutationAuthority $eventAuthority,
        private EventCapabilityGuard $capabilities,
        private RallyPlayerEligibility $eligibility,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        RallyGroup $group,
        Player $player,
        RallyAssignmentRole $role,
        ?int $slotNumber = null,
        ?string $notes = null,
    ): RallyAssignment {
        if ($slotNumber !== null && $slotNumber < 1) {
            throw ValidationException::withMessages(['slot_number' => 'Slot number must be at least one.']);
        }

        $group->loadMissing('occurrence.event', 'alliance');
        $event = $group->occurrence->event;

        return DB::transaction(function () use ($actor, $group, $player, $role, $slotNumber, $notes, $event): RallyAssignment {
            $context = $this->eventAuthority->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::RallyGuidance);

            // One Player may occupy only one Rally group per occurrence, so the
            // occurrence is a legitimate occurrence-wide exclusive coordination row.
            $occurrence = EventOccurrence::query()
                ->whereKey($group->occurrence_id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedGroup = RallyGroup::query()
                ->whereKey($group->id)
                ->where('occurrence_id', $occurrence->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->load('alliance');
            $alliance = $lockedGroup->alliance;

            // Player eligibility/Kingdom identity is stabilized by the Player row;
            // roster mutation workflows use the same Player anchor before roster state.
            $lockedPlayer = Player::query()
                ->whereKey($player->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->eligibility->eligible($context->event, $alliance, $lockedPlayer)) {
                throw ValidationException::withMessages(['player' => 'This Player is not eligible for this Rally Alliance.']);
            }

            $occupying = $this->occupyingStatuses();
            $conflicts = RallyAssignment::query()
                ->where('player_id', $lockedPlayer->id)
                ->whereIn('status', $occupying)
                ->where('rally_group_id', '!=', $lockedGroup->id)
                ->whereHas('rallyGroup', static fn ($query) => $query
                    ->where('occurrence_id', $occurrence->id)
                    ->where('alliance_id', $alliance->id))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $movedFrom = [];
            foreach ($conflicts as $conflict) {
                $movedFrom[] = (string) $conflict->rally_group_id;
                $conflict->forceFill([
                    'status' => RallyAssignmentStatus::Removed,
                    'slot_number' => null,
                    'removed_by_player_id' => $context->actor->id,
                    'removed_at' => now(),
                ])->save();
            }

            $assignment = RallyAssignment::query()
                ->where('rally_group_id', $lockedGroup->id)
                ->where('player_id', $lockedPlayer->id)
                ->lockForUpdate()
                ->first();
            $alreadyOccupies = $assignment instanceof RallyAssignment && $assignment->status->occupiesAssignment();

            if ($role === RallyAssignmentRole::Joiner && ! ($alreadyOccupies && $assignment->role === RallyAssignmentRole::Joiner)) {
                $joiners = RallyAssignment::query()
                    ->where('rally_group_id', $lockedGroup->id)
                    ->where('role', RallyAssignmentRole::Joiner->value)
                    ->whereIn('status', $occupying)
                    ->count();
                if ($lockedGroup->max_joiners !== null && $joiners >= (int) $lockedGroup->max_joiners) {
                    throw ValidationException::withMessages(['role' => 'This Rally group has reached its maximum joiners.']);
                }
            }

            if ($role === RallyAssignmentRole::Lead && RallyAssignment::query()
                ->where('rally_group_id', $lockedGroup->id)
                ->where('role', RallyAssignmentRole::Lead->value)
                ->whereIn('status', $occupying)
                ->when($assignment instanceof RallyAssignment, static fn ($query) => $query->where('id', '!=', $assignment->id))
                ->exists()) {
                throw ValidationException::withMessages(['role' => 'This Rally group already has an active lead.']);
            }

            if ($slotNumber !== null && RallyAssignment::query()
                ->where('rally_group_id', $lockedGroup->id)
                ->where('slot_number', $slotNumber)
                ->whereIn('status', $occupying)
                ->when($assignment instanceof RallyAssignment, static fn ($query) => $query->where('id', '!=', $assignment->id))
                ->exists()) {
                throw ValidationException::withMessages(['slot_number' => 'This Rally slot is already occupied.']);
            }

            $created = ! ($assignment instanceof RallyAssignment);
            $assignment ??= new RallyAssignment(['rally_group_id' => $lockedGroup->id, 'player_id' => $lockedPlayer->id]);
            $assignment->forceFill([
                'role' => $role,
                'slot_number' => $slotNumber,
                'status' => RallyAssignmentStatus::Assigned,
                'assigned_by_player_id' => $context->actor->id,
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
                'alliance_id' => (string) $alliance->id,
                'rally_group_id' => (string) $lockedGroup->id,
                'player_id' => (string) $lockedPlayer->id,
                'role' => $role->value,
                'slot_number' => $slotNumber,
                'moved_from_group_ids' => $movedFrom,
                'actor_player_id' => $context->actor->id,
            ];
            $this->audit->record($eventName, $context->actor, $assignment, $alliance, $metadata);
            $this->outbox->record(
                $eventName,
                (string) $alliance->id,
                $assignment,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $assignment->refresh()->load(['player', 'rallyGroup']);
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
