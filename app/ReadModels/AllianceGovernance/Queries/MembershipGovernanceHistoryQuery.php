<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceGovernance\Queries;

use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\ReadModels\AllianceGovernance\Services\GovernanceHistoryAccess;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

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

    private const PLAYER_KEYS = [
        'player_id',
        'target_player_id',
        'owner_player_id',
        'previous_r5_player_id',
        'new_r5_player_id',
    ];

    public function __construct(
        private PlayerReferenceQuery $players,
        private GovernanceHistoryAccess $access,
        private ScopedCursorCodec $cursors,
    ) {}

    /** @return PageSlice<array{id:string,type:string,occurredAt:string,actor:array{playerId:string,name:string}|null,metadata:array<string,mixed>,source:string}> */
    public function forPlayer(string $viewerPlayerId, string $allianceId, string $playerId, ?string $cursor = null, int $limit = 50): PageSlice
    {
        $this->access->authorize($viewerPlayerId, $allianceId);
        $limit = max(1, min(100, $limit));
        $scope = 'membership-governance|'.$allianceId.'|'.$playerId;
        $query = AuditEvent::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('event', self::EVENTS)
            ->where(static function (Builder $targets) use ($playerId): void {
                foreach (self::PLAYER_KEYS as $key) {
                    $targets->orWhereRaw("metadata->>'{$key}' = ?", [$playerId]);
                }
            });
        // A current or former member/roster entry, or an explicit owner history
        // fact, establishes the target's relationship to this Alliance.
        abort_unless(
            AllianceMembership::query()->where('alliance_id', $allianceId)->where('player_id', $playerId)->exists()
                || AllianceRosterEntry::query()->where('alliance_id', $allianceId)->where('player_id', $playerId)->exists()
                || (clone $query)->exists(),
            404,
        );
        if ($cursor !== null && $cursor !== '') {
            $position = $this->cursors->decode($cursor, $scope);
            $at = $position['at'] ?? null;
            $id = $position['id'] ?? null;
            if (! is_string($at) || ! is_string($id)) {
                throw ValidationException::withMessages(['cursor' => 'The member history cursor is incomplete.']);
            }
            $query->where(static function (Builder $row) use ($at, $id): void {
                $row->where('created_at', '<', $at)->orWhere(static function (Builder $tie) use ($at, $id): void {
                    $tie->where('created_at', $at)->where('id', '<', $id);
                });
            });
        }
        $found = $query->orderByDesc('created_at')->orderByDesc('id')->limit($limit + 1)->get();
        $rows = $found->take($limit)->values();
        $last = $rows->last();
        $nextCursor = $found->count() > $limit && $last instanceof AuditEvent
            ? $this->cursors->encode($scope, ['at' => (string) $last->getRawOriginal('created_at'), 'id' => (string) $last->id])
            : null;

        $actorIds = $rows->pluck('actor_player_id')
            ->filter()
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $actorRefs = $this->players->byIds($actorIds);

        $items = array_values($rows->map(static function (AuditEvent $event) use ($actorRefs): array {
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
        })->all());

        return new PageSlice($items, $nextCursor, $limit, $cursor === null || $cursor === '');
    }
}
