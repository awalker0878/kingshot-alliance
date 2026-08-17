<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Actions\MarkRosterEntryLeft;
use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RosterController extends Controller
{
    public function store(Request $request, AllianceContext $context, UpsertRosterEntry $save): RedirectResponse
    {
        $scope = $context->scope();
        $save->handle($scope->playerId, $scope->allianceId, $this->validated($request, creating: true));

        return back()->with('status', 'roster-entry-created');
    }

    public function update(Request $request, AllianceContext $context, UpsertRosterEntry $save, string $entry): RedirectResponse
    {
        $scope = $context->scope();
        $save->handle($scope->playerId, $scope->allianceId, $this->validated($request, creating: false), $entry);

        return back()->with('status', 'roster-entry-updated');
    }

    public function leave(Request $request, AllianceContext $context, MarkRosterEntryLeft $leave, string $entry): RedirectResponse
    {
        $scope = $context->scope();
        $leave->handle($scope->playerId, $scope->allianceId, $entry);

        return back()->with('status', 'roster-entry-left');
    }

    /** @return array{name:string,game_player_id?:string|null,game_role?:string|null,state:RosterState,joined_at?:string|null,manager_notes?:string|null} */
    private function validated(Request $request, bool $creating): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'game_role' => ['nullable', 'string', 'max:64'],
            'state' => ['required', Rule::in([RosterState::Active->value, RosterState::Tracked->value])],
            'joined_at' => ['nullable', 'date'],
            'manager_notes' => ['nullable', 'string', 'max:5000'],
        ];
        if ($creating) {
            $rules['game_player_id'] = ['nullable', 'string', 'max:100'];
        }
        $validated = $request->validate($rules);
        $validated['state'] = RosterState::from((string) $validated['state']);

        return $validated;
    }
}
