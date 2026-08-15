<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class RosterQuery
{
    /**
     * @param array{
     *   q?: string|null,
     *   state?: string|null,
     *   linkage?: string|null,
     *   role?: string|null,
     *   observation?: string|null
     * } $filters
     * @return Collection<int, AllianceRosterEntry>
     */
    public function forAlliance(Alliance $alliance, array $filters = []): Collection
    {
        $query = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->with('player:id,user_id,current_kingdom_id,game_player_id,current_name');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('observed_name', 'ilike', "%{$search}%")
                    ->orWhereHas('player', static function (Builder $player) use ($search): void {
                        $player->where('game_player_id', 'ilike', "%{$search}%");
                    });
            });
        }

        $state = $filters['state'] ?? null;
        if (is_string($state) && $state !== '') {
            $query->where('state', $state);
        }

        $linkage = $filters['linkage'] ?? null;
        if ($linkage === 'linked' || $linkage === 'unlinked') {
            $method = $linkage === 'linked' ? 'whereExists' : 'whereNotExists';
            $query->{$method}(function ($membership) use ($alliance): void {
                $membership->selectRaw('1')
                    ->from('alliance_memberships')
                    ->whereColumn('alliance_memberships.player_id', 'alliance_roster_entries.player_id')
                    ->where('alliance_memberships.alliance_id', $alliance->id)
                    ->where('alliance_memberships.status', MembershipStatus::Active->value);
            });
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $query->where('game_role', $role);
        }

        $observation = $filters['observation'] ?? null;
        $freshCutoff = now()->subDays(PlayerSnapshotQuery::STALE_AFTER_DAYS);

        if ($observation === 'missing') {
            $query->whereDoesntHave('snapshots');
        } elseif ($observation === 'stale') {
            $query
                ->whereHas('snapshots')
                ->whereDoesntHave('snapshots', static function (Builder $snapshot) use ($freshCutoff): void {
                    $snapshot->where('captured_at', '>=', $freshCutoff);
                });
        } elseif ($observation === 'current') {
            $query->whereHas('snapshots', static function (Builder $snapshot) use ($freshCutoff): void {
                $snapshot->where('captured_at', '>=', $freshCutoff);
            });
        }

        return $query
            ->orderByRaw("case state when 'active' then 0 when 'tracked' then 1 else 2 end")
            ->orderBy('observed_name')
            ->get();
    }

    /** @return list<string> */
    public function rolesForAlliance(Alliance $alliance): array
    {
        return AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->whereNotNull('game_role')
            ->where('game_role', '<>', '')
            ->distinct()
            ->orderBy('game_role')
            ->pluck('game_role')
            ->filter(static fn ($role): bool => is_string($role) && $role !== '')
            ->values()
            ->all();
    }
}
