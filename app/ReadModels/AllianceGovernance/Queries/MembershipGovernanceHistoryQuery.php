<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceGovernance\Queries;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;

final readonly class MembershipGovernanceHistoryQuery
{
    private const EVENTS = [
        'invitation.created',
        'invitation.accepted',
        'invitation.revoked',
        'membership.status_changed',
        'membership.rank_changed',
        'membership.role_assigned',
        'membership.role_removed',
        'alliance.leadership_transferred',
    ];

    public function __construct(private PlayerReferenceQuery $players) {}

    /** @return list<array<string,mixed>> */
    public function forPlayer(string $allianceId, string $playerId, int $limit = 100): array
    {
        $rows = AuditEvent::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('event', self::EVENTS)
            ->latest('created_at')
            ->limit(500)
            ->get()
            ->filter(fn (AuditEvent $event): bool => $this->touchesPlayer($event, $playerId))
            ->take(max(1, min(100, $limit)))
            ->values();

        $actorIds = $rows->pluck('actor_player_id')
            ->filter()
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $actorRefs = $this->players->byIds($actorIds);

        return $rows->map(static function (AuditEvent $event) use ($actorRefs): array {
            $actorId = $event->actor_player_id;
            $actor = $actorId === null ? null : ($actorRefs[$actorId] ?? null);

            return [
                'id' => $event->id,
                'type' => $event->event,
                'occurredAt' => $event->created_at->toIso8601String(),
                'actor' => $actorId === null ? null : [
                    'playerId' => $actorId,
                    'name' => $actor === null ? 'Unknown Governor' : $actor->currentName,
                ],
                'metadata' => $event->metadata,
                'source' => 'audit',
            ];
        })->values()->all();
    }

    private function touchesPlayer(AuditEvent $event, string $playerId): bool
    {
        $metadata = $event->metadata;
        foreach ([
            'player_id',
            'target_player_id',
            'owner_player_id',
            'previous_r5_player_id',
            'new_r5_player_id',
        ] as $key) {
            if (isset($metadata[$key]) && (string) $metadata[$key] === $playerId) {
                return true;
            }
        }

        return false;
    }
}
