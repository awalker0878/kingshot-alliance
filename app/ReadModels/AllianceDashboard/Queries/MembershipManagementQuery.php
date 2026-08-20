<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceDashboard\Queries;

use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final readonly class MembershipManagementQuery
{
    public const PAGE_SIZE = 50;

    public function __construct(
        private PlayerReferenceQuery $players,
        private ScopedCursorCodec $cursors,
    ) {}

    /**
     * @return array{
     *   page: array{
     *     items: list<array{id: string, player: array{id: string, name: string, gamePlayerId: string|null, claimed: bool}, status: string, rank: string, roles: list<array{id: string, key: string, name: string}>}>,
     *     nextCursor: string|null,
     *     hasMore: bool,
     *     pageSize: int,
     *     isFirstPage: bool
     *   },
     *   total: int
     * }
     */
    public function forAlliance(string $allianceId, ?string $cursor = null): array
    {
        $query = AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->with('roles:id,alliance_id,key,name');

        if ($cursor !== null && $cursor !== '') {
            $position = $this->cursors->decode($cursor, 'alliance-memberships:'.$allianceId);
            $createdAt = $position['created_at'] ?? null;
            $membershipId = $position['id'] ?? null;
            if (! is_string($createdAt) || ! is_string($membershipId)) {
                throw ValidationException::withMessages([
                    'member_cursor' => 'The membership cursor is incomplete.',
                ]);
            }

            $query->where(static function (Builder $membership) use ($createdAt, $membershipId): void {
                $membership
                    ->where('created_at', '>', $createdAt)
                    ->orWhere(static function (Builder $tie) use ($createdAt, $membershipId): void {
                        $tie->where('created_at', '=', $createdAt)->where('id', '>', $membershipId);
                    });
            });
        }

        $rows = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();
        $hasMore = $rows->count() > self::PAGE_SIZE;
        $page = $rows->take(self::PAGE_SIZE)->values();
        $playerReferences = $this->players->byIds(
            $page->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
        );

        $items = $page->map(static function (AllianceMembership $member) use ($playerReferences): array {
            $player = $playerReferences[(string) $member->player_id] ?? null;

            return [
                'id' => (string) $member->id,
                'player' => [
                    'id' => (string) $member->player_id,
                    'name' => $player->currentName ?? 'Unknown player',
                    'gamePlayerId' => $player?->gamePlayerId,
                    'claimed' => $player?->claimed() ?? false,
                ],
                'status' => $member->status->value,
                'rank' => $member->rank->value,
                'roles' => array_values($member->roles
                    ->map(static fn (Role $role): array => [
                        'id' => (string) $role->id,
                        'key' => (string) $role->key,
                        'name' => (string) $role->name,
                    ])
                    ->values()
                    ->all()),
            ];
        })->values()->all();

        $items = array_values($items);

        $nextCursor = null;
        $last = $page->last();
        if ($hasMore && $last instanceof AllianceMembership) {
            $nextCursor = $this->cursors->encode('alliance-memberships:'.$allianceId, [
                'created_at' => $last->created_at?->toIso8601String() ?? '',
                'id' => (string) $last->id,
            ]);
        }

        return [
            'page' => (new PageSlice(
                $items,
                $nextCursor,
                self::PAGE_SIZE,
                $cursor === null || $cursor === '',
            ))->toArray(),
            'total' => AllianceMembership::query()->where('alliance_id', $allianceId)->count(),
        ];
    }
}
