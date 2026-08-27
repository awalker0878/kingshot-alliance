<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Services;

use App\Contexts\GameWorld\Progression\Enums\CalculatorEligibilityStatus;
use App\Contexts\GameWorld\Progression\Enums\ProgressionCalculationStatus;
use App\Contexts\GameWorld\Progression\Queries\CalculatorEligibilityQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionTopologyQuery;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionCalculationResult;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;

final class ProgressionCalculator
{
    public function __construct(
        private readonly CalculatorEligibilityQuery $eligibility,
        private readonly ProgressionTopologyQuery $topology,
    ) {}

    public function calculate(
        ProgressionDataset $dataset,
        string $family,
        string $currentStateId,
        string $targetStateId,
    ): ProgressionCalculationResult {
        $eligibility = $this->eligibility->forFamily($dataset, $family);
        if ($eligibility->status !== CalculatorEligibilityStatus::CalculatorReady) {
            return $this->result(
                ProgressionCalculationStatus::Unavailable,
                $dataset,
                $family,
                $currentStateId,
                $targetStateId,
                [],
                [],
                $eligibility->calculationVersion,
                $eligibility->sourceIds,
                [],
                $eligibility->reason,
            );
        }

        return match ($family) {
            'governor_gear' => $this->governorGear($dataset, $currentStateId, $targetStateId, $eligibility->calculationVersion),
            'governor_charms' => $this->governorCharms($dataset, $currentStateId, $targetStateId, $eligibility->calculationVersion),
            default => $this->result(
                ProgressionCalculationStatus::Unavailable,
                $dataset,
                $family,
                $currentStateId,
                $targetStateId,
                [],
                [],
                $eligibility->calculationVersion,
                $eligibility->sourceIds,
                [],
                'The qualified family has no registered calculation implementation.',
            ),
        };
    }

    private function governorGear(ProgressionDataset $dataset, string $currentStateId, string $targetStateId, ?string $version): ProgressionCalculationResult
    {
        $states = $this->topology->states($dataset, 'governor_gear', 'gear');
        $comparison = $this->topology->compare($states, $currentStateId, $targetStateId);
        if ($comparison['status'] !== 'comparable') {
            return $this->result(ProgressionCalculationStatus::Invalid, $dataset, 'governor_gear', $currentStateId, $targetStateId, [], [], $version, [], [], (string) ($comparison['reason'] ?? 'Invalid progression range.'));
        }

        $currentOrdinal = (int) ($comparison['current']['ordinal'] ?? -1);
        $targetOrdinal = (int) ($comparison['target']['ordinal'] ?? -1);
        $document = $dataset->catalogue('governor_gear');
        $rows = is_array($document['data']['upgradeSteps'] ?? null) ? $document['data']['upgradeSteps'] : [];
        $totals = ['satin' => 0, 'gilded_threads' => 0, 'artisans_vision' => 0];
        $sourceIds = [];
        $transitionIds = [];

        for ($index = $currentOrdinal + 1; $index <= $targetOrdinal; $index++) {
            $row = $rows[$index] ?? null;
            $materials = is_array($row) && is_array($row['materials'] ?? null) ? $row['materials'] : null;
            if ($materials === null) {
                return $this->result(ProgressionCalculationStatus::Unavailable, $dataset, 'governor_gear', $currentStateId, $targetStateId, $transitionIds, [], $version, array_values(array_keys($sourceIds)), [], 'A required canonical Governor Gear transition row is unavailable.');
            }
            foreach (array_keys($totals) as $resource) {
                if (! is_int($materials[$resource] ?? null)) {
                    return $this->result(ProgressionCalculationStatus::Unavailable, $dataset, 'governor_gear', $currentStateId, $targetStateId, $transitionIds, [], $version, array_values(array_keys($sourceIds)), [], 'A required Governor Gear resource value is unknown.');
                }
                $totals[$resource] += $materials[$resource];
            }
            foreach (is_array($row['source_ids'] ?? null) ? $row['source_ids'] : [] as $sourceId) {
                if (is_string($sourceId)) {
                    $sourceIds[$sourceId] = true;
                }
            }
            $transitionIds[] = 'step:'.$index;
        }

        return $this->result(
            ProgressionCalculationStatus::Calculated,
            $dataset,
            'governor_gear',
            $currentStateId,
            $targetStateId,
            $transitionIds,
            [
                'satin' => ['label' => 'Satin', 'quantity' => $totals['satin'], 'unit' => 'item'],
                'gilded_threads' => ['label' => 'Gilded Threads', 'quantity' => $totals['gilded_threads'], 'unit' => 'item'],
                'artisans_vision' => ['label' => "Artisan's Vision", 'quantity' => $totals['artisans_vision'], 'unit' => 'item'],
            ],
            $version,
            array_values(array_keys($sourceIds)),
            ['Each canonical upgradeSteps row is the cost to enter that state from the immediately preceding state.', 'Cumulative stat and power fields are not treated as resource costs.'],
        );
    }

    private function governorCharms(ProgressionDataset $dataset, string $currentStateId, string $targetStateId, ?string $version): ProgressionCalculationResult
    {
        $states = $this->topology->states($dataset, 'governor_charms', 'charm');
        $comparison = $this->topology->compare($states, $currentStateId, $targetStateId);
        if ($comparison['status'] !== 'comparable') {
            return $this->result(ProgressionCalculationStatus::Invalid, $dataset, 'governor_charms', $currentStateId, $targetStateId, [], [], $version, [], [], (string) ($comparison['reason'] ?? 'Invalid progression range.'));
        }

        $currentLevel = (int) ($comparison['current']['attributes']['level'] ?? -1);
        $targetLevel = (int) ($comparison['target']['attributes']['level'] ?? -1);
        $document = $dataset->catalogue('governor_charms');
        $rows = is_array($document['data']['charmLevels'] ?? null) ? $document['data']['charmLevels'] : [];
        $byLevel = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_int($row['level'] ?? null)) {
                $byLevel[$row['level']] = $row;
            }
        }
        $guides = 0;
        $designs = 0;
        $sourceIds = [];
        $transitionIds = [];
        for ($level = $currentLevel + 1; $level <= $targetLevel; $level++) {
            $row = $byLevel[$level] ?? null;
            if (! is_array($row) || ! is_int($row['charmGuides'] ?? null) || ! is_int($row['charmDesigns'] ?? null)) {
                return $this->result(ProgressionCalculationStatus::Unavailable, $dataset, 'governor_charms', $currentStateId, $targetStateId, $transitionIds, [], $version, array_values(array_keys($sourceIds)), [], 'A required Governor Charm transition row is unavailable.');
            }
            $guides += $row['charmGuides'];
            $designs += $row['charmDesigns'];
            foreach (is_array($row['source_ids'] ?? null) ? $row['source_ids'] : [] as $sourceId) {
                if (is_string($sourceId)) {
                    $sourceIds[$sourceId] = true;
                }
            }
            $transitionIds[] = 'level:'.$level;
        }

        return $this->result(
            ProgressionCalculationStatus::Calculated,
            $dataset,
            'governor_charms',
            $currentStateId,
            $targetStateId,
            $transitionIds,
            [
                'charm_guides' => ['label' => 'Charm Guides', 'quantity' => $guides, 'unit' => 'item'],
                'charm_designs' => ['label' => 'Charm Designs', 'quantity' => $designs, 'unit' => 'item'],
            ],
            $version,
            array_values(array_keys($sourceIds)),
            ['Each canonical charmLevels row is the cost to enter that level from the immediately preceding level.', 'Level 0 is an explicit unupgraded planning boundary and is never inferred from missing observation data.'],
        );
    }

    /**
     * @param list<string> $transitionIds
     * @param array<string,array{label:string,quantity:int|float,unit:string}> $resources
     * @param list<string> $sourceIds
     * @param list<string> $assumptions
     */
    private function result(
        ProgressionCalculationStatus $status,
        ProgressionDataset $dataset,
        string $family,
        string $currentStateId,
        string $targetStateId,
        array $transitionIds,
        array $resources,
        ?string $calculationVersion,
        array $sourceIds,
        array $assumptions,
        ?string $reason = null,
    ): ProgressionCalculationResult {
        return new ProgressionCalculationResult(
            status: $status,
            family: $family,
            currentStateId: $currentStateId,
            targetStateId: $targetStateId,
            transitionIds: $transitionIds,
            resources: $resources,
            datasetId: $dataset->id,
            datasetVersion: $dataset->datasetVersion,
            datasetChecksum: $dataset->checksum,
            calculationVersion: $calculationVersion,
            sourceIds: $sourceIds,
            assumptions: $assumptions,
            reason: $reason,
        );
    }
}
