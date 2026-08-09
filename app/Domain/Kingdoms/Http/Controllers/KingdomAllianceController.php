<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\ArchiveTrackedKingdomAlliance;
use App\Domain\Kingdoms\Actions\StartTrackingKingdomAlliance;
use App\Domain\Kingdoms\Actions\UpdateTrackedKingdomAlliance;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Queries\KingdomAllianceQuery;
use App\Domain\Platform\Http\Controllers\Controller;
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
        AllianceAuthorization $authorization,
        KingdomAllianceQuery $tracking,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($user, $alliance, PermissionKey::AllianceView)) {
            throw new AuthorizationException;
        }

        return Inertia::render('Alliance/KingdomAlliances', [
            'alliance' => $this->allianceSummary($alliance),
            'canManage' => $authorization->allows($user, $alliance, PermissionKey::KingdomManage),
            'tracking' => $this->trackingRows($tracking->forAlliance($alliance), $alliance, false),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        KingdomAllianceQuery $tracking,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($user, $alliance, PermissionKey::KingdomManage)) {
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
        $start->handle($context->alliance(), $this->user($request), $this->validated($request));

        return back()->with('status', 'kingdom-alliance-tracking-started');
    }

    public function update(
        Request $request,
        AllianceContext $context,
        UpdateTrackedKingdomAlliance $update,
        string $tracking,
    ): RedirectResponse {
        $update->handle($context->alliance(), $this->user($request), $tracking, $this->validated($request));

        return back()->with('status', 'kingdom-alliance-tracking-updated');
    }

    public function archive(
        Request $request,
        AllianceContext $context,
        ArchiveTrackedKingdomAlliance $archive,
        string $tracking,
    ): RedirectResponse {
        $archive->handle($context->alliance(), $this->user($request), $tracking);

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
            $row = [
                'name' => (string) $entry->kingdomAlliance->current_name,
                'tag' => $entry->kingdomAlliance->current_tag,
                'state' => $entry->state->value,
                'kingdom' => (string) $entry->kingdom->number,
                'contextCurrent' => $alliance->kingdom_id !== null && $alliance->kingdom_id === $entry->kingdom_id,
            ];

            if ($includePrivate) {
                $row['id'] = (string) $entry->id;
                $row['kingdomAllianceId'] = (string) $entry->kingdom_alliance_id;
                $row['gameAllianceId'] = $entry->kingdomAlliance->game_alliance_id;
                $row['referenceStatus'] = $entry->kingdomAlliance->status->value;
                $row['managerNotes'] = $entry->manager_notes;
                $row['archivedAt'] = $entry->archived_at?->toIso8601String();
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
