<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Queries;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Contexts\Operations\Participation\Queries\EventEligiblePlayerQuery;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\Contexts\Operations\Rosters\Services\EventRosterAvailabilityService;

final readonly class EventRosterQuery
{
    public function __construct(
        private EventEligiblePlayerQuery $eligiblePlayers,
        private PlayerReferenceQuery $players,
        private EventRosterAvailabilityService $availability,
    ) {}

    /** @return list<array<string,mixed>> */
    public function forPlayer(EventOccurrence $occurrence, PlayerReference $player): array
    {
        return array_values(EventRosterMember::query()
            ->where('player_id', $player->playerId)
            ->where('status', '!=', EventRosterMemberStatus::Removed->value)
            ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
            ->with(['roster.parent'])
            ->get()
            ->map(fn (EventRosterMember $member): array => [
                'id' => (string) $member->id,
                'rosterId' => (string) $member->roster_id,
                'rosterKey' => (string) $member->roster->key,
                'rosterNameKey' => $member->roster->name_key,
                'rosterName' => $member->roster->name,
                'parentNameKey' => $member->roster->parent?->name_key,
                'parentName' => $member->roster->parent?->name,
                'type' => $member->roster->roster_type->value,
                'role' => $member->role,
                'slotNumber' => $member->slot_number,
                'status' => $member->status->value,
                'warnings' => $this->availability->warnings($occurrence, $player),
                'notes' => $member->notes,
                'respondedAt' => $member->responded_at?->toIso8601String(),
            ])->all());
    }

    /**
     * Bounded owner summary consumed by EventManagement Event Command composition.
     *
     * @return array{
     *   rosterCount:int,
     *   capacityRosterCount:int,
     *   requiredSlots:int,
     *   filledSlots:int,
     *   unfilledSlots:int,
     *   activeMemberCount:int,
     *   unassignedCount:int,
     *   declinedCount:int,
     *   removedCount:int,
     *   warningCount:int
     * }
     */
    public function commandSummary(EventOccurrence $occurrence): array
    {
        $rosters = EventRoster::query()
            ->where('occurrence_id', $occurrence->id)
            ->with('members')
            ->get();
        $requiredSlots = 0;
        $filledSlots = 0;
        $capacityRosterCount = 0;
        $activeMemberCount = 0;
        $unassignedCount = 0;
        $declinedCount = 0;
        $removedCount = 0;
        $warningCount = 0;

        foreach ($rosters as $roster) {
            if (! $roster instanceof EventRoster) {
                continue;
            }

            $occupying = $roster->members->filter(
                static fn (EventRosterMember $member): bool => $member->status->occupiesSlot(),
            );
            $activeMemberCount += $occupying->count();
            $unassignedCount += $occupying
                ->filter(static fn (EventRosterMember $member): bool => $member->slot_number === null)
                ->count();
            $declinedCount += $roster->members
                ->filter(static fn (EventRosterMember $member): bool => $member->status === EventRosterMemberStatus::Declined)
                ->count();
            $removedCount += $roster->members
                ->filter(static fn (EventRosterMember $member): bool => $member->status === EventRosterMemberStatus::Removed)
                ->count();
            $warningCount += $roster->members->sum(static function (EventRosterMember $member): int {
                $warnings = $member->assignment_warnings;

                return is_array($warnings) ? count($warnings) : 0;
            });

            if ($roster->capacity !== null) {
                $capacityRosterCount++;
                $requiredSlots += max(0, (int) $roster->capacity);
                $filledSlots += min(max(0, (int) $roster->capacity), $occupying->count());
            }
        }

        return [
            'rosterCount' => $rosters->count(),
            'capacityRosterCount' => $capacityRosterCount,
            'requiredSlots' => $requiredSlots,
            'filledSlots' => $filledSlots,
            'unfilledSlots' => max(0, $requiredSlots - $filledSlots),
            'activeMemberCount' => $activeMemberCount,
            'unassignedCount' => $unassignedCount,
            'declinedCount' => $declinedCount,
            'removedCount' => $removedCount,
            'warningCount' => $warningCount,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        $eligible = $this->eligiblePlayers->for($event)->keyBy(static fn (PlayerReference $player): string => $player->playerId);

        return array_values($event->occurrences
            ->sortBy('starts_at')
            ->values()
            ->map(function (EventOccurrence $occurrence) use ($eligible): array {
                $rosters = EventRoster::query()
                    ->where('occurrence_id', $occurrence->id)
                    ->with(['members', 'parent'])
                    ->orderBy('sort_order')->orderBy('key')->get();
                $memberPlayerIds = $rosters->flatMap(static fn (EventRoster $roster) => $roster->members->pluck('player_id'))
                    ->map(static fn ($id): string => (string) $id)->all();
                $memberPlayers = $this->players->byIds($memberPlayerIds);
                $responses = EventResponse::query()->where('occurrence_id', $occurrence->id)->get()->keyBy('player_id');
                $registrations = EventRegistration::query()->where('occurrence_id', $occurrence->id)->get()->keyBy('player_id');

                return [
                    'occurrenceId' => (string) $occurrence->id,
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                    'rosters' => $rosters->map(fn (EventRoster $roster): array => $this->rosterPayload($occurrence, $roster, $memberPlayers))->all(),
                    'candidates' => $eligible->values()->map(function (PlayerReference $player) use ($occurrence, $responses, $registrations): array {
                        $response = $responses->get($player->playerId);
                        $registration = $registrations->get($player->playerId);

                        return [
                            'playerId' => $player->playerId,
                            'name' => $player->currentName,
                            'claimed' => $player->claimed(),
                            'response' => $response?->response?->value,
                            'preferredRole' => $response?->preferred_role,
                            'preferredTeam' => $response?->preferred_team,
                            'availableFrom' => $response?->available_from?->toIso8601String(),
                            'availableUntil' => $response?->available_until?->toIso8601String(),
                            'registration' => $registration?->status?->value,
                            'waitlistPosition' => $registration?->status === EventRegistrationStatus::Waitlisted ? $registration->waitlist_position : null,
                            'warnings' => $this->availability->warnings($occurrence, $player),
                        ];
                    })->all(),
                ];
            })->all());
    }

    /**
     * @param  array<string, PlayerReference>  $players
     * @return array<string, mixed>
     */
    private function rosterPayload(EventOccurrence $occurrence, EventRoster $roster, array $players): array
    {
        $members = $roster->members->map(function (EventRosterMember $member) use ($occurrence, $players): array {
            $player = $players[(string) $member->player_id] ?? null;

            return [
                'id' => (string) $member->id,
                'playerId' => (string) $member->player_id,
                'playerName' => $player->currentName ?? 'Unknown Player',
                'allianceId' => $member->alliance_id === null ? null : (string) $member->alliance_id,
                'role' => $member->role,
                'slotNumber' => $member->slot_number,
                'status' => $member->status->value,
                'assignmentWarnings' => $member->assignment_warnings ?? [],
                'warnings' => $player instanceof PlayerReference ? $this->availability->warnings($occurrence, $player) : [],
                'assignedAt' => $member->assigned_at?->toIso8601String(),
                'respondedAt' => $member->responded_at?->toIso8601String(),
                'notes' => $member->notes,
            ];
        });

        return [
            'id' => (string) $roster->id,
            'parentId' => $roster->parent_id === null ? null : (string) $roster->parent_id,
            'key' => (string) $roster->key,
            'nameKey' => $roster->name_key,
            'name' => $roster->name,
            'type' => $roster->roster_type->value,
            'assignmentGroup' => (string) $roster->assignment_group,
            'capacity' => $roster->capacity,
            'activeCount' => $members->filter(static fn (array $member): bool => ! in_array($member['status'], [EventRosterMemberStatus::Declined->value, EventRosterMemberStatus::Removed->value], true))->count(),
            'sortOrder' => (int) $roster->sort_order,
            'settings' => $roster->settings ?? [],
            'members' => $members->all(),
        ];
    }
}
