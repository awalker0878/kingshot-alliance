<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Rosters\Actions\AssignEventRosterPlayer;
use App\Contexts\Operations\Rosters\Actions\RecordEventRosterParticipation;
use App\Contexts\Operations\Rosters\Actions\RemoveEventRosterPlayer;
use App\Contexts\Operations\Rosters\Actions\RespondToEventRosterAssignment;
use App\Contexts\Operations\Rosters\Actions\SaveEventRoster;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventRosterController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function store(Request $request, string $occurrence, SaveEventRoster $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $validated = $this->validateRoster($request);
        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            key: (string) $validated['key'],
            type: EventRosterType::from((string) $validated['roster_type']),
            assignmentGroup: (string) $validated['assignment_group'],
            name: (string) $validated['name'],
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            parentId: isset($validated['parent_id']) ? (string) $validated['parent_id'] : null,
        );

        return back()->with('actionReceipt', $this->receipt('event-roster-saved'));
    }

    public function update(Request $request, string $occurrence, string $roster, SaveEventRoster $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $validated = $this->validateRoster($request);
        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            key: (string) $validated['key'],
            type: EventRosterType::from((string) $validated['roster_type']),
            assignmentGroup: (string) $validated['assignment_group'],
            name: (string) $validated['name'],
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            parentId: isset($validated['parent_id']) ? (string) $validated['parent_id'] : null,
            rosterId: $roster,
        );

        return back()->with('actionReceipt', $this->receipt('event-roster-saved'));
    }

    public function assign(Request $request, string $occurrence, string $roster, string $player, AssignEventRosterPlayer $assign): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'max:80'],
            'slot_number' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $assign->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            rosterId: $roster,
            playerId: $player,
            role: isset($validated['role']) ? (string) $validated['role'] : null,
            slotNumber: isset($validated['slot_number']) ? (int) $validated['slot_number'] : null,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
        );

        return back()->with('actionReceipt', $this->receipt('event-roster-player-assigned'));
    }

    public function remove(Request $request, string $occurrence, string $roster, string $player, RemoveEventRosterPlayer $remove): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $memberId = EventRosterMember::query()
            ->where('roster_id', $roster)
            ->where('player_id', $player)
            ->value('id');
        abort_unless(is_string($memberId) && $memberId !== '', 404);
        $remove->handle($actor->playerId, $occurrence, $memberId);

        return back()->with('actionReceipt', $this->receipt('event-roster-player-removed'));
    }

    public function respond(Request $request, string $occurrence, string $member, RespondToEventRosterAssignment $respond): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $validated = $request->validate([
            'status' => ['required', Rule::in([EventRosterMemberStatus::Confirmed->value, EventRosterMemberStatus::Declined->value])],
        ]);
        $respond->handle($actor->playerId, $occurrence, $member, EventRosterMemberStatus::from((string) $validated['status']));

        return back()->with('actionReceipt', $this->receipt('event-roster-assignment-responded'));
    }

    public function participation(
        Request $request,
        string $occurrence,
        string $member,
        RecordEventRosterParticipation $recordParticipation,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $validated = $request->validate([
            'status' => ['required', Rule::in([EventRosterMemberStatus::Participated->value, EventRosterMemberStatus::Absent->value])],
        ]);
        $recordParticipation->handle(
            $actor->playerId,
            $occurrence,
            $member,
            EventRosterMemberStatus::from((string) $validated['status']),
        );

        return back()->with('actionReceipt', $this->receipt('event-roster-participation-recorded'));
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

    private function player(): PlayerReference
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof PlayerReference, 409, 'Select a Player before performing Event operations.');

        return $player;
    }

    private function user(Request $request): AuthenticatedAccount
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        return $user;
    }
}
