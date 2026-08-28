<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\Queries;

use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionObservation;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\ReadModels\EventAnalysis\Queries\BearHuntRunHistoryQuery;
use App\ReadModels\IntelligenceSignals\Services\IntelligenceSignalFactory;
use App\ReadModels\IntelligenceSignals\Services\IntelligenceSignalRules;
use App\ReadModels\IntelligenceSignals\ValueObjects\IntelligenceSignal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class IntelligenceSignalQuery
{
    public function __construct(
        private IntelligenceSignalFactory $factory,
        private IntelligenceSignalRules $rules,
        private BearHuntRunHistoryQuery $bearHuntHistory,
    ) {}

    /**
     * The caller must authorize the concrete Alliance scope before invoking this
     * query. The required alliance ID is applied to every owner read before data
     * enters the candidate signal set; there is no global retrieval/filter pass.
     *
     * @return list<array<string,mixed>>
     */
    public function recentForAlliance(
        string $allianceId,
        ?string $actorPlayerId,
        int $limit = 8,
        ?Carbon $asOf = null,
    ): array {
        $asOf ??= now();
        $limit = max(1, min($limit, $this->rules->maxSignals()));
        $signals = [
            ...$this->allianceObservationSignals($allianceId, $asOf),
            ...$this->transferSignals($allianceId, $asOf),
            ...$this->recruitmentSignals($allianceId, $asOf),
        ];

        if ($actorPlayerId !== null && $actorPlayerId !== '') {
            $signals = [
                ...$signals,
                ...$this->progressionSignals($allianceId, $actorPlayerId, $asOf),
                ...$this->bearHuntSignals($allianceId, $actorPlayerId, $asOf),
            ];
        }

        /** @var array<string,IntelligenceSignal> $deduped */
        $deduped = [];
        foreach ($signals as $signal) {
            $deduped[$signal->fingerprint] = $signal;
        }
        $signals = array_values($deduped);
        usort($signals, static function (IntelligenceSignal $left, IntelligenceSignal $right): int {
            $date = strcmp($right->observedAt, $left->observedAt);
            if ($date !== 0) {
                return $date;
            }

            return strcmp($left->fingerprint, $right->fingerprint);
        });

        return array_values(array_map(
            static fn (IntelligenceSignal $signal): array => $signal->toArray(),
            array_slice($signals, 0, $limit),
        ));
    }

    /** @return list<IntelligenceSignal> */
    private function allianceObservationSignals(string $allianceId, Carbon $asOf): array
    {
        /** @var Collection<int,KingdomAllianceObservation> $observations */
        $observations = KingdomAllianceObservation::query()
            ->where('alliance_id', $allianceId)
            ->whereNull('invalidated_at')
            ->where('captured_at', '<=', $asOf)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(400)
            ->get();

        $signals = [];
        foreach ($observations->groupBy('tracked_kingdom_alliance_id') as $history) {
            /** @var Collection<int,KingdomAllianceObservation> $history */
            $latest = $history->first();
            if (! $latest instanceof KingdomAllianceObservation) {
                continue;
            }
            $stale = $this->factory->staleAllianceObservation($latest, $asOf);
            if ($stale instanceof IntelligenceSignal) {
                $signals[] = $stale;
            }
            $previous = $history->skip(1)->first();
            if ($previous instanceof KingdomAllianceObservation) {
                $signals = [...$signals, ...$this->factory->allianceObservationChanges($latest, $previous, $asOf)];
            }
        }

        return $signals;
    }

    /** @return list<IntelligenceSignal> */
    private function progressionSignals(string $allianceId, string $actorPlayerId, Carbon $asOf): array
    {
        /** @var Collection<int,GovernorProgressionObservation> $observations */
        $observations = GovernorProgressionObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('player_id', $actorPlayerId)
            ->where('captured_at', '<=', $asOf)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(80)
            ->get();
        if ($observations->isEmpty()) {
            return [];
        }

        $signals = [];
        $latest = $observations->first();
        if ($latest instanceof GovernorProgressionObservation) {
            $stale = $this->factory->staleProgressionObservation($latest, $asOf);
            if ($stale instanceof IntelligenceSignal) {
                $signals[] = $stale;
            }
        }

        foreach ($observations->groupBy(static fn (GovernorProgressionObservation $observation): string => $observation->kind->value) as $history) {
            /** @var Collection<int,GovernorProgressionObservation> $history */
            $current = $history->first();
            $previous = $history->skip(1)->first();
            if (! $current instanceof GovernorProgressionObservation || ! $previous instanceof GovernorProgressionObservation) {
                continue;
            }

            // A partial Hero-roster capture can prove facts that are present, but
            // cannot prove that a previously observed Hero disappeared. Until a
            // field-level partial comparison is introduced, compare roster-wide
            // snapshots only when the later capture explicitly asserts completeness.
            if ($current->kind === EvidenceKind::GovernorHeroRoster) {
                $payload = is_array($current->payload) ? $current->payload : [];
                if (($payload['complete_roster_capture'] ?? false) !== true) {
                    continue;
                }
            }

            $signal = $this->factory->progressionChange($current, $previous, $asOf);
            if ($signal instanceof IntelligenceSignal) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    /** @return list<IntelligenceSignal> */
    private function transferSignals(string $allianceId, Carbon $asOf): array
    {
        /** @var Collection<int,TransferObservation> $observations */
        $observations = TransferObservation::query()
            ->where('alliance_id', $allianceId)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', $asOf->copy()->addDays($this->rules->transferExpiringDays()))
            ->where('valid_until', '>=', $asOf->copy()->subDays($this->rules->recentDays()))
            ->orderByDesc('valid_until')
            ->limit(100)
            ->get();

        $signals = [];
        foreach ($observations as $observation) {
            $signal = $this->factory->transferExpiry($observation, $asOf);
            if ($signal instanceof IntelligenceSignal) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    /** @return list<IntelligenceSignal> */
    private function recruitmentSignals(string $allianceId, Carbon $asOf): array
    {
        /** @var Collection<int,RecruitmentStageHistory> $history */
        $history = RecruitmentStageHistory::query()
            ->where('alliance_id', $allianceId)
            ->where('changed_at', '>=', $asOf->copy()->subDays($this->rules->recentDays()))
            ->where('changed_at', '<=', $asOf)
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return $history
            ->map(fn (RecruitmentStageHistory $change): IntelligenceSignal => $this->factory->recruitmentChange($change, $asOf))
            ->values()
            ->all();
    }

    /** @return list<IntelligenceSignal> */
    private function bearHuntSignals(string $allianceId, string $actorPlayerId, Carbon $asOf): array
    {
        $minimum = $this->rules->bearHuntMinimumRuns();
        $occurrences = EventOccurrence::query()
            ->where('status', EventOccurrenceStatus::Completed->value)
            ->where('starts_at', '<=', $asOf)
            ->whereHas('event', static fn (Builder $query) => $query
                ->where('scope', EventScope::Alliance->value)
                ->where('alliance_id', $allianceId)
                ->whereHas('eventType', static fn (Builder $type) => $type->where('slug', 'bear-hunt')))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(max($minimum, 6))
            ->get(['id', 'starts_at']);
        if ($occurrences->count() < $minimum) {
            return [];
        }

        $occurrenceIds = array_values($occurrences->pluck('id')->map(static fn ($id): string => (string) $id)->all());
        $facts = $this->bearHuntHistory->forOccurrences($occurrenceIds, $actorPlayerId);
        $chronological = $occurrences->reverse()->values();
        $allianceRuns = [];
        $personalRuns = [];
        foreach ($chronological as $occurrence) {
            $id = (string) $occurrence->id;
            $run = $facts[$id] ?? null;
            if (! is_array($run)) {
                continue;
            }
            if ($run['totalDamage'] !== null) {
                $allianceRuns[] = ['recordId' => $id, 'observedAt' => $occurrence->starts_at->toIso8601String(), 'value' => (int) $run['totalDamage']];
            }
            if ($run['personalDamage'] !== null) {
                $personalRuns[] = ['recordId' => $id, 'observedAt' => $occurrence->starts_at->toIso8601String(), 'value' => (int) $run['personalDamage']];
            }
        }

        $signals = [];
        $allianceTrend = $this->factory->bearHuntTrend('alliance', $allianceId, 'alliance_damage', $allianceRuns, $asOf);
        if ($allianceTrend instanceof IntelligenceSignal) {
            $signals[] = $allianceTrend;
        }
        $personalTrend = $this->factory->bearHuntTrend('governor', $actorPlayerId, 'personal_damage', $personalRuns, $asOf);
        if ($personalTrend instanceof IntelligenceSignal) {
            $signals[] = $personalTrend;
        }

        return $signals;
    }
}
