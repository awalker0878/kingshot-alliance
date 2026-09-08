<?php

declare(strict_types=1);

namespace App\ReadModels\Progression\Queries;

use App\Contexts\GameWorld\Progression\Queries\ProgressionTopologyQuery;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;

final readonly class ProgressionPrerequisiteEvaluator
{
    private const LEVEL_REQUIREMENT = '/^(?<name>.+?)\s+Lv\.?\s*(?<level>\d+)$/iu';

    public function __construct(private ProgressionTopologyQuery $topology) {}

    /**
     * @param  array<string,mixed>  $observationState
     * @param  list<string>  $requirements
     * @return list<array<string,mixed>>
     */
    public function evaluate(ProgressionDataset $dataset, array $observationState, array $requirements): array
    {
        $results = [];
        foreach ($requirements as $requirement) {
            $label = trim($requirement);
            if ($label === '') {
                continue;
            }
            $results[] = $this->evaluateOne($dataset, $observationState, $label);
        }

        return $results;
    }

    /** @param array<string,mixed> $observationState
     * @return array<string,mixed>
     */
    private function evaluateOne(ProgressionDataset $dataset, array $observationState, string $label): array
    {
        if (preg_match(self::LEVEL_REQUIREMENT, $label, $matches) !== 1) {
            return $this->result($label, 'unsupported', reason: 'The source prerequisite is preserved but is not a supported level requirement.');
        }

        $name = trim((string) $matches['name']);
        $requiredLevel = (int) $matches['level'];
        if ($requiredLevel < 1) {
            return $this->result($label, 'unsupported', requiredLevel: $requiredLevel, reason: 'The source prerequisite does not reference a positive level.');
        }

        foreach ([
            ['family' => 'academy_research', 'projection' => 'academyResearch'],
            ['family' => 'buildings', 'projection' => 'buildings'],
        ] as $candidate) {
            $subject = $this->subjectByName($dataset, $candidate['family'], $name);
            if ($subject === null) {
                continue;
            }

            if ($this->hasUnresolvedConflict($dataset, $candidate['family'], $name)) {
                return $this->result(
                    $label,
                    'source_conflict',
                    $candidate['family'],
                    (string) $subject['id'],
                    $requiredLevel,
                    reason: 'A source conflict affects this prerequisite boundary.',
                );
            }

            $current = is_array($observationState['current'] ?? null) ? $observationState['current'] : [];
            $facts = is_array($current[$candidate['projection']][$subject['id']] ?? null)
                ? $current[$candidate['projection']][$subject['id']]
                : [];
            if ($facts === []) {
                return $this->result(
                    $label,
                    'unknown_current_state',
                    $candidate['family'],
                    (string) $subject['id'],
                    $requiredLevel,
                    reason: 'No authorized observed state is available for this prerequisite.',
                );
            }

            $datasetId = $this->firstFactString($facts, 'datasetId');
            $datasetChecksum = $this->firstFactString($facts, 'datasetChecksum');
            if (($datasetId !== null || $datasetChecksum !== null)
                && ($datasetId !== $dataset->id || $datasetChecksum !== $dataset->checksum)) {
                return $this->result(
                    $label,
                    'dataset_mismatch',
                    $candidate['family'],
                    (string) $subject['id'],
                    $requiredLevel,
                    reason: 'The observed prerequisite state is pinned to a different factual dataset.',
                );
            }

            $observedLevel = $this->factValue($facts['level'] ?? null);
            if (! is_numeric($observedLevel)) {
                return $this->result(
                    $label,
                    'unknown_current_state',
                    $candidate['family'],
                    (string) $subject['id'],
                    $requiredLevel,
                    reason: 'The prerequisite was observed, but no numeric level can be resolved.',
                );
            }
            $observedLevel = (int) $observedLevel;

            return $this->result(
                $label,
                $observedLevel >= $requiredLevel ? 'satisfied' : 'not_satisfied',
                $candidate['family'],
                (string) $subject['id'],
                $requiredLevel,
                $observedLevel,
            );
        }

        return $this->result(
            $label,
            'unobservable',
            requiredLevel: $requiredLevel,
            reason: 'The source names an external prerequisite that is not mapped to an observable Progression family.',
        );
    }

    /** @return array{id:string,label:string,context:array<string,mixed>}|null */
    private function subjectByName(ProgressionDataset $dataset, string $family, string $name): ?array
    {
        $needle = mb_strtolower(trim($name));
        foreach ($this->topology->subjects($dataset, $family) as $subject) {
            if ($needle === mb_strtolower((string) ($subject['id'] ?? ''))
                || $needle === mb_strtolower((string) ($subject['label'] ?? ''))) {
                return $subject;
            }
        }

        return null;
    }

    private function hasUnresolvedConflict(ProgressionDataset $dataset, string $family, string $name): bool
    {
        $needle = mb_strtolower($name);
        foreach ($dataset->conflicts() as $conflict) {
            if (($conflict['family'] ?? null) !== $family || isset($conflict['resolution_status'])) {
                continue;
            }
            $haystack = mb_strtolower((string) ($conflict['id'] ?? '').' '.(string) ($conflict['description'] ?? ''));
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function factValue(mixed $fact): mixed
    {
        return is_array($fact) && array_key_exists('value', $fact) ? $fact['value'] : null;
    }

    /** @param array<string,mixed> $facts */
    private function firstFactString(array $facts, string $key): ?string
    {
        foreach ($facts as $fact) {
            if (is_array($fact) && is_string($fact[$key] ?? null) && $fact[$key] !== '') {
                return $fact[$key];
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function result(
        string $label,
        string $status,
        ?string $family = null,
        ?string $subjectId = null,
        ?int $requiredLevel = null,
        ?int $observedLevel = null,
        ?string $reason = null,
    ): array {
        return [
            'label' => $label,
            'status' => $status,
            'family' => $family,
            'subjectId' => $subjectId,
            'requiredLevel' => $requiredLevel,
            'observedLevel' => $observedLevel,
            'reason' => $reason,
        ];
    }
}
