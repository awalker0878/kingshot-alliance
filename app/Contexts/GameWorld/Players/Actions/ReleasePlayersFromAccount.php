<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Services\PlayerIdentityHistoryRecorder;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ReleasePlayersFromAccount
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private PlayerIdentityHistoryRecorder $history,
        private AuditRecorder $audit,
    ) {}

    /** @param list<string> $playerIds */
    public function handle(int $userId, array $playerIds): void
    {
        if ($playerIds === []) {
            return;
        }

        DB::transaction(function () use ($userId, $playerIds): void {
            $this->accounts->lockCurrent($userId);
            $players = Player::query()
                ->whereIn('id', $playerIds)
                ->where('user_id', $userId)
                ->whereNull('canonical_player_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($players as $player) {
                $this->history->transition(
                    $player,
                    null,
                    (string) $player->current_kingdom_id,
                    (string) $player->current_name,
                    $player->game_player_id === null ? null : (string) $player->game_player_id,
                    PlayerIdentitySource::DataGovernance,
                    reason: 'Account deletion released Player ownership.',
                );
                $player->forceFill(['user_id' => null])->save();
                $this->audit->record('player.released', null, $player, null, [
                    'previous_user_id' => $userId,
                    'source_type' => PlayerIdentitySource::DataGovernance->value,
                ]);
            }
        });
    }
}
