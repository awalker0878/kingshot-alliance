<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutAnalyzer;

final readonly class EventTerritoryCommandQuery
{
    public function __construct(
        private PublishedEventTerritoryRevisionQuery $published,
        private KingdomMapDatasetQuery $datasets,
        private TerritoryLayoutAnalyzer $analyzer,
    ) {}

    /**
     * @return array{
     *     attachmentCount:int,
     *     currentDraftDiffers:bool,
     *     violationCount:int,
     *     warningCount:int,
     *     references:list<array<string,mixed>>
     * }
     */
    public function forOccurrence(string $actorPlayerId, EventOccurrence $occurrence): array
    {
        $references = $this->published->forOccurrence($actorPlayerId, $occurrence);
        if ($references === []) {
            return [
                'attachmentCount' => 0,
                'currentDraftDiffers' => false,
                'violationCount' => 0,
                'warningCount' => 0,
                'references' => [],
            ];
        }

        $revisionIds = array_values(array_unique(array_map(
            static fn (array $reference): string => (string) $reference['revisionId'],
            $references,
        )));
        $revisions = TerritoryPlanRevision::query()
            ->whereIn('id', $revisionIds)
            ->get()
            ->keyBy('id');
        $planIds = array_values(array_unique(array_map(
            static fn (array $reference): string => (string) $reference['planId'],
            $references,
        )));
        $plans = TerritoryPlan::query()
            ->whereIn('id', $planIds)
            ->get()
            ->keyBy('id');
        $currentDraftDiffers = false;
        $violationCount = 0;
        $warningCount = 0;

        foreach ($references as $reference) {
            $revision = $revisions->get((string) $reference['revisionId']);
            $plan = $plans->get((string) $reference['planId']);
            if (! $revision instanceof TerritoryPlanRevision || ! $plan instanceof TerritoryPlan) {
                continue;
            }

            $snapshot = $revision->snapshot;
            $snapshotPlan = is_array($snapshot['plan'] ?? null) ? $snapshot['plan'] : [];
            $publishedHeadRevision = (int) ($snapshotPlan['head_revision'] ?? 0);
            if ((int) $plan->revision !== $publishedHeadRevision) {
                $currentDraftDiffers = true;
            }

            $objects = [];
            foreach (is_array($snapshot['objects'] ?? null) ? $snapshot['objects'] : [] as $object) {
                if (! is_array($object)) {
                    continue;
                }

                $key = $object['key'] ?? null;
                $type = $object['type'] ?? null;
                $allianceKey = $object['alliance_key'] ?? null;
                $x = $object['x'] ?? null;
                $y = $object['y'] ?? null;
                if (
                    ! is_string($key)
                    || ! is_string($type)
                    || ! is_string($allianceKey)
                    || ! is_numeric($x)
                    || ! is_numeric($y)
                ) {
                    continue;
                }

                $objects[] = [
                    'key' => $key,
                    'type' => $type,
                    'x' => (int) $x,
                    'y' => (int) $y,
                    'alliance_key' => $allianceKey,
                ];
            }

            $preferences = is_array($snapshotPlan['planning_preferences'] ?? null)
                ? $snapshotPlan['planning_preferences']
                : [];
            $dataset = $this->datasets->require(
                (string) $revision->map_dataset_id,
                (string) $revision->map_dataset_checksum,
            );
            $analysis = $this->analyzer->analyze($dataset, $objects, $preferences);
            foreach (
                is_array($analysis['alliances'] ?? null) ? $analysis['alliances'] : []
                as $allianceAnalysis
            ) {
                if (! is_array($allianceAnalysis)) {
                    continue;
                }

                $violationCount += (int) ($allianceAnalysis['violation_count'] ?? 0);
                $warningCount += (int) ($allianceAnalysis['warning_count'] ?? 0);
            }
        }

        return [
            'attachmentCount' => count($references),
            'currentDraftDiffers' => $currentDraftDiffers,
            'violationCount' => $violationCount,
            'warningCount' => $warningCount,
            'references' => $references,
        ];
    }
}
