<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventWriteState;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\Contexts\Operations\Rosters\Services\EventRosterAllianceSnapshotResolver;
use App\Contexts\Operations\Rosters\Services\EventRosterAvailabilityService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignEventRosterPlayer
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventParticipantAuthorization $participants,
        private EventCapabilityGuard $capabilities,
        private EventRosterAvailabilityService $availability,
        private EventRosterAllianceSnapshotResolver $allianceSnapshots,
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
        $roster->loadMissing('occurrence.event');
        $occurrence = $roster->occurrence;
        $event = $occurrence->event;

        if ($slotNumber !== null && $slotNumber < 1) {
            throw ValidationException::withMessages(['slot_number' => 'Roster slot must be at least one.']);
        }
        if ($role !== null && mb_strlen(trim($role)) > 80) {
            throw ValidationException::withMessages(['role' => 'Roster role must be 80 characters or fewer.']);
        }
        if ($notes !== null && mb_strlen(trim($notes)) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Roster notes must be 10000 characters or fewer.']);
        }

        $occupying = $this->occupyingStatuses();

        return DB::transaction(function () use ($actor, $roster, $player, $role, $slotNumber, $notes, $occurrence, $event, $occupying): EventRosterMember {
            $context = $this->eventWriteState->lockEventScope($actor, $event);
            $this->mutations->authorizeManager($context);
            $this->capabilities->require($context->event, EventCapability::Rosters);

            // Event Roster placement is occurrence-wide because one Player may occupy
            // only one roster in an assignment group and capacity/slot checks span rows.
            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRoster = EventRoster::query()
                ->whereKey($roster->id)
                ->where('occurrence_id', $lockedOccurrence->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRoster->children()->exists()) {
                throw ValidationException::withMessages([
                    'roster' => 'Assign Players to a leaf roster, not a roster that contains child rosters.',
                ]);
            }

            $currentPlayer = Player::query()
                ->whereKey($player->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (! $this->participants->eligible($context->event, $currentPlayer)) {
                throw ValidationException::withMessages([
                    'player' => 'This Player is not eligible for the Event target.',
                ]);
            }

            $allianceSnapshot = $this->allianceSnapshots->resolve($context->event, $currentPlayer);
            $warnings = $this->availability->warnings($lockedOccurrence, $currentPlayer);

            $movedFrom = [];
            $conflicts = EventRosterMember::query()
                ->where('player_id', $currentPlayer->id)
                ->whereIn('status', $occupying)
                ->where('roster_id', '!=', $lockedRoster->id)
                ->whereHas('roster', static fn ($query) => $query
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->where('assignment_group', $lockedRoster->assignment_group))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($conflicts as $conflict) {
                $movedFrom[] = (string) $conflict->roster_id;
                $conflict->forceFill([
                    'status' => EventRosterMemberStatus::Removed,
                    'slot_number' => null,
                    'removed_by_player_id' => $context->actor->id,
                    'removed_at' => now(),
                ])->save();
            }

            $member = EventRosterMember::query()
                ->where('roster_id', $lockedRoster->id)
                ->where('player_id', $currentPlayer->id)
                ->lockForUpdate()
                ->first();
            $memberId = $member instanceof EventRosterMember ? $member->id : null;
            $alreadyOccupies = $member instanceof EventRosterMember && $member->status->occupiesSlot();
            $activeCount = EventRosterMember::query()
                ->where('roster_id', $lockedRoster->id)
                ->whereIn('status', $occupying)
                ->count();
            if (! $alreadyOccupies && $lockedRoster->capacity !== null && $activeCount >= (int) $lockedRoster->capacity) {
                throw ValidationException::withMessages(['roster' => 'This roster is at capacity.']);
            }
            if ($slotNumber !== null && EventRosterMember::query()
                ->where('roster_id', $lockedRoster->id)
                ->where('slot_number', $slotNumber)
                ->whereIn('status', $occupying)
                ->when($memberId !== null, static fn ($query) => $query->where('id', '!=', $memberId))
                ->exists()) {
                throw ValidationException::withMessages(['slot_number' => 'This roster slot is already occupied.']);
            }

            $created = ! ($member instanceof EventRosterMember);
            $member ??= new EventRosterMember([
                'roster_id' => $lockedRoster->id,
                'player_id' => $currentPlayer->id,
            ]);
            $member->forceFill([
                'alliance_id' => $allianceSnapshot,
                'role' => $role === null || trim($role) === '' ? null : trim($role),
                'slot_number' => $slotNumber,
                'status' => EventRosterMemberStatus::Assigned,
                'assignment_warnings' => $warnings,
                'assigned_by_player_id' => $context->actor->id,
                'assigned_at' => now(),
                'responded_by_player_id' => null,
                'responded_at' => null,
                'removed_by_player_id' => null,
                'removed_at' => null,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ])->save();

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'roster_id' => (string) $lockedRoster->id,
                'player_id' => (string) $currentPlayer->id,
                'alliance_id' => $allianceSnapshot,
                'slot_number' => $slotNumber,
                'warnings' => $warnings,
                'moved_from_roster_ids' => $movedFrom,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $eventName = $created ? 'event.roster.player_assigned' : 'event.roster.player_reassigned';
            $this->audit->record($eventName, $context->actor, $member, $alliance, $metadata);
            $this->outbox->record(
                $eventName,
                $alliance?->id,
                $member,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

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
