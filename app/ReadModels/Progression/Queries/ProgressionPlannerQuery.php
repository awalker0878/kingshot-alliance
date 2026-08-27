<?php

declare(strict_types=1);

namespace App\ReadModels\Progression\Queries;

use App\Contexts\GameWorld\Progression\Queries\CalculatorEligibilityQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionTopologyQuery;
use App\Contexts\GameWorld\Progression\Services\ProgressionCalculator;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use Carbon\CarbonImmutable;

final class ProgressionPlannerQuery
{
    public function __construct(
        private readonly ProgressionTopologyQuery $topology,
        private readonly CalculatorEligibilityQuery $eligibility,
        private readonly ProgressionCalculator $calculator,
    ) {}

    /**
     * @param  array<string,mixed>  $observationState
     * @return array<string,mixed>
     */
    public function compose(
        ProgressionDataset $dataset,
        array $observationState,
        ?string $family,
        ?string $subjectId,
        ?string $targetStateId,
        bool $calculate,
    ): array {
        $families = array_map(function (array $definition) use ($dataset): array {
            $calculatorFamily = $definition['calculatorFamily'];
            $definition['calculator'] = $calculatorFamily !== null
                ? $this->eligibility->forFamily($dataset, $calculatorFamily)->toArray()
                : null;

            return $definition;
        }, $this->topology->families());

        $selectedFamily = $this->findById($families, $family);
        if ($selectedFamily === null) {
            return $this->emptySelection($dataset, $families);
        }

        $subjects = $this->subjects($dataset, $observationState, $family ?? '');
        $selectedSubject = $this->findById($subjects, $subjectId);
        if ($selectedSubject === null) {
            return [
                ...$this->emptySelection($dataset, $families),
                'selectedFamily' => $selectedFamily,
                'subjects' => $subjects,
            ];
        }

        $states = $this->topology->states(
            $dataset,
            $family ?? '',
            (string) $selectedSubject['id'],
            is_array($selectedSubject['context'] ?? null) ? $selectedSubject['context'] : [],
        );
        $current = $this->currentState($dataset, $observationState, $family ?? '', $selectedSubject, $states);
        $target = $this->findById($states, $targetStateId);
        $comparison = $target !== null
            ? $this->topology->compare(
                $states,
                is_string($current['stateId'] ?? null) ? $current['stateId'] : null,
                (string) $target['id'],
            )
            : null;

        $prerequisites = [];
        foreach (is_array($target['prerequisites'] ?? null) ? $target['prerequisites'] : [] as $requirement) {
            if (is_string($requirement) && trim($requirement) !== '') {
                $prerequisites[] = ['label' => $requirement, 'status' => 'unknown'];
            }
        }

        $calculatorFamily = is_string($selectedFamily['calculatorFamily'] ?? null)
            ? $selectedFamily['calculatorFamily']
            : null;
        $calculatorEligibility = $calculatorFamily !== null
            ? $this->eligibility->forFamily($dataset, $calculatorFamily)->toArray()
            : null;
        $calculation = null;
        $datasetCompatible = ($current['datasetStatus'] ?? null) !== 'dataset_mismatch';
        if (
            $calculate
            && $datasetCompatible
            && $calculatorFamily !== null
            && $target !== null
            && is_string($current['stateId'] ?? null)
        ) {
            $calculation = $this->calculator->calculate(
                $dataset,
                $calculatorFamily,
                $current['stateId'],
                (string) $target['id'],
            )->toArray();
        }

        return [
            'dataset' => $this->dataset($dataset),
            'families' => $families,
            'selectedFamily' => $selectedFamily,
            'subjects' => $subjects,
            'selectedSubject' => $selectedSubject,
            'states' => $states,
            'current' => $current,
            'target' => $target,
            'comparison' => $comparison,
            'prerequisites' => $prerequisites,
            'calculator' => $calculatorEligibility,
            'calculation' => $calculation,
            'sources' => $dataset->sources(),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $families
     * @return array<string,mixed>
     */
    private function emptySelection(ProgressionDataset $dataset, array $families): array
    {
        return [
            'dataset' => $this->dataset($dataset),
            'families' => $families,
            'selectedFamily' => null,
            'subjects' => [],
            'selectedSubject' => null,
            'states' => [],
            'current' => [
                'status' => 'unknown',
                'stateId' => null,
                'state' => null,
                'facts' => [],
                'datasetStatus' => 'unlabelled',
                'freshnessStatus' => 'unknown',
                'staleAfterDays' => $this->observationStaleAfterDays(),
                'reason' => 'Select a factual progression family and subject.',
            ],
            'target' => null,
            'comparison' => null,
            'prerequisites' => [],
            'calculator' => null,
            'calculation' => null,
            'sources' => $dataset->sources(),
        ];
    }

    /** @return array<string,mixed> */
    private function dataset(ProgressionDataset $dataset): array
    {
        return [
            'id' => $dataset->id,
            'version' => $dataset->datasetVersion,
            'checksum' => $dataset->checksum,
            'observedAt' => $dataset->observedAt,
            'reviewStatus' => is_string($dataset->release['review_status'] ?? null)
                ? $dataset->release['review_status']
                : 'unknown',
        ];
    }

    /**
     * @param  array<string,mixed>  $observationState
     * @return list<array{id:string,label:string,context:array<string,mixed>}>
     */
    private function subjects(ProgressionDataset $dataset, array $observationState, string $family): array
    {
        $current = is_array($observationState['current'] ?? null) ? $observationState['current'] : [];

        if ($family === 'governor_charms') {
            $charms = is_array($current['charms'] ?? null) ? $current['charms'] : [];
            if ($charms !== []) {
                $subjects = [];
                foreach (array_keys($charms) as $slotId) {
                    if (is_string($slotId)) {
                        $subjects[] = ['id' => $slotId, 'label' => $this->humanize($slotId), 'context' => []];
                    }
                }

                return $subjects;
            }
        }

        if (in_array($family, ['hero_gear_level', 'hero_mastery'], true)) {
            $observed = $this->observedHeroGearSubjects($dataset, $current, $family);
            if ($observed !== []) {
                return $observed;
            }
        }

        return $this->topology->subjects($dataset, $family);
    }

    /**
     * @param  array<string,mixed>  $current
     * @return list<array{id:string,label:string,context:array<string,mixed>}>
     */
    private function observedHeroGearSubjects(ProgressionDataset $dataset, array $current, string $family): array
    {
        $heroes = is_array($current['heroes'] ?? null) ? $current['heroes'] : [];
        $heroNames = [];
        foreach ($dataset->heroes as $hero) {
            if (is_string($hero['id'] ?? null) && is_string($hero['name'] ?? null)) {
                $heroNames[$hero['id']] = $hero['name'];
            }
        }

        $subjects = [];
        foreach ($heroes as $heroId => $hero) {
            if (! is_string($heroId) || ! is_array($hero)) {
                continue;
            }
            $gear = is_array($hero['gear'] ?? null) ? $hero['gear'] : [];
            foreach ($gear as $slotId => $facts) {
                if (! is_string($slotId) || ! is_array($facts)) {
                    continue;
                }
                $quality = $this->factValue($facts['quality'] ?? null);
                $level = $this->factValue($facts['level'] ?? null);
                $mastery = $this->factValue($facts['mastery_level'] ?? null);
                if ($family === 'hero_gear_level' && (! is_string($quality) || ! is_numeric($level))) {
                    continue;
                }
                if ($family === 'hero_mastery' && ! is_numeric($mastery)) {
                    continue;
                }
                $subjects[] = [
                    'id' => $heroId.'::'.$slotId,
                    'label' => ($heroNames[$heroId] ?? $heroId).' · '.$this->humanize($slotId),
                    'context' => [
                        'heroId' => $heroId,
                        'slotId' => $slotId,
                        'quality' => is_string($quality) ? mb_strtolower($quality) : null,
                    ],
                ];
            }
        }

        return $subjects;
    }

    /**
     * @param  array<string,mixed>  $observationState
     * @param  array<string,mixed>  $subject
     * @param  list<array<string,mixed>>  $states
     * @return array<string,mixed>
     */
    private function currentState(
        ProgressionDataset $dataset,
        array $observationState,
        string $family,
        array $subject,
        array $states,
    ): array {
        $current = is_array($observationState['current'] ?? null) ? $observationState['current'] : [];
        $facts = [];
        $stateId = null;

        if ($family === 'governor_gear') {
            $slot = is_array($current['governorGear'][$subject['id']] ?? null)
                ? $current['governorGear'][$subject['id']]
                : [];
            $facts = $slot;
            $quality = $this->factValue($slot['quality'] ?? null);
            $star = $this->factValue($slot['star'] ?? null);
            if (is_string($quality) && ($star === null || is_numeric($star))) {
                foreach ($states as $state) {
                    $tier = $state['attributes']['tier'] ?? null;
                    $stars = $state['attributes']['stars'] ?? null;
                    if (
                        is_string($tier)
                        && mb_strtolower(trim($tier)) === mb_strtolower(trim($quality))
                        && is_int($stars)
                        && $stars === (int) ($star ?? 0)
                    ) {
                        $stateId = (string) $state['id'];
                        break;
                    }
                }
            }
        } elseif ($family === 'governor_charms') {
            $slot = is_array($current['charms'][$subject['id']] ?? null) ? $current['charms'][$subject['id']] : [];
            $facts = $slot;
            $level = $this->factValue($slot['level'] ?? null);
            if (is_numeric($level)) {
                $stateId = 'level:'.(int) $level;
            }
        } elseif ($family === 'hero_level') {
            $hero = is_array($current['heroes'][$subject['id']] ?? null) ? $current['heroes'][$subject['id']] : [];
            $heroFacts = is_array($hero['facts'] ?? null) ? $hero['facts'] : [];
            $facts = $heroFacts;
            $level = $this->factValue($heroFacts['level'] ?? null);
            if (is_numeric($level)) {
                $stateId = 'level:'.(int) $level;
            }
        } elseif (in_array($family, ['hero_gear_level', 'hero_mastery'], true)) {
            $heroId = $subject['context']['heroId'] ?? null;
            $slotId = $subject['context']['slotId'] ?? null;
            $slot = is_string($heroId)
                && is_string($slotId)
                && is_array($current['heroes'][$heroId]['gear'][$slotId] ?? null)
                ? $current['heroes'][$heroId]['gear'][$slotId]
                : [];
            $facts = $slot;
            $value = $this->factValue($slot[$family === 'hero_mastery' ? 'mastery_level' : 'level'] ?? null);
            if (is_numeric($value)) {
                $stateId = 'level:'.(int) $value;
            }
        }

        if ($facts === []) {
            return [
                'status' => 'unknown',
                'stateId' => null,
                'state' => null,
                'facts' => [],
                'datasetStatus' => 'unlabelled',
                'freshnessStatus' => 'unknown',
                'staleAfterDays' => $this->observationStaleAfterDays(),
                'reason' => 'No authorized observed current state is available for this subject.',
            ];
        }

        $observationDatasetId = $this->firstFactString($facts, 'datasetId');
        $observationDatasetChecksum = $this->firstFactString($facts, 'datasetChecksum');
        $datasetStatus = $this->datasetStatus($dataset, $observationDatasetId, $observationDatasetChecksum);
        $capturedAt = $this->latestCapturedAt($facts);
        $freshness = $this->observationFreshness($capturedAt);

        $state = $this->findById($states, $stateId);
        if ($state === null) {
            return [
                'status' => 'observed_unresolved',
                'stateId' => null,
                'state' => null,
                'facts' => $facts,
                'capturedAt' => $capturedAt,
                ...$freshness,
                'observationDatasetId' => $observationDatasetId,
                'observationDatasetChecksum' => $observationDatasetChecksum,
                'datasetStatus' => $datasetStatus,
                'reason' => 'An observation exists, but it cannot be mapped exactly to a state in this pinned factual release.',
            ];
        }

        return [
            'status' => 'observed',
            'stateId' => $stateId,
            'state' => $state,
            'facts' => $facts,
            'capturedAt' => $capturedAt,
            ...$freshness,
            'observationDatasetId' => $observationDatasetId,
            'observationDatasetChecksum' => $observationDatasetChecksum,
            'datasetStatus' => $datasetStatus,
            'reason' => null,
        ];
    }

    /** @return array{freshnessStatus:string,staleAfterDays:int} */
    private function observationFreshness(?string $capturedAt): array
    {
        $staleAfterDays = $this->observationStaleAfterDays();
        if ($capturedAt === null) {
            return ['freshnessStatus' => 'unknown', 'staleAfterDays' => $staleAfterDays];
        }

        $captured = CarbonImmutable::parse($capturedAt)->utc();
        $cutoff = CarbonImmutable::now('UTC')->subDays($staleAfterDays);

        return [
            'freshnessStatus' => $captured->lt($cutoff) ? 'stale_observation' : 'fresh',
            'staleAfterDays' => $staleAfterDays,
        ];
    }

    private function observationStaleAfterDays(): int
    {
        $configured = config('intelligence.progression.observation_stale_after_days', 30);

        return is_int($configured) && $configured > 0 ? $configured : 30;
    }

    private function datasetStatus(
        ProgressionDataset $dataset,
        ?string $observationDatasetId,
        ?string $observationDatasetChecksum,
    ): string {
        if ($observationDatasetId === null && $observationDatasetChecksum === null) {
            return 'unlabelled';
        }

        if ($observationDatasetId === $dataset->id && $observationDatasetChecksum === $dataset->checksum) {
            return 'matched';
        }

        return 'dataset_mismatch';
    }

    private function factValue(mixed $fact): mixed
    {
        return is_array($fact) && array_key_exists('value', $fact) ? $fact['value'] : null;
    }

    /** @param array<string,mixed> $facts */
    private function latestCapturedAt(array $facts): ?string
    {
        $values = [];
        foreach ($facts as $fact) {
            if (is_array($fact) && is_string($fact['capturedAt'] ?? null)) {
                $values[] = $fact['capturedAt'];
            }
        }
        rsort($values);

        return $values[0] ?? null;
    }

    /** @param array<string,mixed> $facts */
    private function firstFactString(array $facts, string $key): ?string
    {
        foreach ($facts as $fact) {
            if (is_array($fact) && is_string($fact[$key] ?? null)) {
                return $fact[$key];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return array<string,mixed>|null
     */
    private function findById(array $items, ?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }
}
