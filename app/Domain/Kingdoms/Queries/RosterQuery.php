<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class RosterQuery
{
    public const STALE_AFTER_DAYS = 30;

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
            ->with([
                'player:id,kingdom_id,game_player_id,current_name',
                'membership.user:id,name,email',
            ]);

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
        if ($linkage === 'linked') {
            $query->whereNotNull('membership_id');
        } elseif ($linkage === 'unlinked') {
            $query->whereNull('membership_id');
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $query->where('game_role', $role);
        }

        $observation = $filters['observation'] ?? null;
        if ($observation === 'missing') {
            $query->whereNull('last_observed_at');
        } elseif ($observation === 'stale') {
            $query
                ->whereNotNull('last_observed_at')
                ->where('last_observed_at', '<', now()->subDays(self::STALE_AFTER_DAYS));
        } elseif ($observation === 'current') {
            $query->where('last_observed_at', '>=', now()->subDays(self::STALE_AFTER_DAYS));
        }

        return $query
            ->orderByRaw("case state when 'active' then 0 when 'tracked' then 1 else 2 end")
            ->orderBy('observed_name')
            ->get();
    }

    /** @return list<string> */
    public function rolesForAlliance(Alliance $alliance): array
    {
        $roles = [];

        foreach (AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->whereNotNull('game_role')
            ->where('game_role', '<>', '')
            ->distinct()
            ->orderBy('game_role')
            ->pluck('game_role') as $role) {
            if (is_string($role) && $role !== '') {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
