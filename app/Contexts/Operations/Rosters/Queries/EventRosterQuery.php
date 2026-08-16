<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Queries;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
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
        private EventRosterAvailabilityService $availability,
    ) {}

    /** @return list<array<string,mixed>> */
    public function forPlayer(EventOccurrence $occurrence, Player $player): array
    {
        return array_values(EventRosterMember::query()
            ->where('player_id', $player->id)
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

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        $players = $this->eligiblePlayers->for($event)->keyBy(static fn (Player $player): string => (string) $player->id);

        return array_values($event->occurrences
            ->sortBy('starts_at')
            ->values()
            ->map(function (EventOccurrence $occurrence) use ($players): array {
                $rosters = EventRoster::query()
                    ->where('occurrence_id', $occurrence->id)
                    ->with(['members.player', 'parent'])
                    ->orderBy('sort_order')
                    ->orderBy('key')
                    ->get();
                $responses = EventResponse::query()->where('occurrence_id', $occurrence->id)->get()->keyBy('player_id');
                $registrations = EventRegistration::query()->where('occurrence_id', $occurrence->id)->get()->keyBy('player_id');

                return [
                    'occurrenceId' => (string) $occurrence->id,
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                    'rosters' => $rosters->map(fn (EventRoster $roster): array => $this->rosterPayload($occurrence, $roster))->all(),
                    'candidates' => $players->values()->map(function (Player $player) use ($occurrence, $responses, $registrations): array {
                        $response = $responses->get($player->id);
                        $registration = $registrations->get($player->id);

                        return [
                            'playerId' => (string) $player->id,
                            'name' => (string) $player->current_name,
                            'claimed' => $player->user_id !== null,
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

    /** @return array<string,mixed> */
    private function rosterPayload(EventOccurrence $occurrence, EventRoster $roster): array
    {
        $members = $roster->members->map(function (EventRosterMember $member) use ($occurrence): array {
            $player = $member->player;

            return [
                'id' => (string) $member->id,
                'playerId' => (string) $member->player_id,
                'playerName' => (string) $player->current_name,
                'allianceId' => $member->alliance_id === null ? null : (string) $member->alliance_id,
                'role' => $member->role,
                'slotNumber' => $member->slot_number,
                'status' => $member->status->value,
                'assignmentWarnings' => $member->assignment_warnings ?? [],
                'warnings' => $this->availability->warnings($occurrence, $player),
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
