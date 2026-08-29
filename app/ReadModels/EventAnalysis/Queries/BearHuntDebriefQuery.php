<?php

declare(strict_types=1);

namespace App\ReadModels\EventAnalysis\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Queries\BearHuntUnmatchedGovernorQuery;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Participation\Queries\BearHuntAttendanceSummaryQuery;
use App\Contexts\Operations\Rallies\Queries\RallyParticipationSummaryQuery;
use App\Contexts\Operations\Results\Queries\BearHuntDebriefResultQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class BearHuntDebriefQuery
{
    private const HISTORY_LIMIT = 12;

    public function __construct(
        private EventAuthorization $authorization,
        private BearHuntDebriefResultQuery $results,
        private BearHuntAttendanceSummaryQuery $attendance,
        private RallyParticipationSummaryQuery $rallies,
        private BearHuntUnmatchedGovernorQuery $unmatchedGovernors,
        private BearHuntRunHistoryQuery $history,
    ) {}

    /** @return array<string,mixed> */
    public function forOccurrence(
        EventOccurrence $occurrence,
        PlayerReference $actor,
        bool $canManage,
    ): array {
        $event = $occurrence->event;
        if (! $event instanceof Event) {
            throw new LogicException('Bear Hunt debrief occurrence must reference an Event.');
        }
        $event->loadMissing('eventType.workflowDimensions');
        if ($event->eventType?->slug !== 'bear-hunt'
            || $event->scopeEnum() !== EventScope::Alliance
            || ! is_string($event->alliance_id)
            || ! $event->eventType->supportsWorkflow(EventWorkflowDimension::Participation)
            || ! $event->eventType->supportsWorkflow(EventWorkflowDimension::Rallies)
            || ! $event->eventType->supportsWorkflow(EventWorkflowDimension::Results)
            || ! $event->eventType->supportsWorkflow(EventWorkflowDimension::Debrief)) {
            throw ValidationException::withMessages([
                'event' => 'Bear Hunt Debrief is available only for Alliance Bear Hunt occurrences.',
            ]);
        }

        $this->authorization->authorize(
            $actor->playerId,
            EventScope::Alliance,
            $event->alliance_id,
            OperationsPermission::EventAllianceView,
        );

        $currentResults = $this->results->forOccurrence((string) $occurrence->id);
        $currentAttendance = $this->attendance->forOccurrence((string) $occurrence->id);
        $currentRallies = $this->rallies->forOccurrence((string) $occurrence->id);

        $governors = [];
        foreach ($currentResults['governors'] as $governor) {
            $playerId = (string) $governor['playerId'];
            $attendance = $currentAttendance['players'][$playerId] ?? null;
            $rallies = $currentRallies['players'][$playerId] ?? null;
            $governors[] = [
                ...$governor,
                'attendanceStatus' => is_array($attendance) ? ($attendance['status'] ?? null) : null,
                'rallies' => is_array($rallies)
                    ? [
                        'available' => true,
                        'participated' => (int) ($rallies['participated'] ?? 0),
                        'led' => (int) ($rallies['led'] ?? 0),
                        'joined' => (int) ($rallies['joined'] ?? 0),
                    ]
                    : [
                        'available' => false,
                        'participated' => null,
                        'led' => null,
                        'joined' => null,
                    ],
            ];
        }

        $runOccurrences = EventOccurrence::query()
            ->where(function (Builder $query) use ($occurrence): void {
                $query->whereKey($occurrence->id)
                    ->orWhere('status', EventOccurrenceStatus::Completed->value);
            })
            ->where('starts_at', '<=', $occurrence->starts_at)
            ->whereHas('event', static fn (Builder $query) => $query
                ->where('scope', EventScope::Alliance->value)
                ->where('alliance_id', $event->alliance_id)
                ->where('event_type_id', $event->event_type_id))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        /** @var list<string> $runIds */
        $runIds = array_values(
            $runOccurrences->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
        );
        $historyFacts = $this->history->forOccurrences($runIds, $actor->playerId);
        $runs = [];
        foreach ($runOccurrences as $run) {
            $id = (string) $run->id;
            $facts = $historyFacts[$id] ?? null;
            if (! is_array($facts)) {
                continue;
            }
            $runs[] = [
                'occurrenceId' => $id,
                'startsAt' => $run->starts_at->toIso8601String(),
                'endsAt' => $run->ends_at->toIso8601String(),
                'status' => $run->status->value,
                ...$facts,
            ];
        }

        $currentHistory = $historyFacts[(string) $occurrence->id] ?? null;
        $previous = null;
        foreach ($runs as $run) {
            if ($run['occurrenceId'] === (string) $occurrence->id) {
                continue;
            }
            if ($run['status'] === EventOccurrenceStatus::Completed->value) {
                $previous = $run;
                break;
            }
        }

        $unmatched = $canManage
            ? $this->unmatchedGovernors->forOccurrence($actor->playerId, (string) $occurrence->id)
            : [];

        $actorResult = null;
        foreach ($governors as $governor) {
            if ($governor['playerId'] === $actor->playerId) {
                $actorResult = $governor;
                break;
            }
        }

        $personalTrend = [];
        $allianceTrend = [];
        foreach (array_reverse($runs) as $run) {
            $personalTrend[] = [
                'occurrenceId' => $run['occurrenceId'],
                'startsAt' => $run['startsAt'],
                'damage' => $run['personalDamage'],
                'rank' => $run['personalRank'],
                'attendanceStatus' => $run['attendance']['personalStatus'],
                'rallies' => $run['rallies']['personalParticipated'],
                'ralliesAvailable' => $run['rallies']['personalParticipated'] !== null,
            ];
            $allianceTrend[] = [
                'occurrenceId' => $run['occurrenceId'],
                'startsAt' => $run['startsAt'],
                'totalDamage' => $run['totalDamage'],
                'governorCount' => $run['governorCount'],
                'attendanceRatePercent' => $run['attendance']['ratePercent'],
                'attendanceAvailable' => $run['attendance']['available'],
                'recordedRallies' => $run['rallies']['available'] ? $run['rallies']['participated'] : null,
                'ralliesAvailable' => $run['rallies']['available'],
            ];
        }
        $comparison = $this->comparison($currentHistory, $previous);
        $currentPersonalDamage = is_array($currentHistory) && is_int($currentHistory['personalDamage'] ?? null)
            ? $currentHistory['personalDamage']
            : null;
        $currentPersonalAccepted = (bool) ($currentHistory['personalAccepted'] ?? false);
        $previousPersonalDamage = array_values(array_filter(array_map(
            static fn (array $run): ?int => $run['occurrenceId'] !== (string) $occurrence->id
                && ($run['personalAccepted'] ?? false) === true
                && is_int($run['personalDamage'] ?? null)
                    ? $run['personalDamage']
                    : null,
            $runs,
        ), static fn (?int $damage): bool => $damage !== null));

        return [
            'run' => [
                'occurrenceId' => (string) $occurrence->id,
                'eventId' => (string) $event->id,
                'allianceId' => $event->alliance_id,
                'title' => $event->title,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'endsAt' => $occurrence->ends_at->toIso8601String(),
                'status' => $occurrence->status->value,
            ],
            'summary' => [
                'resultsAvailable' => (bool) $currentResults['available'],
                'totalDamage' => $currentResults['totalDamage'],
                'governorCount' => (int) $currentResults['governorCount'],
                'acceptedReportCount' => (int) $currentResults['acceptedReportCount'],
                'attendance' => [
                    'available' => (bool) $currentAttendance['available'],
                    'total' => (int) $currentAttendance['total'],
                    'ratePercent' => $currentAttendance['ratePercent'],
                    'byStatus' => $currentAttendance['byStatus'],
                ],
                'rallies' => [
                    'available' => (bool) $currentRallies['available'],
                    'recordedAssignments' => (int) $currentRallies['recordedAssignments'],
                    'participated' => $currentRallies['available'] ? (int) $currentRallies['participated'] : null,
                    'led' => $currentRallies['available'] ? (int) $currentRallies['led'] : null,
                    'joined' => $currentRallies['available'] ? (int) $currentRallies['joined'] : null,
                ],
                'unmatchedGovernorCount' => array_sum(array_map(
                    static fn (array $item): int => count($item['rows'] ?? []),
                    $unmatched,
                )),
            ],
            'governors' => $governors,
            'personal' => [
                'playerId' => $actor->playerId,
                'playerName' => $actor->currentName,
                'result' => $actorResult,
                'attendanceStatus' => $currentAttendance['players'][$actor->playerId]['status'] ?? null,
                'rallies' => $currentRallies['players'][$actor->playerId] ?? [
                    'available' => false,
                    'participated' => null,
                    'led' => null,
                    'joined' => null,
                ],
            ],
            'unmatchedGovernors' => $unmatched,
            'canReviewEvidence' => $canManage,
            'previousRun' => $previous,
            'comparison' => $comparison,
            'signals' => [
                'allianceDamage' => $this->direction($comparison['allianceDamage'] ?? null),
                'personalDamage' => $this->direction($comparison['personalDamage'] ?? null),
                'personalRallies' => $this->direction($comparison['personalRallies'] ?? null),
                'newPersonalBest' => $currentPersonalAccepted
                    && $currentPersonalDamage !== null
                    && ($previousPersonalDamage === [] || $currentPersonalDamage > max($previousPersonalDamage)),
                'acceptedResult' => $currentPersonalAccepted,
                'evidenceStatus' => $currentPersonalAccepted
                    ? 'accepted'
                    : ($currentPersonalDamage !== null ? 'recorded_without_accepted_evidence' : 'unavailable'),
                'reviewPending' => $unmatched !== [],
            ],
            'personalTrend' => $personalTrend,
            'allianceTrend' => $allianceTrend,
            'runs' => $runs,
        ];
    }

    /**
     * @param  array<string,mixed>|null  $current
     * @param  array<string,mixed>|null  $previous
     * @return array<string,mixed>|null
     */
    private function comparison(?array $current, ?array $previous): ?array
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return [
            'allianceDamage' => $this->delta($current['totalDamage'], $previous['totalDamage']),
            'governorCount' => $this->delta($current['governorCount'], $previous['governorCount'], percent: false),
            'attendancePresent' => $this->delta(
                $current['attendance']['available'] ? $current['attendance']['present'] : null,
                $previous['attendance']['available'] ? $previous['attendance']['present'] : null,
                percent: false,
            ),
            'attendanceRate' => $this->delta(
                $current['attendance']['ratePercent'],
                $previous['attendance']['ratePercent'],
                percent: false,
            ),
            'recordedRallies' => $this->delta(
                $current['rallies']['available'] ? $current['rallies']['participated'] : null,
                $previous['rallies']['available'] ? $previous['rallies']['participated'] : null,
            ),
            'personalDamage' => $this->delta($current['personalDamage'], $previous['personalDamage']),
            'personalRank' => [
                'current' => $current['personalRank'],
                'previous' => $previous['personalRank'],
                'movement' => $current['personalRank'] !== null && $previous['personalRank'] !== null
                    ? $previous['personalRank'] - $current['personalRank']
                    : null,
            ],
            'personalAttendance' => [
                'current' => $current['attendance']['personalStatus'],
                'previous' => $previous['attendance']['personalStatus'],
                'state' => $current['attendance']['personalStatus'] !== null
                    && $previous['attendance']['personalStatus'] !== null
                        ? 'available'
                        : 'unavailable',
            ],
            'personalRallies' => $this->delta(
                $current['rallies']['personalParticipated'],
                $previous['rallies']['personalParticipated'],
            ),
        ];
    }

    /** @return array{current:int|float|null,previous:int|float|null,delta:int|float|null,percentChange:?float,state:string} */
    private function delta(int|float|null $current, int|float|null $previous, bool $percent = true): array
    {
        if ($current === null || $previous === null) {
            return [
                'current' => $current,
                'previous' => $previous,
                'delta' => null,
                'percentChange' => null,
                'state' => 'unavailable',
            ];
        }

        $difference = $current - $previous;
        if (! $percent) {
            return [
                'current' => $current,
                'previous' => $previous,
                'delta' => $difference,
                'percentChange' => null,
                'state' => 'available',
            ];
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => $difference,
            'percentChange' => $previous == 0
                ? null
                : round(($difference / $previous) * 100, 2),
            'state' => $previous == 0 ? 'previous_zero' : 'available',
        ];
    }

    /** @param array<string,mixed>|null $comparison */
    private function direction(?array $comparison): string
    {
        $delta = $comparison['delta'] ?? null;
        if (! is_int($delta) && ! is_float($delta)) {
            return 'unknown';
        }
        if ($delta > 0) {
            return 'increased';
        }
        if ($delta < 0) {
            return 'decreased';
        }

        return 'unchanged';
    }
}
