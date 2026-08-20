<?php

declare(strict_types=1);

namespace App\ReadModels\Roster\Queries;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final readonly class RosterQuery
{
    public const PAGE_SIZE = 50;

    private const STATE_RANK_SQL = "case state when 'active' then 0 when 'tracked' then 1 else 2 end";

    public function __construct(private ScopedCursorCodec $cursors) {}

    /**
     * @param  array{
     *   q?: string|null,
     *   state?: string|null,
     *   linkage?: string|null,
     *   role?: string|null,
     *   observation?: string|null
     * }  $filters
     * @return Collection<int, AllianceRosterEntry>
     */
    public function forAlliance(string $allianceId, array $filters = []): Collection
    {
        return $this->ordered($this->baseQuery($allianceId, $this->normalizeFilters($filters)))->get();
    }

    /**
     * @param  array{q?: string|null, state?: string|null, linkage?: string|null, role?: string|null, observation?: string|null}  $filters
     * @return PageSlice<AllianceRosterEntry>
     */
    public function pageForAlliance(
        string $allianceId,
        array $filters = [],
        ?string $cursor = null,
    ): PageSlice {
        $normalized = $this->normalizeFilters($filters);
        $query = $this->baseQuery($allianceId, $normalized);
        $scope = $this->cursorScope($allianceId, $normalized);

        if ($cursor !== null && $cursor !== '') {
            $position = $this->cursors->decode($cursor, $scope);
            $stateRank = $position['state_rank'] ?? null;
            $observedName = $position['observed_name'] ?? null;
            $entryId = $position['id'] ?? null;

            if (! is_int($stateRank) || ! is_string($observedName) || ! is_string($entryId)) {
                throw ValidationException::withMessages([
                    'cursor' => 'The roster cursor is incomplete.',
                ]);
            }

            $query->where(static function (Builder $entry) use ($stateRank, $observedName, $entryId): void {
                $entry
                    ->whereRaw(self::STATE_RANK_SQL.' > ?', [$stateRank])
                    ->orWhere(static function (Builder $sameState) use ($stateRank, $observedName, $entryId): void {
                        $sameState
                            ->whereRaw(self::STATE_RANK_SQL.' = ?', [$stateRank])
                            ->where(static function (Builder $namePosition) use ($observedName, $entryId): void {
                                $namePosition
                                    ->where('observed_name', '>', $observedName)
                                    ->orWhere(static function (Builder $sameName) use ($observedName, $entryId): void {
                                        $sameName
                                            ->where('observed_name', '=', $observedName)
                                            ->where('id', '>', $entryId);
                                    });
                            });
                    });
            });
        }

        $rows = $this->ordered($query)->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $rows->count() > self::PAGE_SIZE;
        $page = $rows->take(self::PAGE_SIZE)->values();
        $nextCursor = null;
        $last = $page->last();

        if ($hasMore && $last instanceof AllianceRosterEntry) {
            $nextCursor = $this->cursors->encode($scope, [
                'state_rank' => $this->stateRank($last),
                'observed_name' => (string) $last->observed_name,
                'id' => (string) $last->id,
            ]);
        }

        return new PageSlice(
            $page->all(),
            $nextCursor,
            self::PAGE_SIZE,
            $cursor === null || $cursor === '',
        );
    }

    /**
     * @param  array{q?: string|null, state?: string|null, linkage?: string|null, role?: string|null, observation?: string|null}  $filters
     * @return array{total: int, current: int, stale: int, missing: int, linked: int}
     */
    public function summaryForAlliance(string $allianceId, array $filters = []): array
    {
        $normalized = $this->normalizeFilters($filters);
        $base = $this->baseQuery($allianceId, $normalized);

        return [
            'total' => (clone $base)->count(),
            'current' => $this->applyObservation(clone $base, 'current')->count(),
            'stale' => $this->applyObservation(clone $base, 'stale')->count(),
            'missing' => $this->applyObservation(clone $base, 'missing')->count(),
            'linked' => $this->applyLinkage(clone $base, $allianceId, 'linked')->count(),
        ];
    }

    /** @param  array{q: string, state: string, linkage: string, role: string, observation: string}  $filters */
    private function baseQuery(string $allianceId, array $filters): Builder
    {
        $query = AllianceRosterEntry::query()->where('alliance_id', $allianceId);

        $search = $filters['q'];
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('observed_name', 'ilike', "%{$search}%")
                    ->orWhereExists(static function ($player) use ($search): void {
                        $player->selectRaw('1')
                            ->from('players')
                            ->whereColumn('players.id', 'alliance_roster_entries.player_id')
                            ->where('players.game_player_id', 'ilike', "%{$search}%");
                    });
            });
        }

        $state = $filters['state'];
        if ($state !== '') {
            $query->where('state', $state);
        }

        $this->applyLinkage($query, $allianceId, $filters['linkage']);

        $role = $filters['role'];
        if ($role !== '') {
            $query->where('game_role', $role);
        }

        $this->applyObservation($query, $filters['observation']);

        return $query;
    }

    private function applyLinkage(Builder $query, string $allianceId, string $linkage): Builder
    {
        if ($linkage !== 'linked' && $linkage !== 'unlinked') {
            return $query;
        }

        $method = $linkage === 'linked' ? 'whereExists' : 'whereNotExists';
        $query->{$method}(static function ($membership) use ($allianceId): void {
            $membership->selectRaw('1')
                ->from('alliance_memberships')
                ->whereColumn('alliance_memberships.player_id', 'alliance_roster_entries.player_id')
                ->where('alliance_memberships.alliance_id', $allianceId)
                ->where('alliance_memberships.status', MembershipStatus::Active->value);
        });

        return $query;
    }

    private function applyObservation(Builder $query, string $observation): Builder
    {
        $freshCutoff = now()->subDays(PlayerSnapshotQuery::STALE_AFTER_DAYS);

        $snapshotExists = static function ($snapshot): void {
            $snapshot->selectRaw('1')
                ->from('player_snapshots')
                ->whereColumn('player_snapshots.roster_entry_id', 'alliance_roster_entries.id');
        };
        $freshSnapshotExists = static function ($snapshot) use ($freshCutoff): void {
            $snapshot->selectRaw('1')
                ->from('player_snapshots')
                ->whereColumn('player_snapshots.roster_entry_id', 'alliance_roster_entries.id')
                ->where('player_snapshots.captured_at', '>=', $freshCutoff);
        };

        if ($observation === 'missing') {
            $query->whereNotExists($snapshotExists);
        } elseif ($observation === 'stale') {
            $query->whereExists($snapshotExists)->whereNotExists($freshSnapshotExists);
        } elseif ($observation === 'current') {
            $query->whereExists($freshSnapshotExists);
        }

        return $query;
    }

    private function ordered(Builder $query): Builder
    {
        return $query
            ->orderByRaw(self::STATE_RANK_SQL)
            ->orderBy('observed_name')
            ->orderBy('id');
    }

    private function stateRank(AllianceRosterEntry $entry): int
    {
        return match ($entry->state->value) {
            'active' => 0,
            'tracked' => 1,
            default => 2,
        };
    }

    /**
     * @param  array{q?: string|null, state?: string|null, linkage?: string|null, role?: string|null, observation?: string|null}  $filters
     * @return array{q: string, state: string, linkage: string, role: string, observation: string}
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'state' => (string) ($filters['state'] ?? ''),
            'linkage' => (string) ($filters['linkage'] ?? ''),
            'role' => trim((string) ($filters['role'] ?? '')),
            'observation' => (string) ($filters['observation'] ?? ''),
        ];
    }

    /** @param  array{q: string, state: string, linkage: string, role: string, observation: string}  $filters */
    private function cursorScope(string $allianceId, array $filters): string
    {
        return 'alliance-roster:'.$allianceId.':'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    public function rolesForAlliance(string $allianceId): array
    {
        return array_values(AllianceRosterEntry::query()
            ->where('alliance_id', $allianceId)
            ->whereNotNull('game_role')
            ->where('game_role', '<>', '')
            ->distinct()
            ->orderBy('game_role')
            ->pluck('game_role')
            ->filter(static fn ($role): bool => is_string($role) && $role !== '')
            ->all());
    }
}
