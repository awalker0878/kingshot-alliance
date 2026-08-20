<?php

declare(strict_types=1);

namespace App\ReadModels\Roster\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\ReadModels\Roster\Queries\PlayerSnapshotQuery;
use App\ReadModels\Roster\Queries\RosterQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class RosterReadController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        AccountIdentityQuery $accounts,
        PlayerReferenceQuery $players,
        RosterQuery $roster,
        PlayerSnapshotQuery $snapshots,
    ): Response {
        $scope = $context->scope();
        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);

        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'state' => ['nullable', Rule::in(array_column(RosterState::cases(), 'value'))],
            'linkage' => ['nullable', Rule::in(['linked', 'unlinked'])],
            'role' => ['nullable', 'string', 'max:64'],
            'observation' => ['nullable', Rule::in(['current', 'stale', 'missing'])],
            'cursor' => ['nullable', 'string', 'max:4096'],
        ]);
        $page = $roster->pageForAlliance(
            $alliance->allianceId,
            $filters,
            isset($filters['cursor']) ? (string) $filters['cursor'] : null,
        );
        /** @var Collection<int, AllianceRosterEntry> $entries */
        $entries = new Collection($page->items);
        $latest = $snapshots->latestForEntries($alliance->allianceId, $entries);
        $memberships = $this->memberships($alliance->allianceId);
        $playerRefs = $this->playerReferences($players, $entries, $memberships);
        $entryRows = $this->entries($entries, false, $latest, $memberships, $playerRefs);

        return Inertia::render('Alliance/Members/Index', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'kingdom' => (string) $kingdom->number,
            ],
            'canManage' => $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage),
            'entryPage' => [
                ...$page->toArray(),
                'items' => $entryRows,
            ],
            'summary' => $roster->summaryForAlliance($alliance->allianceId, $filters),
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'state' => (string) ($filters['state'] ?? ''),
                'linkage' => (string) ($filters['linkage'] ?? ''),
                'role' => (string) ($filters['role'] ?? ''),
                'observation' => (string) ($filters['observation'] ?? ''),
            ],
            'roleOptions' => $roster->rolesForAlliance($alliance->allianceId),
            'staleAfterDays' => PlayerSnapshotQuery::STALE_AFTER_DAYS,
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        AccountIdentityQuery $accounts,
        PlayerReferenceQuery $players,
        RosterQuery $roster,
        PlayerSnapshotQuery $snapshots,
    ): Response {
        $scope = $context->scope();
        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);

        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        $entries = $roster->forAlliance($alliance->allianceId);
        $latest = $snapshots->latestForEntries($alliance->allianceId, $entries);
        $memberships = $this->memberships($alliance->allianceId);
        $playerRefs = $this->playerReferences($players, $entries, $memberships);

        $membershipRows = $memberships->map(static function (AllianceMembership $membership) use ($entries, $playerRefs): array {
            $entry = $entries->firstWhere('player_id', $membership->player_id);
            $player = $playerRefs[(string) $membership->player_id] ?? null;

            return [
                'playerId' => (string) $membership->player_id,
                'name' => $player->currentName ?? '',
                'rank' => $membership->rank->value,
                'claimed' => $player?->userId !== null,
                'linkedRosterEntryId' => $entry instanceof AllianceRosterEntry ? (string) $entry->id : null,
            ];
        })->values();

        return Inertia::render('Alliance/Members/Manage', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'kingdom' => (string) $kingdom->number,
            ],
            'entries' => $this->entries($entries, true, $latest, $memberships, $playerRefs),
            'states' => [RosterState::Active->value, RosterState::Tracked->value],
            'gaps' => [
                'membershipsWithoutRoster' => $membershipRows
                    ->filter(static fn (array $membership): bool => $membership['linkedRosterEntryId'] === null)
                    ->map(static fn (array $membership): array => [
                        'playerId' => $membership['playerId'],
                        'name' => $membership['name'],
                        'rank' => $membership['rank'],
                        'claimed' => $membership['claimed'],
                    ])->values()->all(),
                'rosterWithoutMembership' => $entries
                    ->filter(static fn (AllianceRosterEntry $entry): bool => ! $memberships->has((string) $entry->player_id))
                    ->count(),
            ],
        ]);
    }

    /** @return Collection<string, AllianceMembership> */
    private function memberships(string $allianceId): Collection
    {
        /** @var Collection<int, AllianceMembership> $memberships */
        $memberships = AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->get();

        return $memberships->keyBy(static fn (AllianceMembership $membership): string => (string) $membership->player_id);
    }

    /**
     * @param  Collection<int, AllianceRosterEntry>  $entries
     * @param  Collection<string, AllianceMembership>  $memberships
     * @return array<string, PlayerReference>
     */
    private function playerReferences(PlayerReferenceQuery $players, Collection $entries, Collection $memberships): array
    {
        $ids = [];
        foreach ($entries as $entry) {
            $ids[] = (string) $entry->player_id;
        }
        foreach ($memberships as $membership) {
            $ids[] = (string) $membership->player_id;
        }

        return $players->byIds(array_values(array_unique($ids)));
    }

    /**
     * @param  Collection<int, AllianceRosterEntry>  $entries
     * @param  array<string, PlayerSnapshot>  $latestSnapshots
     * @param  Collection<string, AllianceMembership>  $memberships
     * @param  array<string, PlayerReference>  $players
     * @return list<array<string,mixed>>
     */
    private function entries(
        Collection $entries,
        bool $includePrivate,
        array $latestSnapshots,
        Collection $memberships,
        array $players,
    ): array {
        $rows = [];
        foreach ($entries as $entry) {
            $player = $players[(string) $entry->player_id] ?? null;
            $membership = $memberships->get((string) $entry->player_id);
            $latest = $latestSnapshots[(string) $entry->id] ?? null;
            $row = [
                'id' => (string) $entry->id,
                'playerId' => (string) $entry->player_id,
                'gamePlayerId' => $player?->gamePlayerId,
                'name' => (string) $entry->observed_name,
                'gameRole' => $entry->game_role,
                'state' => $entry->state->value,
                'joinedAt' => $entry->joined_at?->toDateString(),
                'leftAt' => $entry->left_at?->toIso8601String(),
                'lastObservedAt' => $entry->last_observed_at?->toIso8601String(),
                'source' => (string) $entry->source,
                'membership' => $membership instanceof AllianceMembership ? [
                    'playerId' => (string) $membership->player_id,
                    'name' => $player->currentName ?? '',
                    'rank' => $membership->rank->value,
                    'claimed' => $player?->userId !== null,
                ] : null,
                'latestSnapshot' => $latest instanceof PlayerSnapshot ? [
                    'observedName' => (string) $latest->observed_name,
                    'power' => (string) $latest->power,
                    'progressionLevel' => $latest->progression_level,
                    'observedAllianceTag' => $latest->observed_alliance_tag,
                    'capturedAt' => $latest->captured_at->toIso8601String(),
                    'source' => (string) $latest->source,
                ] : null,
            ];
            if ($includePrivate) {
                $row['managerNotes'] = $entry->manager_notes;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
