<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceGovernance\Queries;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;

final readonly class AllianceGovernanceTimelineQuery
{
    private const PREFIXES = [
        'alliance.',
        'membership.',
        'invitation.',
        'recruitment.',
        'content.',
        'integration.',
    ];

    public function __construct(private PlayerReferenceQuery $players) {}

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string} */
    public function forAlliance(
        string $allianceId,
        ?string $eventPrefix = null,
        ?string $actorPlayerId = null,
        ?string $beforeId = null,
        int $limit = 50,
    ): array {
        $limit = max(1, min(100, $limit));
        $query = AuditEvent::query()->where('alliance_id', $allianceId);
        $query->where(function (Builder $builder): void {
            foreach (self::PREFIXES as $index => $prefix) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}('event', 'like', $prefix.'%');
            }
        });

        if ($eventPrefix !== null && in_array($eventPrefix.'.', self::PREFIXES, true)) {
            $query->where('event', 'like', $eventPrefix.'.%');
        }
        if ($actorPlayerId !== null) {
            $query->where('actor_player_id', $actorPlayerId);
        }
        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();

        $actorIds = $rows->pluck('actor_player_id')
            ->filter()
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $actorRefs = $this->players->byIds($actorIds);

        $items = $rows->map(static function (AuditEvent $event) use ($actorRefs): array {
            $actorId = $event->actor_player_id === null ? null : (string) $event->actor_player_id;
            $actor = $actorId === null ? null : ($actorRefs[$actorId] ?? null);

            return [
                'id' => (string) $event->id,
                'type' => (string) $event->event,
                'occurredAt' => $event->created_at?->toIso8601String(),
                'actor' => $actorId === null ? null : [
                    'playerId' => $actorId,
                    'name' => $actor?->currentName ?? 'Unknown Governor',
                ],
                'metadata' => $event->metadata ?? [],
                'source' => 'audit',
                'handoff' => self::handoff((string) $event->event),
            ];
        })->all();

        return [
            'items' => $items,
            'nextCursor' => $hasMore && $rows->isNotEmpty() ? (string) $rows->last()->id : null,
        ];
    }

    private static function handoff(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'membership.'), str_starts_with($event, 'invitation.') => '/alliance',
            str_starts_with($event, 'recruitment.') => '/alliance/recruitment',
            str_starts_with($event, 'content.') => '/alliance/content/manage',
            str_starts_with($event, 'integration.') => '/alliance/connections',
            default => '/alliance/settings',
        };
    }
}
