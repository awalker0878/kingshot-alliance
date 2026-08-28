<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\Services;

use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionObservation;
use App\Contexts\Intelligence\Roster\Services\PowerMath;
use App\ReadModels\IntelligenceSignals\Enums\IntelligenceSignalType;
use App\ReadModels\IntelligenceSignals\ValueObjects\IntelligenceSignal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final readonly class IntelligenceSignalFactory
{
    public function __construct(
        private IntelligenceSignalRules $rules,
        private PowerMath $powerMath,
    ) {}

    /** @return list<IntelligenceSignal> */
    public function allianceObservationChanges(
        KingdomAllianceObservation $current,
        KingdomAllianceObservation $previous,
        CarbonInterface $asOf,
    ): array
    {
        if (! $current->captured_at->gt($previous->captured_at)) {
            return [];
        }

        $signals = [];

        if ($current->power !== null && $previous->power !== null) {
            $delta = $this->powerMath->difference((string) $current->power, (string) $previous->power);
            $percent = $previous->power === 0
                ? null
                : round((($current->power - $previous->power) / $previous->power) * 100, 2);
            $material = abs((float) $delta) >= $this->rules->alliancePowerAbsolute()
                || ($percent !== null && abs($percent) >= $this->rules->alliancePowerPercent());

            if ($material && $delta !== '0') {
                $signals[] = $this->signal(
                    type: IntelligenceSignalType::ObservationChange,
                    subjectType: 'tracked_alliance',
                    subjectId: (string) $current->tracked_kingdom_alliance_id,
                    metric: 'power',
                    summary: sprintf(
                        '%s was observed at %s power on %s, %s from %s on %s.',
                        $current->observed_name,
                        number_format($current->power),
                        $current->captured_at->toDateString(),
                        str_starts_with($delta, '-')
                            ? 'down '.number_format((int) ltrim($delta, '-'))
                            : 'up '.number_format((int) $delta),
                        number_format($previous->power),
                        $previous->captured_at->toDateString(),
                    ),
                    asOf: $asOf,
                    observedAt: $current->captured_at,
                    baselineObservedAt: $previous->captured_at,
                    currentValue: (string) $current->power,
                    previousValue: (string) $previous->power,
                    delta: $delta,
                    percentChange: $percent,
                    state: 'changed',
                    materiality: 'material',
                    sourceOwner: 'Intelligence/Observations',
                    sourceRecordIds: [(string) $previous->id, (string) $current->id],
                    canonicalUrl: $this->historyUrl((string) $current->tracked_kingdom_alliance_id),
                );
            }
        }

        if ($current->member_count !== null && $previous->member_count !== null) {
            $delta = $current->member_count - $previous->member_count;

            if ($delta !== 0 && abs($delta) >= $this->rules->memberCountAbsolute()) {
                $signals[] = $this->signal(
                    type: IntelligenceSignalType::ObservationChange,
                    subjectType: 'tracked_alliance',
                    subjectId: (string) $current->tracked_kingdom_alliance_id,
                    metric: 'member_count',
                    summary: sprintf(
                        '%s was observed with %d members on %s, %s%d from %d on %s.',
                        $current->observed_name,
                        $current->member_count,
                        $current->captured_at->toDateString(),
                        $delta > 0 ? '+' : '',
                        $delta,
                        $previous->member_count,
                        $previous->captured_at->toDateString(),
                    ),
                    asOf: $asOf,
                    observedAt: $current->captured_at,
                    baselineObservedAt: $previous->captured_at,
                    currentValue: $current->member_count,
                    previousValue: $previous->member_count,
                    delta: $delta,
                    percentChange: $previous->member_count === 0
                        ? null
                        : round(($delta / $previous->member_count) * 100, 2),
                    state: 'changed',
                    materiality: 'material',
                    sourceOwner: 'Intelligence/Observations',
                    sourceRecordIds: [(string) $previous->id, (string) $current->id],
                    canonicalUrl: $this->historyUrl((string) $current->tracked_kingdom_alliance_id),
                );
            }
        }

        return $signals;
    }

    public function staleAllianceObservation(
        KingdomAllianceObservation $latest,
        CarbonInterface $asOf,
    ): ?IntelligenceSignal
    {
        if ($latest->captured_at->gte($asOf->copy()->subDays($this->rules->allianceObservationStaleDays()))) {
            return null;
        }

        return $this->signal(
            type: IntelligenceSignalType::StaleIntelligence,
            subjectType: 'tracked_alliance',
            subjectId: (string) $latest->tracked_kingdom_alliance_id,
            metric: 'alliance_observation',
            summary: sprintf(
                'The latest accepted observation for %s is from %s and is stale.',
                $latest->observed_name,
                $latest->captured_at->toDateString(),
            ),
            asOf: $asOf,
            observedAt: $latest->captured_at,
            baselineObservedAt: null,
            currentValue: $latest->captured_at->toIso8601String(),
            previousValue: null,
            delta: null,
            percentChange: null,
            state: 'stale',
            materiality: 'attention',
            sourceOwner: 'Intelligence/Observations',
            sourceRecordIds: [(string) $latest->id],
            canonicalUrl: $this->historyUrl((string) $latest->tracked_kingdom_alliance_id),
        );
    }

    public function progressionChange(
        GovernorProgressionObservation $current,
        GovernorProgressionObservation $previous,
        CarbonInterface $asOf,
    ): ?IntelligenceSignal
    {
        if ($current->kind !== $previous->kind || ! $current->captured_at->gt($previous->captured_at)) {
            return null;
        }

        $before = $this->comparableProgressionPayload($previous);
        $after = $this->comparableProgressionPayload($current);
        $paths = $this->changedPaths($before, $after);

        if ($paths === []) {
            return null;
        }

        return $this->signal(
            type: IntelligenceSignalType::ProgressionChanged,
            subjectType: 'governor',
            subjectId: (string) $current->player_id,
            metric: $current->kind->value,
            summary: sprintf(
                'Governor progression observation %s changed on %s (%s).',
                $current->kind->value,
                $current->captured_at->toDateString(),
                implode(', ', array_slice($paths, 0, 5)),
            ),
            asOf: $asOf,
            observedAt: $current->captured_at,
            baselineObservedAt: $previous->captured_at,
            currentValue: $after,
            previousValue: $before,
            delta: null,
            percentChange: null,
            state: 'changed',
            materiality: 'material',
            sourceOwner: 'Intelligence/Roster',
            sourceRecordIds: [(string) $previous->id, (string) $current->id],
            evidenceIds: array_values(array_unique(array_filter([
                (string) $previous->evidence_id,
                (string) $current->evidence_id,
            ]))),
            datasetId: (string) $current->progression_dataset_id,
            datasetChecksum: (string) $current->progression_dataset_checksum,
            metadata: [
                'changedPaths' => $paths,
                'reviewId' => (string) $current->evidence_review_id,
            ],
        );
    }

    public function staleProgressionObservation(
        GovernorProgressionObservation $latest,
        CarbonInterface $asOf,
    ): ?IntelligenceSignal
    {
        if ($latest->captured_at->gte($asOf->copy()->subDays($this->rules->progressionObservationStaleDays()))) {
            return null;
        }

        return $this->signal(
            type: IntelligenceSignalType::StaleIntelligence,
            subjectType: 'governor',
            subjectId: (string) $latest->player_id,
            metric: 'governor_progression',
            summary: sprintf(
                'The latest Governor progression observation is from %s and is stale.',
                $latest->captured_at->toDateString(),
            ),
            asOf: $asOf,
            observedAt: $latest->captured_at,
            baselineObservedAt: null,
            currentValue: $latest->captured_at->toIso8601String(),
            previousValue: null,
            delta: null,
            percentChange: null,
            state: 'stale',
            materiality: 'attention',
            sourceOwner: 'Intelligence/Roster',
            sourceRecordIds: [(string) $latest->id],
            evidenceIds: [(string) $latest->evidence_id],
            datasetId: (string) $latest->progression_dataset_id,
            datasetChecksum: (string) $latest->progression_dataset_checksum,
        );
    }

    public function transferExpiry(
        TransferObservation $observation,
        CarbonInterface $asOf,
    ): ?IntelligenceSignal
    {
        if ($observation->valid_until === null) {
            return null;
        }

        $expiresSoonCutoff = $asOf->copy()->addDays($this->rules->transferExpiringDays());
        if ($observation->valid_until->gt($expiresSoonCutoff)) {
            return null;
        }

        $expired = $observation->valid_until->lt($asOf);

        return $this->signal(
            type: IntelligenceSignalType::TransferEvidenceExpiring,
            subjectType: 'transfer_participant',
            subjectId: (string) $observation->transfer_participant_id,
            metric: $observation->kind->value,
            summary: sprintf(
                'Transfer %s observation %s on %s.',
                $observation->kind->value,
                $expired ? 'expired' : 'expires soon',
                $observation->valid_until->toDateString(),
            ),
            asOf: $asOf,
            observedAt: $observation->observed_at,
            baselineObservedAt: null,
            currentValue: $observation->valid_until->toIso8601String(),
            previousValue: null,
            delta: null,
            percentChange: null,
            state: $expired ? 'expired' : 'expiring',
            materiality: 'attention',
            sourceClassification: 'game_fact',
            sourceOwner: 'GameWorld/KingdomTransfers',
            sourceRecordIds: [(string) $observation->id],
            evidenceIds: $observation->evidence_id === null ? [] : [(string) $observation->evidence_id],
            metadata: [
                'sourceType' => $observation->source_type->value,
                'sourceReference' => (string) $observation->source_reference,
            ],
        );
    }

    public function recruitmentChange(
        RecruitmentStageHistory $history,
        CarbonInterface $asOf,
    ): IntelligenceSignal
    {
        $from = $history->fromStage()?->value;
        $to = $history->toStage()->value;

        return $this->signal(
            type: IntelligenceSignalType::RecruitmentChanged,
            subjectType: 'recruitment_candidate',
            subjectId: (string) $history->candidate_id,
            metric: 'stage',
            summary: sprintf(
                'Recruitment candidate stage changed from %s to %s on %s.',
                $from ?? 'new',
                $to,
                $history->changed_at->toDateString(),
            ),
            asOf: $asOf,
            observedAt: $history->changed_at,
            baselineObservedAt: null,
            currentValue: $to,
            previousValue: $from,
            delta: null,
            percentChange: null,
            state: 'changed',
            materiality: 'material',
            sourceClassification: 'operational_fact',
            sourceOwner: 'Alliance/Recruitment',
            sourceRecordIds: [(string) $history->id],
        );
    }

    /**
     * Runs are chronological oldest-to-newest.
     *
     * @param  list<array{recordId: string, observedAt: string, value: int|float}>  $runs
     */
    public function bearHuntTrend(
        string $subjectType,
        string $subjectId,
        string $metric,
        array $runs,
        CarbonInterface $asOf,
    ): ?IntelligenceSignal
    {
        $minimum = $this->rules->bearHuntMinimumRuns();

        if (count($runs) < $minimum) {
            return null;
        }

        $runs = array_slice($runs, -$minimum);
        $direction = null;

        for ($index = 1; $index < count($runs); $index++) {
            $delta = $runs[$index]['value'] <=> $runs[$index - 1]['value'];

            if ($delta === 0) {
                return null;
            }

            $currentDirection = $delta > 0 ? 'increased' : 'decreased';
            if ($direction !== null && $direction !== $currentDirection) {
                return null;
            }

            $direction = $currentDirection;
        }

        if ($direction === null) {
            return null;
        }

        $first = $runs[0];
        $last = $runs[array_key_last($runs)];

        return $this->signal(
            type: IntelligenceSignalType::PerformanceTrend,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metric: $metric,
            summary: sprintf(
                '%s %s across the last %d comparable completed Bear Hunt runs.',
                str_replace('_', ' ', ucfirst($metric)),
                $direction,
                count($runs),
            ),
            asOf: $asOf,
            observedAt: Carbon::parse($last['observedAt']),
            baselineObservedAt: Carbon::parse($first['observedAt']),
            currentValue: $last['value'],
            previousValue: $first['value'],
            delta: $last['value'] - $first['value'],
            percentChange: $first['value'] == 0
                ? null
                : round((($last['value'] - $first['value']) / $first['value']) * 100, 2),
            state: $direction,
            materiality: 'material',
            sourceClassification: 'operational_fact',
            sourceOwner: 'Operations/Results',
            sourceRecordIds: array_values(array_map(
                static fn (array $run): string => $run['recordId'],
                $runs,
            )),
            metadata: ['runCount' => count($runs)],
        );
    }

    public function trackedEntityStateChange(
        string $subjectId,
        bool $previousPresent,
        bool $currentPresent,
        bool $completeSource,
        string $previousRecordId,
        string $currentRecordId,
        CarbonInterface $previousObservedAt,
        CarbonInterface $currentObservedAt,
        CarbonInterface $asOf,
    ): ?IntelligenceSignal
    {
        if (! $completeSource || $previousPresent === $currentPresent) {
            return null;
        }

        $state = $currentPresent ? 'reappeared' : 'disappeared';

        return $this->signal(
            type: IntelligenceSignalType::TrackedEntityStateChanged,
            subjectType: 'tracked_alliance',
            subjectId: $subjectId,
            metric: 'presence',
            summary: sprintf(
                'Tracked Alliance was observed as %s in a complete source capture.',
                $state,
            ),
            asOf: $asOf,
            observedAt: $currentObservedAt,
            baselineObservedAt: $previousObservedAt,
            currentValue: $currentPresent,
            previousValue: $previousPresent,
            delta: null,
            percentChange: null,
            state: $state,
            materiality: 'material',
            sourceOwner: 'Intelligence/Observations',
            sourceRecordIds: [$previousRecordId, $currentRecordId],
        );
    }

    /** @return array<string, mixed> */
    private function comparableProgressionPayload(GovernorProgressionObservation $observation): array
    {
        $payload = is_array($observation->payload) ? $observation->payload : [];

        if (
            $observation->kind !== EvidenceKind::GovernorHeroRoster
            || ($payload['complete_roster_capture'] ?? false) === true
        ) {
            return $payload;
        }

        unset($payload['complete_roster_capture']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changedPaths(array $before, array $after, string $prefix = ''): array
    {
        $paths = [];
        $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
        sort($keys);

        foreach ($keys as $key) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $hasBefore = array_key_exists($key, $before);
            $hasAfter = array_key_exists($key, $after);

            if (! $hasBefore || ! $hasAfter) {
                $paths[] = $path;

                continue;
            }

            if (is_array($before[$key]) && is_array($after[$key])) {
                $paths = [...$paths, ...$this->changedPaths($before[$key], $after[$key], $path)];

                continue;
            }

            if ($before[$key] !== $after[$key]) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>  $sourceRecordIds
     * @param  list<string>  $evidenceIds
     * @param  array<string, mixed>  $metadata
     */
    private function signal(
        IntelligenceSignalType $type,
        string $subjectType,
        string $subjectId,
        ?string $metric,
        string $summary,
        CarbonInterface $asOf,
        CarbonInterface $observedAt,
        ?CarbonInterface $baselineObservedAt,
        mixed $currentValue,
        mixed $previousValue,
        string|int|float|null $delta,
        ?float $percentChange,
        string $state,
        string $materiality,
        string $sourceOwner,
        array $sourceRecordIds,
        string $sourceClassification = 'observation',
        array $evidenceIds = [],
        ?string $datasetId = null,
        ?string $datasetChecksum = null,
        ?string $canonicalUrl = null,
        array $metadata = [],
    ): IntelligenceSignal
    {
        $ruleVersion = $this->rules->ruleVersion();
        $identity = implode('|', [
            $type->value,
            $subjectType,
            $subjectId,
            $metric ?? '-',
            implode(',', $sourceRecordIds),
            $ruleVersion,
        ]);

        return new IntelligenceSignal(
            type: $type,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metric: $metric,
            summary: $summary,
            detectedAsOf: $asOf->toIso8601String(),
            observedAt: $observedAt->toIso8601String(),
            baselineObservedAt: $baselineObservedAt?->toIso8601String(),
            currentValue: $currentValue,
            previousValue: $previousValue,
            delta: $delta,
            percentChange: $percentChange,
            state: $state,
            materiality: $materiality,
            sourceClassification: $sourceClassification,
            sourceOwner: $sourceOwner,
            sourceRecordIds: $sourceRecordIds,
            evidenceIds: $evidenceIds,
            datasetId: $datasetId,
            datasetChecksum: $datasetChecksum,
            canonicalUrl: $canonicalUrl,
            fingerprint: hash('sha256', $identity),
            ruleVersion: $ruleVersion,
            metadata: $metadata,
        );
    }

    private function historyUrl(string $trackingId): ?string
    {
        try {
            return route('alliance.kingdom-alliances.history', ['tracking' => $trackingId], false);
        } catch (\Throwable) {
            return null;
        }
    }
}
