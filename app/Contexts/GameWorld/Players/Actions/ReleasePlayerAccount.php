<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerIdentityHistoryRecorder;
use App\Contexts\GameWorld\Players\Services\PlayerLifecyclePolicy;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\AuditTrail\ValueObjects\AuditPrincipal;
use Illuminate\Support\Facades\DB;

final readonly class ReleasePlayerAccount
{
    public function __construct(
        private PlayerLifecyclePolicy $lifecycle,
        private PlayerIdentityHistoryRecorder $history,
        private PlayerReferenceQuery $references,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $userId, string $playerId): PlayerReference
    {
        DB::transaction(function () use ($userId, $playerId): void {
            $player = Player::query()
                ->whereKey($playerId)
                ->where('user_id', $userId)
                ->whereNull('canonical_player_id')
                ->lockForUpdate()
                ->firstOrFail();
            $this->lifecycle->assertReleaseAllowed($player);
            $this->history->transition(
                $player,
                null,
                (string) $player->current_kingdom_id,
                (string) $player->current_name,
                $player->game_player_id === null ? null : (string) $player->game_player_id,
                PlayerIdentitySource::Manual,
                reason: 'Account owner released this Governor identity.',
            );
            $player->forceFill(['user_id' => null])->save();
            $this->audit->record('player.released', AuditPrincipal::user($userId), $player, null, [
                'previous_user_id' => $userId,
                'source_type' => PlayerIdentitySource::Manual->value,
            ]);
        });

        return $this->references->require($playerId);
    }
}
