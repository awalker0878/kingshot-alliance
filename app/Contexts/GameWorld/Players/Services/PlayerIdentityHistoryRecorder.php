<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Services;

use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Models\PlayerIdentityHistory;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class PlayerIdentityHistoryRecorder
{
    public function recordInitial(
        Player $player,
        PlayerIdentitySource $source = PlayerIdentitySource::Manual,
        ?string $sourceReference = null,
        ?DateTimeInterface $observedAt = null,
        ?int $confidenceBasisPoints = null,
        ?string $reason = null,
    ): PlayerIdentityHistory {
        $this->assertConfidence($confidenceBasisPoints);

        return PlayerIdentityHistory::query()->create([
            'player_id' => (string) $player->id,
            'user_id' => $player->user_id === null ? null : (int) $player->user_id,
            'kingdom_id' => (string) $player->current_kingdom_id,
            'name' => (string) $player->current_name,
            'game_player_id' => $this->nullable($player->game_player_id === null ? null : (string) $player->game_player_id),
            'valid_from' => now(),
            'valid_to' => null,
            'source_type' => $source,
            'source_reference' => $this->nullable($sourceReference),
            'observed_at' => $observedAt === null ? null : Carbon::instance($observedAt)->utc(),
            'confidence_basis_points' => $confidenceBasisPoints,
            'reason' => $this->nullable($reason),
        ]);
    }

    public function transition(
        Player $player,
        ?int $userId,
        string $kingdomId,
        string $name,
        ?string $gamePlayerId,
        PlayerIdentitySource $source = PlayerIdentitySource::Manual,
        ?string $sourceReference = null,
        ?DateTimeInterface $observedAt = null,
        ?int $confidenceBasisPoints = null,
        ?string $reason = null,
    ): bool {
        $this->assertConfidence($confidenceBasisPoints);
        $name = trim($name);
        $gamePlayerId = $this->nullable($gamePlayerId);

        if (($player->user_id === null ? null : (int) $player->user_id) === $userId
            && (string) $player->current_kingdom_id === $kingdomId
            && (string) $player->current_name === $name
            && ($player->game_player_id === null ? null : (string) $player->game_player_id) === $gamePlayerId) {
            return false;
        }

        $at = now();
        $current = PlayerIdentityHistory::query()
            ->where('player_id', $player->id)
            ->whereNull('valid_to')
            ->lockForUpdate()
            ->first();

        if (! $current instanceof PlayerIdentityHistory) {
            $current = $this->recordInitial(
                $player,
                $source,
                $sourceReference,
                $observedAt,
                $confidenceBasisPoints,
                $reason,
            );
        }

        $current->forceFill(['valid_to' => $at])->save();
        PlayerIdentityHistory::query()->create([
            'player_id' => (string) $player->id,
            'user_id' => $userId,
            'kingdom_id' => $kingdomId,
            'name' => $name,
            'game_player_id' => $gamePlayerId,
            'valid_from' => $at,
            'valid_to' => null,
            'source_type' => $source,
            'source_reference' => $this->nullable($sourceReference),
            'observed_at' => $observedAt === null ? null : Carbon::instance($observedAt)->utc(),
            'confidence_basis_points' => $confidenceBasisPoints,
            'reason' => $this->nullable($reason),
        ]);

        return true;
    }

    public function closeCurrent(Player $player, ?DateTimeInterface $at = null): void
    {
        PlayerIdentityHistory::query()
            ->where('player_id', $player->id)
            ->whereNull('valid_to')
            ->lockForUpdate()
            ->update(['valid_to' => $at === null ? now() : Carbon::instance($at)->utc()]);
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function assertConfidence(?int $confidenceBasisPoints): void
    {
        if ($confidenceBasisPoints !== null && ($confidenceBasisPoints < 0 || $confidenceBasisPoints > 10000)) {
            throw new InvalidArgumentException('Player identity confidence must be between 0 and 10000 basis points.');
        }
    }
}
