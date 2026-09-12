<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Queries;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;

final readonly class KingdomGovernanceTimelineQuery
{
    public function __construct(private PlayerReferenceQuery $players, private GovernanceCataloguePage $pages,
        private KingdomGovernanceProjectionQuery $governance) {}

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function forKingdom(string $actorId, string $kingdomId, ?string $cursor = null): array
    {
        $this->governance->authorize($actorId, $kingdomId);
        $query = AuditEvent::query()->where('event', 'like', 'kingdom.%')->where('metadata->kingdom_id', $kingdomId)
            ->select(['id', 'event', 'actor_player_id', 'created_at'])
            ->selectRaw('actor_user_id is not null as platform_actor');
        // Only the public Governance receipt is materialized, never arbitrary
        // operational recovery metadata or the Platform operator's account identity.
        $fields = ['role_id', 'role_key', 'player_id', 'target_player_id', 'assignment_id', 'mode', 'revoked_assignment_count', 'affected_players', 'owner_key'];
        $pairs = [];
        foreach ($fields as $field) {
            $pairs[] = "'{$field}', left(metadata->>'{$field}', 100)";
        }
        $query->selectRaw('jsonb_strip_nulls(jsonb_build_object('.implode(', ', $pairs).')) as metadata');
        $page = $this->pages->slice($query, 'kingdom-governance-history|'.$actorId.'|'.$kingdomId, $cursor);
        $ids = array_values(array_unique(array_filter(array_map(static fn (AuditEvent $event): ?string => $event->actor_player_id, $page['items']))));
        $refs = $this->players->byIds($ids);
        $page['items'] = array_map(static function (AuditEvent $event) use ($refs): array {
            $playerId = $event->actor_player_id;
            $actorName = $playerId !== null && isset($refs[$playerId]) ? $refs[$playerId]->currentName
                : ($event->getAttribute('platform_actor') ? 'Platform Administrator' : 'System');

            return ['id' => (string) $event->id, 'type' => $event->event, 'occurredAt' => $event->created_at->toIso8601String(),
                'actor' => ['playerId' => $playerId, 'name' => $actorName], 'metadata' => $event->metadata ?? []];
        }, $page['items']);

        return $page;
    }
}
