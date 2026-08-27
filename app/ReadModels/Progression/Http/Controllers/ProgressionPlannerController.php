<?php

declare(strict_types=1);

namespace App\ReadModels\Progression\Http\Controllers;

use App\Contexts\Alliance\Membership\Queries\ActiveAllianceScopeQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Queries\GovernorProgressionObservationQuery;
use App\ReadModels\Progression\Queries\ProgressionPlannerQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProgressionPlannerController extends Controller
{
    public function __invoke(
        Request $request,
        PlayerContext $context,
        ProgressionDatasetQuery $datasets,
        ActiveAllianceScopeQuery $allianceScopes,
        RosterEntryQuery $rosterEntries,
        AllianceIntelligenceAuthorization $intelligence,
        GovernorProgressionObservationQuery $observations,
        ProgressionPlannerQuery $planner,
    ): Response {
        $player = $context->player();
        $datasetId = trim((string) $request->query('dataset_id', ''));
        $datasetChecksum = trim((string) $request->query('dataset_checksum', ''));
        $dataset = $datasetId !== ''
            ? $datasets->require($datasetId, $datasetChecksum !== '' ? $datasetChecksum : null)
            : $datasets->latest();

        $scope = $allianceScopes->findForPlayer($player->playerId, $player->kingdomId);
        $allianceId = $scope?->allianceId;
        $canViewObservations = $allianceId !== null
            && $intelligence->allows($player->playerId, $allianceId, IntelligencePermission::View);

        $entry = null;
        if ($allianceId !== null && $canViewObservations) {
            $entries = $rosterEntries->forPlayer($allianceId, $player->playerId, 1);
            $entry = $entries[0] ?? null;
        }

        $observationState = $entry !== null && $canViewObservations
            ? $observations->forRosterEntry((string) $allianceId, $entry->rosterEntryId)
            : [
                'history' => [],
                'current' => ['profile' => [], 'heroes' => [], 'governorGear' => [], 'charms' => [], 'completeRosterCapture' => null],
                'last_updated_at' => null,
            ];

        $family = trim((string) $request->query('family', ''));
        $subjectId = trim((string) $request->query('subject', ''));
        $target = trim((string) $request->query('target', ''));
        $model = $planner->compose(
            dataset: $dataset,
            observationState: $observationState,
            family: $family !== '' ? $family : null,
            subjectId: $subjectId !== '' ? $subjectId : null,
            targetStateId: $target !== '' ? $target : null,
            calculate: $request->boolean('calculate'),
        );

        $user = $request->user();

        return Inertia::render('Kingdom/Progression/Planner', [
            'user' => ['name' => (string) $user?->name, 'email' => (string) $user?->email],
            'governor' => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'allianceId' => $allianceId,
                'rosterEntryId' => $entry?->rosterEntryId,
            ],
            'observationAccess' => ['canView' => $canViewObservations],
            'planner' => $model,
        ]);
    }
}
