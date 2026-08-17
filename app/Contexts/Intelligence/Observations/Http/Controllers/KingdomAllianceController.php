<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
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
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        KingdomAuthorization $kingdomAuthorization,
        KingdomAllianceQuery $tracking,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }

        return Inertia::render('Alliance/KingdomAlliances', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => $this->allianceSummary($alliance),
            'canManage' => $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage),
            'canManageKingdomRoles' => $alliance->kingdom !== null
                && $kingdomAuthorization->allows($context->player(), $alliance->kingdom, KingdomPermission::RoleManage),
            'tracking' => $this->trackingRows($tracking->forAlliance($alliance), $alliance, false),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        KingdomAllianceQuery $tracking,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        return Inertia::render('Alliance/KingdomAlliancesManage', [
            'alliance' => $this->allianceSummary($alliance),
            'tracking' => $this->trackingRows($tracking->forAlliance($alliance), $alliance, true),
        ]);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        StartTrackingKingdomAlliance $start,
    ): RedirectResponse {
        $start->handle($context->alliance(), $context->player(), $this->validated($request));

        return back()->with('status', 'kingdom-alliance-tracking-started');
    }

    public function update(
        Request $request,
        AllianceContext $context,
        UpdateTrackedKingdomAlliance $update,
        string $tracking,
    ): RedirectResponse {
        $update->handle($context->alliance(), $context->player(), $tracking, $this->validated($request));

        return back()->with('status', 'kingdom-alliance-tracking-updated');
    }

    public function archive(
        Request $request,
        AllianceContext $context,
        ArchiveTrackedKingdomAlliance $archive,
        string $tracking,
    ): RedirectResponse {
        $archive->handle($context->alliance(), $context->player(), $tracking);

        return back()->with('status', 'kingdom-alliance-tracking-archived');
    }

    /**
     * @return array{
     *   current_name: string,
     *   current_tag?: string|null,
     *   game_alliance_id?: string|null,
     *   manager_notes?: string|null
     * }
     */
    private function validated(Request $request): array
    {
        /** @var array{current_name: string, current_tag?: string|null, game_alliance_id?: string|null, manager_notes?: string|null} $validated */
        $validated = $request->validate([
            'current_name' => ['required', 'string', 'max:160'],
            'current_tag' => ['nullable', 'string', 'max:32'],
            'game_alliance_id' => ['nullable', 'string', 'max:100'],
            'manager_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $validated;
    }

    /** @return array{id: string, name: string, kingdom: string|null} */
    private function allianceSummary(Alliance $alliance): array
    {
        return [
            'id' => (string) $alliance->id,
            'name' => (string) $alliance->name,
            'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
        ];
    }

    /**
     * @param  iterable<int, TrackedKingdomAlliance>  $tracking
     * @return list<array<string, mixed>>
     */
    private function trackingRows(iterable $tracking, Alliance $alliance, bool $includePrivate): array
    {
        $rows = [];

        foreach ($tracking as $entry) {
            /** @var KingdomAllianceObservation|null $latest */
            $latest = $entry->observations->first();
            $freshness = $latest === null
                ? 'missing'
                : ($latest->captured_at->gte(now()->subDays(KingdomAllianceObservationQuery::FRESH_DAYS)) ? 'current' : 'stale');
            $diplomacy = $entry->diplomacy;
            $diplomacyNeedsReview = $diplomacy !== null
                && (($diplomacy->review_at !== null && $diplomacy->review_at->lte(now()))
                    || ($diplomacy->expires_at !== null && $diplomacy->expires_at->lte(now())));

            $row = [
                'name' => (string) $entry->kingdomAlliance->current_name,
                'tag' => $entry->kingdomAlliance->current_tag,
                'state' => $entry->state->value,
                'kingdom' => (string) $entry->kingdom->number,
                'contextCurrent' => $alliance->kingdom_id !== null && $alliance->kingdom_id === $entry->kingdom_id,
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
                'diplomacyNeedsReview' => $diplomacyNeedsReview,
            ];

            if ($includePrivate) {
                $row['id'] = (string) $entry->id;
                $row['kingdomAllianceId'] = (string) $entry->kingdom_alliance_id;
                $row['gameAllianceId'] = $entry->kingdomAlliance->game_alliance_id;
                $row['referenceStatus'] = $entry->kingdomAlliance->status->value;
                $row['managerNotes'] = $entry->manager_notes;
                $row['archivedAt'] = $entry->archived_at?->toIso8601String();
                $row['diplomacyUrl'] = route('alliance.kingdom-alliances.diplomacy.show', ['tracking' => $entry->id], false);
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
