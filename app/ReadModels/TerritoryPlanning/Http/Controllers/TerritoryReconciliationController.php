<?php

declare(strict_types=1);

namespace App\ReadModels\TerritoryPlanning\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Membership\Queries\ActiveAllianceScopeQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Evidence\Queries\TerritorySpatialEvidenceSummaryQuery;
use App\ReadModels\TerritoryPlanning\Queries\TerritoryReconciliationQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TerritoryReconciliationController extends Controller
{
    public function show(
        Request $request,
        string $plan,
        PlayerContext $players,
        TerritoryReconciliationQuery $reconciliation,
        ActiveAllianceScopeQuery $allianceScopes,
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        TerritorySpatialEvidenceSummaryQuery $evidenceSummaries,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $query = $request->validate([
            'revision' => ['nullable', 'ulid'],
            'alliance' => ['nullable', 'ulid'],
            'observation' => ['nullable', 'ulid'],
        ]);
        $projection = $reconciliation->build(
            actorPlayerId: $player->playerId,
            planId: $plan,
            revisionId: is_string($query['revision'] ?? null) ? $query['revision'] : null,
            allianceId: is_string($query['alliance'] ?? null) ? $query['alliance'] : null,
            observationId: is_string($query['observation'] ?? null) ? $query['observation'] : null,
        );
        $targetAllianceId = is_array($projection['alliance'] ?? null) && is_string($projection['alliance']['id'] ?? null)
            ? $projection['alliance']['id']
            : null;
        $kingdomId = is_array($projection['plan'] ?? null) && is_string($projection['plan']['kingdom_id'] ?? null)
            ? $projection['plan']['kingdom_id']
            : $player->kingdomId;
        $activeAlliance = $allianceScopes->findForPlayer($player->playerId, $player->kingdomId);
        $canManageEvidence = $targetAllianceId !== null
            && $activeAlliance !== null
            && $activeAlliance->allianceId === $targetAllianceId
            && $activeAlliance->kingdomId === $kingdomId
            && $intelligenceAuthorization->allows($player->playerId, $targetAllianceId, IntelligencePermission::KingdomManage);

        return Inertia::render('Kingdom/Territory/Reconciliation', [
            'user' => ['name' => $user->name, 'email' => $user->email],
            'activePlayer' => ['id' => $player->playerId, 'name' => $player->currentName, 'kingdomNumber' => $player->kingdomNumber],
            'reconciliation' => $projection,
            'canManageEvidence' => $canManageEvidence,
            'evidence' => $canManageEvidence && $targetAllianceId !== null
                ? $evidenceSummaries->forScope($player->playerId, $targetAllianceId, $kingdomId)
                : [],
        ]);
    }
}
