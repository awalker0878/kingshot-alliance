<?php

declare(strict_types=1);

namespace App\Domain\Events\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Domain\Events\Actions\AssignEventRosterPlayer;
use App\Domain\Events\Actions\RecordEventRosterParticipation;
use App\Domain\Events\Actions\RemoveEventRosterPlayer;
use App\Domain\Events\Actions\RespondToEventRosterAssignment;
use App\Domain\Events\Actions\SaveEventRoster;
use App\Domain\Events\Enums\EventRosterMemberStatus;
use App\Domain\Events\Enums\EventRosterType;
use App\Domain\Events\Models\EventRoster;
use App\Domain\Events\Models\EventRosterMember;
use App\Domain\Events\Queries\EventCalendarQuery;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventRosterController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function store(Request $request, string $occurrence, EventCalendarQuery $events, SaveEventRoster $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $validated = $this->validateRoster($request);
        $parent = isset($validated['parent_id'])
            ? EventRoster::query()->whereKey((string) $validated['parent_id'])->where('occurrence_id', $record->id)->firstOrFail()
            : null;
        $save->handle(
            actor: $actor,
            occurrence: $record,
            key: (string) $validated['key'],
            type: EventRosterType::from((string) $validated['roster_type']),
            assignmentGroup: (string) $validated['assignment_group'],
            name: (string) $validated['name'],
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            parent: $parent,
        );

        return back()->with('status', 'event-roster-saved');
    }

    public function update(Request $request, string $occurrence, string $roster, EventCalendarQuery $events, SaveEventRoster $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $rosterRecord = EventRoster::query()->whereKey($roster)->where('occurrence_id', $record->id)->firstOrFail();
        $validated = $this->validateRoster($request);
        $parent = isset($validated['parent_id'])
            ? EventRoster::query()->whereKey((string) $validated['parent_id'])->where('occurrence_id', $record->id)->firstOrFail()
            : null;
        $save->handle(
            actor: $actor,
            occurrence: $record,
            key: (string) $validated['key'],
            type: EventRosterType::from((string) $validated['roster_type']),
            assignmentGroup: (string) $validated['assignment_group'],
            name: (string) $validated['name'],
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            parent: $parent,
            roster: $rosterRecord,
        );

        return back()->with('status', 'event-roster-saved');
    }

    public function assign(Request $request, string $occurrence, string $roster, string $player, EventCalendarQuery $events, AssignEventRosterPlayer $assign): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $rosterRecord = EventRoster::query()->whereKey($roster)->where('occurrence_id', $record->id)->firstOrFail();
        $playerRecord = Player::query()->whereKey($player)->firstOrFail();
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'max:80'],
            'slot_number' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $assign->handle(
            actor: $actor,
            roster: $rosterRecord,
            player: $playerRecord,
            role: $validated['role'] ?? null,
            slotNumber: isset($validated['slot_number']) ? (int) $validated['slot_number'] : null,
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', 'event-roster-player-assigned');
    }

    public function remove(Request $request, string $occurrence, string $roster, string $player, EventCalendarQuery $events, RemoveEventRosterPlayer $remove): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $member = EventRosterMember::query()
            ->where('player_id', $player)
            ->whereHas('roster', static fn ($query) => $query->where('id', $roster)->where('occurrence_id', $record->id))
            ->firstOrFail();
        $remove->handle($actor, $member);

        return back()->with('status', 'event-roster-player-removed');
    }

    public function respond(Request $request, string $occurrence, string $member, EventCalendarQuery $events, RespondToEventRosterAssignment $respond): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $memberRecord = EventRosterMember::query()
            ->whereKey($member)
            ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $record->id))
            ->firstOrFail();
        $validated = $request->validate([
            'status' => ['required', Rule::in([EventRosterMemberStatus::Confirmed->value, EventRosterMemberStatus::Declined->value])],
        ]);
        $respond->handle($actor, $memberRecord, $actor, EventRosterMemberStatus::from((string) $validated['status']));

        return back()->with('status', 'event-roster-assignment-responded');
    }

    public function participation(
        Request $request,
        string $occurrence,
        string $member,
        EventCalendarQuery $events,
        RecordEventRosterParticipation $recordParticipation,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $memberRecord = EventRosterMember::query()
            ->whereKey($member)
            ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $record->id))
            ->firstOrFail();
        $validated = $request->validate([
            'status' => ['required', Rule::in([EventRosterMemberStatus::Participated->value, EventRosterMemberStatus::Absent->value])],
        ]);
        $recordParticipation->handle(
            $actor,
            $memberRecord,
            EventRosterMemberStatus::from((string) $validated['status']),
        );

        return back()->with('status', 'event-roster-participation-recorded');
    }

    /** @return array<string,mixed> */
    private function validateRoster(Request $request): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:160'],
            'roster_type' => ['required', Rule::enum(EventRosterType::class)],
            'assignment_group' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'parent_id' => ['nullable', 'string'],
        ]);
    }

    private function player(): Player
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof Player, 409, 'Select a Player before performing Event operations.');

        return $player;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
