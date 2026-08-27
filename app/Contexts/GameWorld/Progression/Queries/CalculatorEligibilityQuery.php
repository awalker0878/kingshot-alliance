<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Queries;

use App\Contexts\GameWorld\Progression\Enums\CalculatorEligibilityStatus;
use App\Contexts\GameWorld\Progression\ValueObjects\CalculatorEligibility;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use JsonException;
use RuntimeException;

final class CalculatorEligibilityQuery
{
    private const DIRECTORY = 'resources/data/progression/calculator-qualifications';

    /** @var array<string,string> */
    private const IMPLEMENTED_CALCULATORS = [
        'governor_gear' => 'governor-gear-v1',
        'governor_charms' => 'governor-charms-v1',
    ];

    /** @return array<string,CalculatorEligibility> */
    public function all(ProgressionDataset $dataset): array
    {
        if (! $this->hasReport($dataset)) {
            return [];
        }

        [$report, $checksum] = $this->report($dataset);
        $result = [];

        foreach ($report['families'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $eligibility = $this->eligibility($dataset, $row, $checksum);
            $result[$eligibility->family] = $eligibility;
        }

        ksort($result);

        return $result;
    }

    public function forFamily(ProgressionDataset $dataset, string $family): CalculatorEligibility
    {
        if (! $this->hasReport($dataset)) {
            return new CalculatorEligibility(
                family: $family,
                status: CalculatorEligibilityStatus::EvidenceReview,
                reason: 'No calculator evidence qualification report exists for this pinned progression dataset. Historical factual planning remains available, but calculation is unavailable.',
                datasetId: $dataset->id,
                datasetVersion: $dataset->datasetVersion,
                datasetChecksum: $dataset->checksum,
                qualificationReportChecksum: '',
                qualificationStatus: 'not_reviewed',
                calculationVersion: null,
                sourceIds: [],
                units: [],
                gates: [],
                blockers: ['qualification_report_missing'],
            );
        }

        $all = $this->all($dataset);
        if (isset($all[$family])) {
            return $all[$family];
        }

        return new CalculatorEligibility(
            family: $family,
            status: CalculatorEligibilityStatus::Unsupported,
            reason: 'No calculator evidence qualification contract exists for this progression family.',
            datasetId: $dataset->id,
            datasetVersion: $dataset->datasetVersion,
            datasetChecksum: $dataset->checksum,
            qualificationReportChecksum: '',
            qualificationStatus: 'unsupported',
            calculationVersion: null,
            sourceIds: [],
            units: [],
            gates: [],
            blockers: ['unsupported_family'],
        );
    }

    private function hasReport(ProgressionDataset $dataset): bool
    {
        return is_file($this->reportPath($dataset));
    }

    private function reportPath(ProgressionDataset $dataset): string
    {
        return base_path(self::DIRECTORY.'/'.$dataset->datasetVersion.'.json');
    }

    /** @return array{0:array<string,mixed>,1:string} */
    private function report(ProgressionDataset $dataset): array
    {
        $raw = file_get_contents($this->reportPath($dataset));
        if (! is_string($raw)) {
            throw new RuntimeException('Calculator qualification report is unavailable for progression dataset '.$dataset->datasetVersion.'.');
        }

        try {
            $report = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Calculator qualification report contains invalid JSON.', previous: $exception);
        }

        if (! is_array($report)
            || ($report['schema_version'] ?? null) !== 1
            || ($report['dataset_id'] ?? null) !== $dataset->id
            || ($report['dataset_version'] ?? null) !== $dataset->datasetVersion
            || ! is_array($report['families'] ?? null)) {
            throw new RuntimeException('Calculator qualification report does not match the selected progression dataset.');
        }

        $seen = [];
        $registeredSources = [];
        foreach ($dataset->sources() as $source) {
            if (is_string($source['id'] ?? null)) {
                $registeredSources[$source['id']] = true;
            }
        }

        foreach ($report['families'] as $row) {
            if (! is_array($row)
                || ! is_string($row['family'] ?? null)
                || ! is_string($row['qualification_status'] ?? null)
                || ! is_string($row['reason'] ?? null)
                || ! is_array($row['source_ids'] ?? null)
                || ! is_array($row['units'] ?? null)
                || ! is_array($row['gates'] ?? null)
                || ! is_array($row['blockers'] ?? null)
                || ! is_array($row['golden_fixture_ids'] ?? null)) {
                throw new RuntimeException('Calculator qualification family row is invalid.');
            }
            if (isset($seen[$row['family']])) {
                throw new RuntimeException('Calculator qualification families must be unique.');
            }
            $seen[$row['family']] = true;
            foreach ($row['source_ids'] as $sourceId) {
                if (! is_string($sourceId) || ! isset($registeredSources[$sourceId])) {
                    throw new RuntimeException('Calculator qualification references an unregistered progression source.');
                }
            }
            foreach ($row['gates'] as $gate => $passed) {
                if (! is_string($gate) || ! is_bool($passed)) {
                    throw new RuntimeException('Calculator qualification gate values must be boolean.');
                }
            }
        }

        foreach (['governor_gear', 'governor_charms', 'hero_gear_mastery', 'troop_training_promotion', 'research', 'buildings_truegold'] as $family) {
            if (! isset($seen[$family])) {
                throw new RuntimeException('Calculator qualification report omitted required family: '.$family);
            }
        }

        return [$report, hash('sha256', $raw)];
    }

    /** @param array<string,mixed> $row */
    private function eligibility(ProgressionDataset $dataset, array $row, string $reportChecksum): CalculatorEligibility
    {
        $family = (string) $row['family'];
        $qualificationStatus = (string) $row['qualification_status'];
        $calculationVersion = is_string($row['calculation_version'] ?? null) ? $row['calculation_version'] : null;
        $implementedVersion = self::IMPLEMENTED_CALCULATORS[$family] ?? null;

        $status = match ($qualificationStatus) {
            'qualified' => $implementedVersion !== null && $calculationVersion === $implementedVersion
                ? CalculatorEligibilityStatus::CalculatorReady
                : CalculatorEligibilityStatus::QualifiedPendingImplementation,
            'evidence_review' => CalculatorEligibilityStatus::EvidenceReview,
            'source_gap' => CalculatorEligibilityStatus::SourceGap,
            'evidence_conflict' => CalculatorEligibilityStatus::EvidenceConflict,
            default => CalculatorEligibilityStatus::EvidenceIncomplete,
        };

        /** @var list<string> $sourceIds */
        $sourceIds = array_values(array_filter($row['source_ids'], 'is_string'));
        /** @var array<string,string> $units */
        $units = array_filter($row['units'], static fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH);
        /** @var array<string,bool> $gates */
        $gates = array_filter($row['gates'], static fn (mixed $value, mixed $key): bool => is_string($key) && is_bool($value), ARRAY_FILTER_USE_BOTH);
        /** @var list<string> $blockers */
        $blockers = array_values(array_filter($row['blockers'], 'is_string'));

        return new CalculatorEligibility(
            family: $family,
            status: $status,
            reason: (string) $row['reason'],
            datasetId: $dataset->id,
            datasetVersion: $dataset->datasetVersion,
            datasetChecksum: $dataset->checksum,
            qualificationReportChecksum: $reportChecksum,
            qualificationStatus: $qualificationStatus,
            calculationVersion: $calculationVersion,
            sourceIds: $sourceIds,
            units: $units,
            gates: $gates,
            blockers: $blockers,
        );
    }
}
