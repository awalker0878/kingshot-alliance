<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceDiplomacyState;
use App\Contexts\Intelligence\Observations\Actions\ArchiveTrackedKingdomAlliance;
use App\Contexts\Intelligence\Observations\Actions\StartTrackingKingdomAlliance;
use App\Contexts\Intelligence\Observations\Actions\UpdateTrackedKingdomAlliance;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Contexts\Intelligence\Observations\Queries\KingdomAllianceObservationQuery;
use App\Contexts\Intelligence\Observations\Queries\KingdomAllianceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomAllianceController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        KingdomAuthorization $kingdomAuthorization,
        KingdomAllianceQuery $tracking,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        KingdomAllianceReferenceQuery $kingdomAlliances,
        AccountIdentityQuery $accounts,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }
        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        $tracked = $tracking->forAlliance($alliance->allianceId);

        return Inertia::render('Intelligence/KingdomWatch/Index', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => $this->allianceSummary($alliance, $kingdom),
            'canManage' => $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage),
            'canManageKingdomRoles' => $kingdomAuthorization->allows($scope->playerId, $scope->kingdomId, KingdomPermission::RoleManage),
            'tracking' => $this->trackingRows($tracked, $alliance, $kingdoms, $kingdomAlliances, false),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        KingdomAllianceQuery $tracking,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        KingdomAllianceReferenceQuery $kingdomAlliances,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        $tracked = $tracking->forAlliance($alliance->allianceId);

        return Inertia::render('Intelligence/KingdomWatch/Tracking', [
            'alliance' => $this->allianceSummary($alliance, $kingdom),
            'tracking' => $this->trackingRows($tracked, $alliance, $kingdoms, $kingdomAlliances, true),
        ]);
    }

    public function store(Request $request, AllianceContext $context, StartTrackingKingdomAlliance $start): RedirectResponse
    {
        $scope = $context->scope();
        $start->handle($scope->allianceId, $scope->playerId, $this->validated($request));

        return back()->with('status', 'kingdom-alliance-tracking-started');
    }

    public function update(Request $request, AllianceContext $context, UpdateTrackedKingdomAlliance $update, string $tracking): RedirectResponse
    {
        $scope = $context->scope();
        $update->handle($scope->allianceId, $scope->playerId, $tracking, $this->validated($request));

        return back()->with('status', 'kingdom-alliance-tracking-updated');
    }

    public function archive(Request $request, AllianceContext $context, ArchiveTrackedKingdomAlliance $archive, string $tracking): RedirectResponse
    {
        $scope = $context->scope();
        $archive->handle($scope->allianceId, $scope->playerId, $tracking);

        return back()->with('status', 'kingdom-alliance-tracking-archived');
    }

    /** @return array{current_name:string,current_tag?:string|null,game_alliance_id?:string|null,manager_notes?:string|null} */
    private function validated(Request $request): array
    {
        /** @var array{current_name:string,current_tag?:string|null,game_alliance_id?:string|null,manager_notes?:string|null} $validated */
        $validated = $request->validate([
            'current_name' => ['required', 'string', 'max:160'],
            'current_tag' => ['nullable', 'string', 'max:32'],
            'game_alliance_id' => ['nullable', 'string', 'max:100'],
            'manager_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $validated;
    }

    /** @return array{id:string,name:string,kingdom:string} */
    private function allianceSummary(AllianceReference $alliance, KingdomReference $kingdom): array
    {
        return ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number];
    }

    /**
     * @param  iterable<int, TrackedKingdomAlliance>  $tracking
     * @return list<array<string, mixed>>
     */
    private function trackingRows(iterable $tracking, AllianceReference $alliance, KingdomReferenceQuery $kingdoms, KingdomAllianceReferenceQuery $kingdomAlliances, bool $includePrivate): array
    {
        $items = is_array($tracking) ? $tracking : iterator_to_array($tracking);
        $kingdomRefs = $kingdoms->byIds(array_values(array_map(static fn (TrackedKingdomAlliance $row): string => (string) $row->kingdom_id, $items)));
        $allianceRefs = $kingdomAlliances->byIds(array_values(array_map(static fn (TrackedKingdomAlliance $row): string => (string) $row->kingdom_alliance_id, $items)));
        $rows = [];
        foreach ($items as $entry) {
            $reference = $allianceRefs[(string) $entry->kingdom_alliance_id] ?? null;
            $kingdom = $kingdomRefs[(string) $entry->kingdom_id] ?? null;
            if (! $reference instanceof KingdomAllianceReference || ! $kingdom instanceof KingdomReference) {
                continue;
            }
            /** @var KingdomAllianceObservation|null $latest */
            $latest = $entry->observations->first();
            $freshness = $latest === null ? 'missing' : ($latest->captured_at->gte(now()->subDays(KingdomAllianceObservationQuery::FRESH_DAYS)) ? 'current' : 'stale');
            $diplomacy = $entry->diplomacy;
            $needsReview = $diplomacy !== null && (($diplomacy->review_at !== null && $diplomacy->review_at->lte(now())) || ($diplomacy->expires_at !== null && $diplomacy->expires_at->lte(now())));
            $row = [
                'name' => $reference->currentName,
                'tag' => $reference->currentTag,
                'state' => $entry->state->value,
                'kingdom' => (string) $kingdom->number,
                'contextCurrent' => $alliance->kingdomId === $entry->kingdom_id,
                'historyUrl' => route('alliance.kingdom-alliances.history', ['tracking' => $entry->id], false),
                'freshness' => $freshness,
                'latestObservation' => $latest === null ? null : [
                    'observedName' => $latest->observed_name,
                    'observedTag' => $latest->observed_tag,
                    'power' => $latest->power === null ? null : (string) $latest->power,
                    'memberCount' => $latest->member_count,
                    'capturedAt' => $latest->captured_at->toIso8601String(),
                    'source' => $latest->source,
                ],
                'diplomacyState' => $diplomacy?->current_state->value ?? KingdomAllianceDiplomacyState::Unknown->value,
                'diplomacyNeedsReview' => $needsReview,
            ];
            if ($includePrivate) {
                $row += [
                    'id' => (string) $entry->id,
                    'kingdomAllianceId' => $reference->kingdomAllianceId,
                    'gameAllianceId' => $reference->gameAllianceId,
                    'referenceStatus' => $reference->statusObservedAtRead->value,
                    'managerNotes' => $entry->manager_notes,
                    'archivedAt' => $entry->archived_at?->toIso8601String(),
                    'diplomacyUrl' => route('alliance.kingdom-alliances.diplomacy.show', ['tracking' => $entry->id], false),
                ];
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
