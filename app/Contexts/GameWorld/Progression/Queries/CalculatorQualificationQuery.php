<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Queries;

use App\Contexts\GameWorld\Progression\Enums\CalculatorEligibilityStatus;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use JsonException;
use RuntimeException;

final class CalculatorQualificationQuery
{
    private const FILE = 'resources/data/progression/calculator-qualification.json';

    /** @var list<string> */
    private const FAMILIES = [
        'governor_gear',
        'governor_charms',
        'hero_gear',
        'troop_training_promotion',
        'research',
        'buildings',
    ];

    /** @var list<string> */
    private const GATES = [
        'source_coverage',
        'authority_corroboration',
        'boundary_clarity',
        'conflict_closure',
        'immutable_release',
        'pure_typed_calculation',
        'golden_fixtures',
        'provenance_result',
        'historical_stability',
        'independent_unlock',
    ];

    /**
     * @return list<array{
     *   family:string,
     *   status:string,
     *   reason:string,
     *   reviewedAt:string,
     *   datasetId:string,
     *   datasetVersion:string,
     *   datasetChecksum:string,
     *   sourceIds:list<string>,
     *   gates:array<string,array{status:string,reason:string}>
     * }>
     */
    public function all(ProgressionDataset $dataset): array
    {
        $document = $this->document();
        if (($document['evaluated_dataset_id'] ?? null) !== $dataset->id
            || ($document['evaluated_dataset_version'] ?? null) !== $dataset->datasetVersion) {
            throw new RuntimeException('Calculator qualification report does not match the active progression release.');
        }

        $reviewedAt = $document['reviewed_at'] ?? null;
        if (! is_string($reviewedAt) || $reviewedAt === '') {
            throw new RuntimeException('Calculator qualification report is missing its review date.');
        }

        $reports = $document['reports'] ?? null;
        if (! is_array($reports)) {
            throw new RuntimeException('Calculator qualification report list is invalid.');
        }

        $normalized = [];
        foreach ($reports as $report) {
            if (! is_array($report)) {
                throw new RuntimeException('Calculator qualification report contains an invalid family entry.');
            }
            $family = $report['family'] ?? null;
            $status = $report['status'] ?? null;
            $reason = $report['reason'] ?? null;
            $sourceIds = $report['source_ids'] ?? null;
            $gates = $report['gates'] ?? null;
            if (! is_string($family)
                || ! in_array($family, self::FAMILIES, true)
                || isset($normalized[$family])
                || ! is_string($status)
                || CalculatorEligibilityStatus::tryFrom($status) === null
                || ! is_string($reason)
                || $reason === ''
                || ! is_array($sourceIds)
                || ! is_array($gates)) {
                throw new RuntimeException('Calculator qualification family entry does not satisfy the required schema.');
            }

            $normalizedGates = [];
            foreach (self::GATES as $gate) {
                $value = $gates[$gate] ?? null;
                if (! is_array($value)
                    || ! in_array($value['status'] ?? null, ['pass', 'fail'], true)
                    || ! is_string($value['reason'] ?? null)
                    || $value['reason'] === '') {
                    throw new RuntimeException('Calculator qualification report is missing a required gate result: '.$gate);
                }
                $normalizedGates[$gate] = [
                    'status' => $value['status'],
                    'reason' => $value['reason'],
                ];
            }

            if ($status === CalculatorEligibilityStatus::CalculatorReady->value
                && array_any($normalizedGates, static fn (array $gate): bool => $gate['status'] !== 'pass')) {
                throw new RuntimeException('A calculator family cannot be ready while a qualification gate is failing.');
            }

            $normalized[$family] = [
                'family' => $family,
                'status' => $status,
                'reason' => $reason,
                'reviewedAt' => $reviewedAt,
                'datasetId' => $dataset->id,
                'datasetVersion' => $dataset->datasetVersion,
                'datasetChecksum' => $dataset->checksum,
                'sourceIds' => $this->stringList($sourceIds),
                'gates' => $normalizedGates,
            ];
        }

        foreach (self::FAMILIES as $family) {
            if (! isset($normalized[$family])) {
                throw new RuntimeException('Calculator qualification report omitted required family: '.$family);
            }
        }
        if (count($normalized) !== count(self::FAMILIES)) {
            throw new RuntimeException('Calculator qualification report contains an unsupported family.');
        }

        return array_map(static fn (string $family): array => $normalized[$family], self::FAMILIES);
    }

    /** @return array<string,mixed> */
    public function forFamily(ProgressionDataset $dataset, string $family): array
    {
        foreach ($this->all($dataset) as $report) {
            if ($report['family'] === $family) {
                return $report;
            }
        }

        throw new RuntimeException('Calculator qualification family is not defined: '.$family);
    }

    /** @return array<string,mixed> */
    private function document(): array
    {
        $raw = file_get_contents(base_path(self::FILE));
        if (! is_string($raw)) {
            throw new RuntimeException('Calculator qualification report cannot be read.');
        }

        try {
            $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Calculator qualification report contains invalid JSON.', previous: $exception);
        }

        if (! is_array($document) || ($document['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Calculator qualification report has an unsupported schema.');
        }

        return $document;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', array_filter($value, 'is_string'))));
    }
}
