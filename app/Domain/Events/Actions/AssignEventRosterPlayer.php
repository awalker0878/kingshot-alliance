<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventRosterMemberStatus;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRoster;
use App\Domain\Events\Models\EventRosterMember;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventRosterAllianceSnapshotResolver;
use App\Domain\Events\Services\EventRosterAvailabilityService;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignEventRosterPlayer
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventRosterAvailabilityService $availability,
        private EventRosterAllianceSnapshotResolver $allianceSnapshots,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        EventRoster $roster,
        Player $player,
        ?string $role = null,
        ?int $slotNumber = null,
        ?string $notes = null,
    ): EventRosterMember {
        $roster->loadMissing('occurrence.event.typeScope');
        $occurrence = $roster->occurrence;
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Rosters);
        $this->authorization->authorizeManager($actor, $event);
        if (! $this->authorization->eligible($event, $player)) {
            throw ValidationException::withMessages(['player' => 'This Player is not eligible for the Event target.']);
        }
        if ($slotNumber !== null && $slotNumber < 1) {
            throw ValidationException::withMessages(['slot_number' => 'Roster slot must be at least one.']);
        }
        if ($role !== null && mb_strlen(trim($role)) > 80) {
            throw ValidationException::withMessages(['role' => 'Roster role must be 80 characters or fewer.']);
        }
        if ($notes !== null && mb_strlen(trim($notes)) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Roster notes must be 10000 characters or fewer.']);
        }

        $target = $this->targets->forEvent($event);
        $allianceSnapshot = $this->allianceSnapshots->resolve($event, $player);
        $warnings = $this->availability->warnings($occurrence, $player);
        $occupying = $this->occupyingStatuses();

        return DB::transaction(function () use ($actor, $roster, $player, $role, $slotNumber, $notes, $occurrence, $event, $target,  $allianceSnapshot, $warnings, $occupying): EventRosterMember {
            EventOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            $lockedRoster = EventRoster::query()->whereKey($roster->id)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();
            if ($lockedRoster->children()->exists()) {
                throw ValidationException::withMessages(['roster' => 'Assign Players to a leaf roster, not a roster that contains child rosters.']);
            }

            $movedFrom = [];
            $conflicts = EventRosterMember::query()
                ->where('player_id', $player->id)
                ->whereIn('status', $occupying)
                ->where('roster_id', '!=', $lockedRoster->id)
                ->whereHas('roster', static fn ($query) => $query
                    ->where('occurrence_id', $occurrence->id)
                    ->where('assignment_group', $lockedRoster->assignment_group))
                ->lockForUpdate()
                ->get();
            foreach ($conflicts as $conflict) {
                $movedFrom[] = (string) $conflict->roster_id;
                $conflict->forceFill([
                    'status' => EventRosterMemberStatus::Removed,
                    'slot_number' => null,
                    'removed_by_player_id' => $actor->id,
                    'removed_at' => now(),
                ])->save();
            }

            $member = EventRosterMember::query()
                ->where('roster_id', $lockedRoster->id)
                ->where('player_id', $player->id)
                ->lockForUpdate()
                ->first();
            $alreadyOccupies = $member instanceof EventRosterMember && $member->status->occupiesSlot();
            $activeCount = EventRosterMember::query()->where('roster_id', $lockedRoster->id)->whereIn('status', $occupying)->count();
            if (! $alreadyOccupies && $lockedRoster->capacity !== null && $activeCount >= (int) $lockedRoster->capacity) {
                throw ValidationException::withMessages(['roster' => 'This roster is at capacity.']);
            }
            if ($slotNumber !== null && EventRosterMember::query()
                ->where('roster_id', $lockedRoster->id)
                ->where('slot_number', $slotNumber)
                ->whereIn('status', $occupying)
                ->when($member instanceof EventRosterMember, static fn ($query) => $query->where('id', '!=', $member->id))
                ->exists()) {
                throw ValidationException::withMessages(['slot_number' => 'This roster slot is already occupied.']);
            }

            $created = ! ($member instanceof EventRosterMember);
            $member ??= new EventRosterMember([
                'roster_id' => $lockedRoster->id,
                'player_id' => $player->id,
            ]);
            $member->forceFill([
                'alliance_id' => $allianceSnapshot,
                'role' => $role === null || trim($role) === '' ? null : trim($role),
                'slot_number' => $slotNumber,
                'status' => EventRosterMemberStatus::Assigned,
                'assignment_warnings' => $warnings,
                'assigned_by_player_id' => $actor->id,
                'assigned_at' => now(),
                'responded_by_player_id' => null,
                'responded_at' => null,
                'removed_by_player_id' => null,
                'removed_at' => null,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ])->save();

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'roster_id' => (string) $lockedRoster->id,
                'player_id' => (string) $player->id,
                'alliance_id' => $allianceSnapshot,
                'slot_number' => $slotNumber,
                'warnings' => $warnings,
                'moved_from_roster_ids' => $movedFrom,
                'actor_player_id' => $actor->id,
            ];
            $eventName = $created ? 'event.roster.player_assigned' : 'event.roster.player_reassigned';
            $this->audit->record($eventName, $actor, $member, $alliance, $metadata);
            $this->outbox->record($eventName, $alliance?->id, $member, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $member->refresh()->load(['player', 'roster']);
        });
    }

    /** @return list<string> */
    private function occupyingStatuses(): array
    {
        return array_values(array_map(
            static fn (EventRosterMemberStatus $status): string => $status->value,
            array_filter(EventRosterMemberStatus::cases(), static fn (EventRosterMemberStatus $status): bool => $status->occupiesSlot()),
        ));
    }
}
