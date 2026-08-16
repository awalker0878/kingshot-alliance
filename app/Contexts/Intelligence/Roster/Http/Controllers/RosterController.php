<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Actions\MarkRosterEntryLeft;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Contexts\Intelligence\Roster\Queries\PlayerSnapshotQuery;
use App\Contexts\Intelligence\Roster\Queries\RosterQuery;
use App\Shared\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
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
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        RosterQuery $roster,
        PlayerSnapshotQuery $snapshots,
    ): Response {
        $user = $this->user($request);
        $actor = $context->player();
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($actor, $alliance, AlliancePermission::View)) {
            throw new AuthorizationException;
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'state' => ['nullable', Rule::in(array_column(RosterState::cases(), 'value'))],
            'linkage' => ['nullable', Rule::in(['linked', 'unlinked'])],
            'role' => ['nullable', 'string', 'max:64'],
            'observation' => ['nullable', Rule::in(['current', 'stale', 'missing'])],
        ]);
        $entries = $roster->forAlliance($alliance, $filters);
        $latestSnapshots = $snapshots->latestForEntries($alliance, $entries);
        $memberships = $this->memberships($alliance->id);

        return Inertia::render('Alliance/Roster', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'kingdom' => (string) $alliance->kingdom->number,
            ],
            'canManage' => $intelligenceAuthorization->allows($actor, $alliance, IntelligencePermission::KingdomManage),
            'entries' => $this->entries($entries, false, $latestSnapshots, $memberships),
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'state' => (string) ($filters['state'] ?? ''),
                'linkage' => (string) ($filters['linkage'] ?? ''),
                'role' => (string) ($filters['role'] ?? ''),
                'observation' => (string) ($filters['observation'] ?? ''),
            ],
            'roleOptions' => $roster->rolesForAlliance($alliance),
            'staleAfterDays' => PlayerSnapshotQuery::STALE_AFTER_DAYS,
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        RosterQuery $roster,
        PlayerSnapshotQuery $snapshots,
    ): Response {
        $user = $this->user($request);
        $actor = $context->player();
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($actor, $alliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        $entries = $roster->forAlliance($alliance);
        $latestSnapshots = $snapshots->latestForEntries($alliance, $entries);
        $memberships = $this->memberships($alliance->id);

        $membershipRows = $memberships->map(static function (AllianceMembership $membership) use ($entries): array {
            $entry = $entries->firstWhere('player_id', $membership->player_id);

            return [
                'playerId' => (string) $membership->player_id,
                'name' => (string) $membership->player->current_name,
                'rank' => $membership->rank->value,
                'claimed' => $membership->player->user_id !== null,
                'linkedRosterEntryId' => $entry instanceof AllianceRosterEntry ? (string) $entry->id : null,
            ];
        })->values();

        return Inertia::render('Alliance/RosterManage', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'kingdom' => (string) $alliance->kingdom->number,
            ],
            'entries' => $this->entries($entries, true, $latestSnapshots, $memberships),
            'states' => [RosterState::Active->value, RosterState::Tracked->value],
            'gaps' => [
                'membershipsWithoutRoster' => $membershipRows
                    ->filter(static fn (array $membership): bool => $membership['linkedRosterEntryId'] === null)
                    ->map(static fn (array $membership): array => [
                        'playerId' => $membership['playerId'],
                        'name' => $membership['name'],
                        'rank' => $membership['rank'],
                        'claimed' => $membership['claimed'],
                    ])
                    ->values()
                    ->all(),
                'rosterWithoutMembership' => $entries->filter(static fn (AllianceRosterEntry $entry): bool => ! $memberships->has((string) $entry->player_id))->count(),
            ],
        ]);
    }

    public function store(Request $request, AllianceContext $context, SaveRosterEntry $save): RedirectResponse
    {
        $save->handle($context->alliance(), $context->player(), $this->validated($request, creating: true));

        return back()->with('status', 'roster-entry-created');
    }

    public function update(Request $request, AllianceContext $context, SaveRosterEntry $save, string $entry): RedirectResponse
    {
        $save->handle($context->alliance(), $context->player(), $this->validated($request, creating: false), $entry);

        return back()->with('status', 'roster-entry-updated');
    }

    public function leave(Request $request, AllianceContext $context, MarkRosterEntryLeft $leave, string $entry): RedirectResponse
    {
        $leave->handle($context->alliance(), $context->player(), $entry);

        return back()->with('status', 'roster-entry-left');
    }

    /** @return array{name: string, game_player_id?: string|null, game_role?: string|null, state: RosterState, joined_at?: string|null, manager_notes?: string|null} */
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

    /**
     * @param  Collection<int, AllianceRosterEntry>  $entries
     * @param  array<string, PlayerSnapshot>  $latestSnapshots
     * @param  Collection<string, AllianceMembership>  $memberships
     * @return list<array<string, mixed>>
     */
    private function entries(Collection $entries, bool $includePrivate, array $latestSnapshots, Collection $memberships): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $membership = $memberships->get((string) $entry->player_id);
            $membershipPayload = $membership instanceof AllianceMembership
                ? [
                    'playerId' => (string) $membership->player_id,
                    'name' => (string) $membership->player->current_name,
                    'rank' => $membership->rank->value,
                    'claimed' => $membership->player->user_id !== null,
                ]
                : null;

            $latestSnapshot = $latestSnapshots[(string) $entry->id] ?? null;
            $row = [
                'id' => (string) $entry->id,
                'playerId' => (string) $entry->player_id,
                'gamePlayerId' => $entry->player->game_player_id,
                'name' => (string) $entry->observed_name,
                'gameRole' => $entry->game_role,
                'state' => $entry->state->value,
                'joinedAt' => $entry->joined_at?->toDateString(),
                'leftAt' => $entry->left_at?->toIso8601String(),
                'lastObservedAt' => $entry->last_observed_at?->toIso8601String(),
                'source' => (string) $entry->source,
                'membership' => $membershipPayload,
                'latestSnapshot' => $latestSnapshot === null ? null : [
                    'observedName' => (string) $latestSnapshot->observed_name,
                    'power' => (string) $latestSnapshot->power,
                    'progressionLevel' => $latestSnapshot->progression_level,
                    'observedAllianceTag' => $latestSnapshot->observed_alliance_tag,
                    'capturedAt' => $latestSnapshot->captured_at->toIso8601String(),
                    'source' => (string) $latestSnapshot->source,
                ],
            ];

            if ($includePrivate) {
                $row['managerNotes'] = $entry->manager_notes;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** @return Collection<string, AllianceMembership> */
    private function memberships(string $allianceId): Collection
    {
        /** @var Collection<int, AllianceMembership> $memberships */
        $memberships = AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->with('player:id,user_id,current_name')
            ->get();

        return $memberships->keyBy(static fn (AllianceMembership $membership): string => (string) $membership->player_id);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
