<?php

declare(strict_types=1);

namespace App\ReadModels\Roster\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\ReadModels\AllianceGovernance\Queries\MembershipGovernanceHistoryQuery;
use App\ReadModels\Roster\Queries\MemberCapabilityProfileQuery;
use App\ReadModels\Roster\Queries\PlayerSnapshotQuery;
use App\ReadModels\Roster\Services\PlayerProgressionTimeline;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlayerSnapshotHistoryController extends Controller
{
    public function show(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        AllianceAuthorization $allianceAuthorization,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        AccountIdentityQuery $accounts,
        PlayerReferenceQuery $players,
        PlayerSnapshotQuery $snapshots,
        PlayerProgressionTimeline $timeline,
        MemberCapabilityProfileQuery $capabilityProfile,
        MembershipGovernanceHistoryQuery $membershipGovernance,
        string $entry,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }

        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        $rosterEntry = AllianceRosterEntry::query()->where('alliance_id', $alliance->allianceId)->findOrFail($entry);
        $player = $players->require((string) $rosterEntry->player_id);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $rosterEntry->player_id)
            ->where('status', MembershipStatus::Active->value)
            ->first();
        $canManage = $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage);
        $canViewMembershipGovernance = $allianceAuthorization->allows(
            $scope->playerId,
            $scope->allianceId,
            AlliancePermission::MembershipManage,
        ) || $allianceAuthorization->allows(
            $scope->playerId,
            $scope->allianceId,
            AlliancePermission::RoleManage,
        ) || $allianceAuthorization->allows(
            $scope->playerId,
            $scope->allianceId,
            AlliancePermission::Manage,
        );
        $history = $snapshots->historyForEntry($alliance->allianceId, $rosterEntry, 251);
        $visibleHistory = $history->take(250)->values();
        $changes = $timeline->changes($history);
        $actorIds = $visibleHistory->pluck('actor_player_id')->filter()->map(static fn ($id): string => (string) $id)->values()->all();
        $actorRefs = $players->byIds($actorIds);
        $latest = $visibleHistory->first();
        $profile = $capabilityProfile->forPlayer(
            $scope->playerId,
            $alliance->allianceId,
            $rosterEntry,
            $player,
        );
        $profile['membershipGovernance'] = [
            'access' => $canViewMembershipGovernance ? 'available' : 'unavailable',
            'history' => $canViewMembershipGovernance
                ? $membershipGovernance->forPlayer($alliance->allianceId, $player->playerId, 12)
                : [],
            'href' => '/alliance/members/'.$player->playerId.'/history',
        ];

        return Inertia::render('Intelligence/Roster/History', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'kingdom' => (string) $kingdom->number,
            ],
            'entry' => [
                'id' => (string) $rosterEntry->id,
                'playerId' => $player->playerId,
                'gamePlayerId' => $player->gamePlayerId,
                'name' => (string) $rosterEntry->observed_name,
                'gameRole' => $rosterEntry->game_role,
                'state' => $rosterEntry->state->value,
                'membership' => $membership instanceof AllianceMembership ? ['name' => $player->currentName] : null,
            ],
            'canManage' => $canManage,
            'latest' => $latest instanceof PlayerSnapshot
                ? $this->snapshot($latest, $canManage, $actorRefs, $changes[(string) $latest->id] ?? null)
                : null,
            'snapshots' => $visibleHistory->map(fn (PlayerSnapshot $snapshot): array => $this->snapshot(
                $snapshot,
                $canManage,
                $actorRefs,
                $changes[(string) $snapshot->id] ?? null,
            ))->values()->all(),
            'hasMoreSnapshots' => $history->count() > $visibleHistory->count(),
            'staleAfterDays' => PlayerSnapshotQuery::STALE_AFTER_DAYS,
            'capabilityProfile' => $profile,
        ]);
    }

    /**
     * @param  array<string, PlayerReference>  $actors
     * @param  array<string, mixed>|null  $change
     * @return array<string, mixed>
     */
    private function snapshot(
        PlayerSnapshot $snapshot,
        bool $includeActor,
        array $actors,
        ?array $change,
    ): array {
        $row = [
            'id' => (string) $snapshot->id,
            'observedName' => (string) $snapshot->observed_name,
            'power' => (string) $snapshot->power,
            'progressionLevel' => $snapshot->progression_level,
            'observedAllianceTag' => $snapshot->observed_alliance_tag,
            'capturedAt' => $snapshot->captured_at->toIso8601String(),
            'source' => (string) $snapshot->source,
            'change' => $change,
        ];
        if ($includeActor) {
            $actor = $snapshot->actor_player_id === null ? null : ($actors[(string) $snapshot->actor_player_id] ?? null);
            $row += [
                'actorName' => $actor?->currentName,
                'sourceSubscriptionId' => $snapshot->source_subscription_id,
                'sourceBatchId' => $snapshot->source_batch_id,
                'sourceAdapterKey' => $snapshot->source_adapter_key,
                'sourceAdapterVersion' => $snapshot->source_adapter_version,
                'sourceRecordId' => $snapshot->source_record_id,
                'sourceIdentityHash' => $snapshot->source_identity_hash,
                'sourcePayloadHash' => $snapshot->source_payload_hash,
            ];
        }

        return $row;
    }
}
