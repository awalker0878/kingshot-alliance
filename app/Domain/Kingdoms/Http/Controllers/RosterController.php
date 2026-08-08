<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\MarkRosterEntryLeft;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Queries\RosterQuery;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class RosterController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        RosterQuery $roster,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($user, $alliance, PermissionKey::AllianceView)) {
            throw new AuthorizationException;
        }

        return Inertia::render('Alliance/Roster', [
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
            ],
            'canManage' => $authorization->allows($user, $alliance, PermissionKey::KingdomManage),
            'entries' => $this->entries($roster->forAlliance($alliance), false),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        RosterQuery $roster,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($user, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        $memberships = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('user:id,name,email')
            ->orderBy('joined_at')
            ->get()
            ->map(static fn (AllianceMembership $membership): array => [
                'id' => (string) $membership->id,
                'name' => (string) $membership->user?->name,
                'email' => (string) $membership->user?->email,
            ])
            ->values()
            ->all();

        return Inertia::render('Alliance/RosterManage', [
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
            ],
            'entries' => $this->entries($roster->forAlliance($alliance), true),
            'memberships' => $memberships,
            'states' => [RosterState::Active->value, RosterState::Tracked->value],
        ]);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        SaveRosterEntry $save,
    ): RedirectResponse {
        $validated = $this->validated($request, creating: true);
        $save->handle($context->alliance(), $this->user($request), $validated);

        return back()->with('status', 'roster-entry-created');
    }

    public function update(
        Request $request,
        AllianceContext $context,
        SaveRosterEntry $save,
        string $entry,
    ): RedirectResponse {
        $validated = $this->validated($request, creating: false);
        $save->handle($context->alliance(), $this->user($request), $validated, $entry);

        return back()->with('status', 'roster-entry-updated');
    }

    public function leave(
        Request $request,
        AllianceContext $context,
        MarkRosterEntryLeft $leave,
        string $entry,
    ): RedirectResponse {
        $leave->handle($context->alliance(), $this->user($request), $entry);

        return back()->with('status', 'roster-entry-left');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'membership_id' => ['nullable', 'string', 'ulid'],
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

    /**
     * @param iterable<int, AllianceRosterEntry> $entries
     * @return list<array<string, mixed>>
     */
    private function entries(iterable $entries, bool $includePrivate): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $row = [
                'id' => (string) $entry->id,
                'playerId' => (string) $entry->kingdom_player_id,
                'gamePlayerId' => $entry->player->game_player_id,
                'name' => (string) $entry->observed_name,
                'gameRole' => $entry->game_role,
                'state' => $entry->state->value,
                'joinedAt' => $entry->joined_at?->toDateString(),
                'leftAt' => $entry->left_at?->toIso8601String(),
                'lastObservedAt' => $entry->last_observed_at?->toIso8601String(),
                'source' => (string) $entry->source,
                'membership' => $entry->membership === null ? null : [
                    'id' => (string) $entry->membership->id,
                    'name' => (string) $entry->membership->user?->name,
                    'email' => (string) $entry->membership->user?->email,
                ],
            ];

            if ($includePrivate) {
                $row['managerNotes'] = $entry->manager_notes;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
