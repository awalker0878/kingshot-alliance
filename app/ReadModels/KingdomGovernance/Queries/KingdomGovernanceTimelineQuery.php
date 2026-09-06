<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Queries;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;

final readonly class KingdomGovernanceTimelineQuery
{
    public function __construct(private PlayerReferenceQuery $players, private AccountIdentityQuery $accounts) {}

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string} */
    public function forKingdom(string $kingdomId, ?string $beforeId = null, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $query = AuditEvent::query()->where('event', 'like', 'kingdom.%')->where('metadata->kingdom_id', $kingdomId);
        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }
        $rows = $query->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $playerIds = array_values($rows->pluck('actor_player_id')->filter()->map('strval')->unique()->values()->all());
        $userIds = array_values($rows->pluck('actor_user_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->values()->all());
        $playerRefs = $this->players->byIds($playerIds);
        $accountRefs = $this->accounts->byIds($userIds);
        $items = $rows->map(static function (AuditEvent $event) use ($playerRefs, $accountRefs): array {
            $playerId = $event->actor_player_id;
            $userId = $event->actor_user_id;
            if ($playerId !== null && isset($playerRefs[$playerId])) {
                $actorName = $playerRefs[$playerId]->currentName;
            } elseif ($userId !== null && isset($accountRefs[(int) $userId])) {
                $actorName = $accountRefs[(int) $userId]->name;
            } else {
                $actorName = $userId !== null ? 'Platform Administrator' : 'System';
            }

            return ['id' => (string) $event->id, 'type' => (string) $event->event, 'occurredAt' => $event->created_at->toIso8601String(), 'actor' => ['playerId' => $playerId, 'userId' => $userId, 'name' => $actorName], 'metadata' => $event->metadata ?? []];
        })->values()->all();
        $last = $rows->last();

        return ['items' => array_values($items), 'nextCursor' => $hasMore && $last instanceof AuditEvent ? (string) $last->id : null];
    }
}
