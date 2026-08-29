<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
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
        private EventWorkflowGuard $workflows,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $rosterEntries,
        private EventRosterAvailabilityService $availability,
        private EventRosterAllianceSnapshotResolver $allianceSnapshots,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $rosterId,
        string $playerId,
        ?string $role = null,
        ?int $slotNumber = null,
        ?string $notes = null,
    ): void {
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
        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $rosterId, $playerId, $role, $slotNumber, $notes, $occupying): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->mutations->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Roster);

            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id)->lockForUpdate()->firstOrFail();
            $roster = EventRoster::query()->whereKey($rosterId)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();
            if ($roster->children()->exists()) {
                throw ValidationException::withMessages(['roster' => 'Assign Players to a leaf roster, not a roster that contains child rosters.']);
            }

            $player = $this->players->lockCurrent($playerId);
            $activeRosterPresence = $context->target->scope === EventScope::Alliance
                && $context->target->allianceId !== null
                && $this->rosterEntries->lockActiveRosterPresence($context->target->allianceId, $playerId);
            if (! $this->participants->eligibleAgainstTarget($context->target, $player, $activeRosterPresence)) {
                throw ValidationException::withMessages(['player' => 'This Player is not eligible for the Event target.']);
            }

            $allianceId = $this->allianceSnapshots->resolve($context->target, $player);
            $warnings = $this->availability->warnings($occurrence, $player);
            $movedFrom = [];
            $conflicts = EventRosterMember::query()
                ->where('player_id', $player->playerId)
                ->whereIn('status', $occupying)
                ->where('roster_id', '!=', $roster->id)
                ->whereHas('roster', static fn ($query) => $query
                    ->where('occurrence_id', $occurrence->id)
                    ->where('assignment_group', $roster->assignment_group))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($conflicts as $conflict) {
                $movedFrom[] = (string) $conflict->roster_id;
                $conflict->forceFill([
                    'status' => EventRosterMemberStatus::Removed,
                    'slot_number' => null,
                    'removed_by_player_id' => $actorPlayerId,
                    'removed_at' => now(),
                ])->save();
            }

            $member = EventRosterMember::query()->where('roster_id', $roster->id)->where('player_id', $player->playerId)->lockForUpdate()->first();
            $memberId = $member instanceof EventRosterMember ? $member->id : null;
            $alreadyOccupies = $member instanceof EventRosterMember && $member->statusEnum()->occupiesSlot();
            $activeCount = EventRosterMember::query()->where('roster_id', $roster->id)->whereIn('status', $occupying)->count();
            if (! $alreadyOccupies && $roster->capacity !== null && $activeCount >= (int) $roster->capacity) {
                throw ValidationException::withMessages(['roster' => 'This roster is at capacity.']);
            }
            if ($slotNumber !== null && EventRosterMember::query()
                ->where('roster_id', $roster->id)->where('slot_number', $slotNumber)->whereIn('status', $occupying)
                ->when($memberId !== null, static fn ($query) => $query->where('id', '!=', $memberId))->exists()) {
                throw ValidationException::withMessages(['slot_number' => 'This roster slot is already occupied.']);
            }

            $created = ! $member instanceof EventRosterMember;
            $member ??= new EventRosterMember(['roster_id' => $roster->id, 'player_id' => $player->playerId]);
            $member->forceFill([
                'alliance_id' => $allianceId,
                'role' => $role === null || trim($role) === '' ? null : trim($role),
                'slot_number' => $slotNumber,
                'status' => EventRosterMemberStatus::Assigned,
                'assignment_warnings' => $warnings,
                'assigned_by_player_id' => $actorPlayerId,
                'assigned_at' => now(),
                'responded_by_player_id' => null,
                'responded_at' => null,
                'removed_by_player_id' => null,
                'removed_at' => null,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'roster_id' => (string) $roster->id,
                'player_id' => $player->playerId,
                'alliance_id' => $allianceId,
                'slot_number' => $slotNumber,
                'warnings' => $warnings,
                'moved_from_roster_ids' => $movedFrom,
                'actor_player_id' => $actorPlayerId,
            ];
            $eventName = $created ? 'event.roster.player_assigned' : 'event.roster.player_reassigned';
            $this->audit->record($eventName, $context->actor, $member, $context->target->allianceId, $metadata);
            $this->outbox->record($eventName, $context->target->allianceId, $member, $metadata, partitionKey: $context->target->partitionKey());
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
