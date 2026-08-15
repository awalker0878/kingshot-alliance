<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Queries;

use App\Domain\Contributions\Models\ContributionCategory;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Events\Queries\EventPlayerHistoryQuery;
use App\Domain\Kingdoms\Models\Player;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class PlayerContributionHistoryQuery
{
    public function __construct(private EventPlayerHistoryQuery $events) {}

    /**
     * Compose exact-Player history without copying Event facts into ContributionRecord.
     *
     * @param array{
     *   from?:DateTimeInterface|null,
     *   until?:DateTimeInterface|null,
     *   alliance_id?:string|null,
     *   event_scope?:string|null,
     *   event_type_slug?:string|null,
     *   event_metric_key?:string|null,
     *   participation_outcome?:string|null,
     *   limit?:int|null
     * } $filters
     * @return list<array<string,mixed>>
     */
    public function forPlayer(Player $player, array $filters = []): array
    {
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $allianceId = isset($filters['alliance_id']) ? trim((string) $filters['alliance_id']) : '';

        $eventFilters = [
            'scope' => $filters['event_scope'] ?? null,
            'event_type_slug' => $filters['event_type_slug'] ?? null,
            'from' => $filters['from'] ?? null,
            'until' => $filters['until'] ?? null,
            'metric_key' => $filters['event_metric_key'] ?? null,
            'represented_alliance_id' => $allianceId === '' ? null : $allianceId,
            'participation_outcome' => $filters['participation_outcome'] ?? null,
            'limit' => $limit,
        ];

        $timeline = [];
        foreach ($this->events->forPlayer($player, $eventFilters) as $event) {
            $timeline[] = [
                'kind' => 'event',
                'occurredAt' => (string) $event['startsAt'],
                'event' => $event,
                'contribution' => null,
            ];
        }

        $records = ContributionRecord::query()
            ->where('player_id', $player->id)
            ->when(
                $allianceId !== '',
                static fn ($query) => $query->where('alliance_id', $allianceId),
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

        usort(
            $timeline,
            static fn (array $left, array $right): int => strcmp((string) $right['occurredAt'], (string) $left['occurredAt']),
        );

        return array_values(array_slice($timeline, 0, $limit));
    }
}
