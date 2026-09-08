<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Queries;

use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Support\Facades\DB;

final class PlayersIntegrityQuery
{
    /**
     * @return array{
     *   aliases_with_live_identity:list<string>,
     *   direct_players_without_current_history:list<string>,
     *   multiple_open_history:list<string>,
     *   unresolved_duplicate_candidates:list<array{kingdom_id:string,normalized_name:string,player_ids:list<string>}>
     * }
     */
    public function report(): array
    {
        $aliasesWithLiveIdentity = Player::query()
            ->whereNotNull('canonical_player_id')
            ->where(static fn ($query) => $query->whereNotNull('user_id')->orWhereNotNull('game_player_id'))
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        $directWithoutCurrentHistory = Player::query()
            ->whereNull('canonical_player_id')
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('player_identity_history')
                    ->whereColumn('player_identity_history.player_id', 'players.id')
                    ->whereNull('player_identity_history.valid_to');
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        $multipleOpenHistory = DB::table('player_identity_history')
            ->select('player_id')
            ->whereNull('valid_to')
            ->groupBy('player_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('player_id')
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        $groups = [];
        foreach (Player::query()->whereNull('canonical_player_id')->orderBy('id')->get() as $player) {
            $normalized = mb_strtolower(trim((string) $player->current_name), 'UTF-8');
            if ($normalized === '') {
                continue;
            }
            $key = (string) $player->current_kingdom_id.'|'.$normalized;
            $groups[$key] ??= [
                'kingdom_id' => (string) $player->current_kingdom_id,
                'normalized_name' => $normalized,
                'player_ids' => [],
            ];
            $groups[$key]['player_ids'][] = (string) $player->id;
        }

        $duplicates = array_values(array_filter(
            $groups,
            static fn (array $group): bool => count($group['player_ids']) > 1,
        ));

        return [
            'aliases_with_live_identity' => array_values($aliasesWithLiveIdentity),
            'direct_players_without_current_history' => array_values($directWithoutCurrentHistory),
            'multiple_open_history' => array_values($multipleOpenHistory),
            'unresolved_duplicate_candidates' => $duplicates,
        ];
    }
}
