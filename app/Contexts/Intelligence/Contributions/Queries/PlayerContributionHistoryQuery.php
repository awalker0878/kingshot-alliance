<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Queries;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Contributions\Models\ContributionCategory;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Contexts\Intelligence\EventAnalysis\Queries\EventPlayerHistoryQuery;
use App\Contexts\Intelligence\EventAnalysis\Queries\EventPlayerHistorySummaryQuery;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final readonly class PlayerContributionHistoryQuery
{
    public function __construct(
        private EventPlayerHistoryQuery $events,
        private EventPlayerHistorySummaryQuery $eventSummary,
    ) {}

    /**
     * Compose exact-Player history without copying Event facts into ContributionRecord.
     *
     * @param array{
     *   from?:DateTimeInterface|null,
     *   until?:DateTimeInterface|null,
     *   alliance_id?:string|null,
     *   kingdom_id_at_event?:string|null,
     *   event_scope?:string|null,
     *   event_type_slug?:string|null,
     *   event_metric_key?:string|null,
     *   participation_outcome?:string|null,
     *   contribution_category_slug?:string|null,
     *   limit?:int|null
     * } $filters
     * @return list<array<string,mixed>>
     */
    public function forPlayer(Player $player, array $filters = []): array
    {
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $allianceId = isset($filters['alliance_id']) ? trim((string) $filters['alliance_id']) : '';
        $kingdomId = isset($filters['kingdom_id_at_event']) ? trim((string) $filters['kingdom_id_at_event']) : '';
        $eventScope = isset($filters['event_scope']) ? trim((string) $filters['event_scope']) : '';
        $categorySlug = isset($filters['contribution_category_slug'])
            ? trim((string) $filters['contribution_category_slug'])
            : '';

        $eventFilters = [
            'scope' => $eventScope === '' ? null : $eventScope,
            'event_type_slug' => $filters['event_type_slug'] ?? null,
            'from' => $filters['from'] ?? null,
            'until' => $filters['until'] ?? null,
            'metric_key' => $filters['event_metric_key'] ?? null,
            'represented_alliance_id' => $allianceId === '' ? null : $allianceId,
            'kingdom_id_at_event' => $kingdomId === '' ? null : $kingdomId,
            'participation_outcome' => $filters['participation_outcome'] ?? null,
            'limit' => $limit,
        ];

        $timeline = [];
        foreach ($this->events->forPlayer($player, $eventFilters) as $event) {
            $timeline[] = [
                'kind' => 'event',
                'occurredAt' => CarbonImmutable::parse((string) $event['startsAt'])->toIso8601String(),
                'event' => $event,
                'contribution' => null,
            ];
        }

        // Scope tabs are Event-specific. Non-Event contribution records belong to
        // the All view and are not silently mixed into Player/Alliance/Kingdom tabs.
        if ($eventScope === '') {
            $records = ContributionRecord::query()
                ->where('player_id', $player->id)
                ->when(
                    $allianceId !== '',
                    static fn ($query) => $query->where('alliance_id', $allianceId),
                )
                ->when(
                    $kingdomId !== '',
                    static fn ($query) => $query->whereIn(
                        'alliance_id',
                        DB::table('alliances')->where('kingdom_id', $kingdomId)->select('id'),
                    ),
                )
                ->when(
                    $categorySlug !== '',
                    static fn ($query) => $query->whereHas(
                        'category',
                        static fn ($category) => $category->where('slug', $categorySlug),
                    ),
                )
                ->when(
                    ($filters['from'] ?? null) instanceof DateTimeInterface,
                    static fn ($query) => $query->where('recorded_at', '>=', $filters['from']),
                )
                ->when(
                    ($filters['until'] ?? null) instanceof DateTimeInterface,
                    static fn ($query) => $query->where('recorded_at', '<=', $filters['until']),
                )
                ->with('category')
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            foreach ($records as $record) {
                $category = $record->category;
                if (! $category instanceof ContributionCategory) {
                    continue;
                }

                $recordedAt = CarbonImmutable::parse((string) $record->recorded_at)->toIso8601String();
                $timeline[] = [
                    'kind' => 'contribution',
                    'occurredAt' => $recordedAt,
                    'event' => null,
                    'contribution' => [
                        'recordId' => (string) $record->id,
                        'playerId' => (string) $record->player_id,
                        'allianceId' => (string) $record->alliance_id,
                        'categoryId' => (string) $record->category_id,
                        'categoryName' => (string) $category->name,
                        'categorySlug' => (string) $category->slug,
                        'unit' => (string) $category->unit,
                        'value' => (string) $record->value,
                        'source' => $record->source->value,
                        'dataClass' => $record->data_class->value,
                        'status' => $record->status->value,
                        'periodStart' => $record->period_start->toDateString(),
                        'periodEnd' => $record->period_end->toDateString(),
                        'recordedAt' => $recordedAt,
                        'correctionOfRecordId' => $record->correction_of_record_id === null
                            ? null
                            : (string) $record->correction_of_record_id,
                    ],
                ];
            }
        }

        usort(
            $timeline,
            static fn (array $left, array $right): int => strcmp((string) $right['occurredAt'], (string) $left['occurredAt']),
        );

        return array_values(array_slice($timeline, 0, $limit));
    }

    /**
     * @return array{
     *   events:int,
     *   player_events:int,
     *   alliance_events:int,
     *   kingdom_events:int,
     *   completed:int,
     *   absent:int,
     *   excused:int,
     *   unresolved:int,
     *   reliability_percent:?float,
     *   contribution_records:int
     * }
     */
    public function summaryForPlayer(Player $player): array
    {
        return [
            ...$this->eventSummary->forPlayer($player),
            'contribution_records' => ContributionRecord::query()
                ->where('player_id', $player->id)
                ->count(),
        ];
    }
}
