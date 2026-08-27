<?php

declare(strict_types=1);

namespace App\ReadModels\Progression\Http\Controllers;

use App\Contexts\Alliance\Membership\Queries\ActiveAllianceScopeQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Progression\Queries\CalculatorQualificationQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionGoalPlannerQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Queries\GovernorProgressionEvidenceSummaryQuery;
use App\Contexts\Intelligence\Evidence\Services\GovernorProgressionEvidenceSchemaRegistry;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Contexts\Intelligence\Roster\Queries\GovernorProgressionObservationQuery;
use App\Contexts\Operations\Rallies\Models\PlayerFormation;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GovernorProgressionController extends Controller
{
    public function __invoke(
        Request $request,
        PlayerContext $context,
        ProgressionDatasetQuery $datasets,
        ProgressionGoalPlannerQuery $goalPlanner,
        CalculatorQualificationQuery $calculatorQualifications,
        ActiveAllianceScopeQuery $allianceScopes,
        RosterEntryQuery $rosterEntries,
        AllianceIntelligenceAuthorization $intelligence,
        GovernorProgressionObservationQuery $progressionObservations,
        GovernorProgressionEvidenceSummaryQuery $evidenceSummaries,
        GovernorProgressionEvidenceSchemaRegistry $evidenceSchemas,
    ): Response {
        $player = $context->player();
        $dataset = $datasets->latest();
        $scope = $allianceScopes->findForPlayer($player->playerId, $player->kingdomId);
        $allianceId = $scope?->allianceId;
        $canViewObservations = $allianceId !== null
            && $intelligence->allows($player->playerId, $allianceId, IntelligencePermission::View);
        $canManageObservations = $allianceId !== null
            && $intelligence->allows($player->playerId, $allianceId, IntelligencePermission::KingdomManage);

        $entry = null;
        if ($allianceId !== null && $canViewObservations) {
            $entries = $rosterEntries->forPlayer($allianceId, $player->playerId, 1);
            $entry = $entries[0] ?? null;
        }

        $snapshots = $entry !== null
            ? PlayerSnapshot::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $player->playerId)
                ->orderByDesc('captured_at')
                ->limit(50)
                ->get()
            : collect();

        $progressionObservationState = $entry !== null && $canViewObservations
            ? $progressionObservations->forRosterEntry((string) $allianceId, $entry->rosterEntryId)
            : ['history' => [], 'current' => ['profile' => [], 'heroes' => [], 'governorGear' => [], 'charms' => [], 'completeRosterCapture' => null], 'last_updated_at' => null];
        $evidenceWorkspace = $entry !== null && $canManageObservations
            ? [
                'schemas' => array_map(static function (EvidenceKind $kind) use ($evidenceSchemas): array {
                    $schema = $evidenceSchemas->require($kind);

                    return [
                        'kind' => $kind->value,
                        'version' => $schema->version,
                        'supportedFields' => $schema->supportedFields,
                        'requiredFields' => $schema->requiredFields,
                        'minimumClassificationConfidence' => $schema->minimumClassificationConfidence,
                        'minimumFieldConfidence' => $schema->minimumFieldConfidence,
                        'fixtureCorpus' => $schema->fixtureCorpus,
                        'destinationAction' => $schema->destinationAction,
                    ];
                }, EvidenceKind::governorProgressionCases()),
                'evidence' => $evidenceSummaries->forRosterEntry((string) $allianceId, $entry->rosterEntryId),
            ]
            : ['schemas' => [], 'evidence' => []];

        $plannerFamily = trim((string) $request->query('planner_family', ''));
        $plannerSubject = trim((string) $request->query('planner_subject', ''));
        $plannerTarget = trim((string) $request->query('planner_target', ''));
        $observedCurrent = is_array($progressionObservationState['current'] ?? null)
            ? $progressionObservationState['current']
            : [];
        $planner = $goalPlanner->plan(
            $dataset,
            $observedCurrent,
            $plannerFamily === '' ? null : $plannerFamily,
            $plannerSubject === '' ? null : $plannerSubject,
            $plannerTarget === '' ? null : $plannerTarget,
        );
        $calculatorEligibility = $calculatorQualifications->all($dataset);

        $formations = PlayerFormation::query()
            ->where('player_id', $player->playerId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $user = $request->user();

        return Inertia::render('Kingdom/Progression/Governor', [
            'user' => ['name' => (string) $user?->name, 'email' => (string) $user?->email],
            'governor' => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'gamePlayerId' => $player->gamePlayerId,
                'kingdomNumber' => $player->kingdomNumber,
                'allianceId' => $allianceId,
                'rosterEntryId' => $entry?->rosterEntryId,
            ],
            'dataset' => [
                'id' => $dataset->id,
                'version' => $dataset->datasetVersion,
                'checksum' => $dataset->checksum,
                'heroes' => array_map(static fn (array $hero): array => [
                    'id' => (string) ($hero['id'] ?? ''),
                    'name' => (string) ($hero['name'] ?? ''),
                    'generation' => (int) ($hero['generation'] ?? 0),
                    'rarity' => (string) ($hero['rarity'] ?? ''),
                    'troopClass' => (string) ($hero['troop_class'] ?? ''),
                ], $dataset->heroes),
            ],
            'observationAccess' => [
                'canView' => $canViewObservations,
                'canManage' => $canManageObservations,
            ],
            'observations' => $snapshots->map(static fn (PlayerSnapshot $snapshot): array => [
                'id' => (string) $snapshot->id,
                'capturedAt' => $snapshot->captured_at->toIso8601String(),
                'source' => (string) $snapshot->source,
                'power' => (string) $snapshot->power,
                'progressionLevel' => $snapshot->progression_level,
                'datasetId' => $snapshot->progression_dataset_id,
                'datasetChecksum' => $snapshot->progression_dataset_checksum,
                'heroObservations' => is_array($snapshot->hero_observations) ? $snapshot->hero_observations : [],
            ])->values()->all(),
            'progressionObservationState' => $progressionObservationState,
            'evidenceWorkspace' => $evidenceWorkspace,
            'planner' => $planner,
            'calculatorEligibility' => $calculatorEligibility,
            'loadouts' => $formations->map(static fn (PlayerFormation $formation): array => [
                'id' => (string) $formation->id,
                'name' => (string) $formation->name,
                'infantryPercent' => (int) $formation->infantry_percent,
                'cavalryPercent' => (int) $formation->cavalry_percent,
                'archerPercent' => (int) $formation->archer_percent,
                'heroes' => is_array($formation->heroes) ? $formation->heroes : [],
                'notes' => $formation->notes,
                'isDefault' => (bool) $formation->is_default,
                'datasetId' => $formation->progression_dataset_id,
                'datasetChecksum' => $formation->progression_dataset_checksum,
            ])->values()->all(),
        ]);
    }
}
