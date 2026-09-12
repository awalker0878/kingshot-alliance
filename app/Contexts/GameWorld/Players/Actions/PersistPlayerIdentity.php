<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerIdentityHistoryRecorder;
use App\Contexts\GameWorld\Players\Services\PlayerLifecyclePolicy;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PersistPlayerIdentity
{
    public function __construct(
        private PlayerReferenceQuery $references,
        private PlayerLifecyclePolicy $lifecycle,
        private KingdomReferenceQuery $kingdoms,
        private PlayerIdentityHistoryRecorder $history,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $kingdomId,
        string $observedName,
        ?string $gamePlayerId,
        ?string $expectedPlayerId = null,
        PlayerIdentitySource $source = PlayerIdentitySource::Manual,
        ?string $sourceReference = null,
        ?DateTimeInterface $observedAt = null,
        ?int $confidenceBasisPoints = null,
        ?AuditActor $actor = null,
        ?string $reason = null,
        ?int $expectedExistingOwnerUserId = null,
    ): PlayerReference {
        $name = trim($observedName);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Player name is required.']);
        }
        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        try {
            $playerId = DB::transaction(function () use (
                $kingdomId,
                $name,
                $stableId,
                $expectedPlayerId,
                $source,
                $sourceReference,
                $observedAt,
                $confidenceBasisPoints,
                $actor,
                $reason,
                $expectedExistingOwnerUserId,
            ): string {
                try {
                    $this->kingdoms->lockActiveShared($kingdomId);
                } catch (ModelNotFoundException) {
                    throw ValidationException::withMessages(['kingdom' => 'The selected Kingdom is archived or unavailable.']);
                }

                if ($expectedPlayerId !== null) {
                    $player = Player::query()
                        ->whereKey($expectedPlayerId)
                        ->whereNull('canonical_player_id')
                        ->lockForUpdate()
                        ->firstOrFail();
                    $this->assertExpectedExistingOwner($player, $expectedExistingOwnerUserId);
                    $this->lifecycle->assertKingdomMoveAllowed($player, $kingdomId);
                    if ($stableId !== null) {
                        if ($player->game_player_id !== null && $player->game_player_id !== $stableId) {
                            throw ValidationException::withMessages(['game_player_id' => 'The selected Player has a different stable game-player identifier.']);
                        }
                        $conflict = Player::query()
                            ->where('game_player_id', $stableId)
                            ->whereNull('canonical_player_id')
                            ->where('id', '<>', $player->id)
                            ->exists();
                        if ($conflict) {
                            throw ValidationException::withMessages(['game_player_id' => 'That game Player ID belongs to a different Player.']);
                        }
                    }

                    $nextStableId = $player->game_player_id === null ? $stableId : (string) $player->game_player_id;
                    $this->recordTransition($player, $kingdomId, $name, $nextStableId, $source, $sourceReference, $observedAt, $confidenceBasisPoints, $reason);
                    $previousKingdomId = (string) $player->current_kingdom_id;
                    $previousName = (string) $player->current_name;
                    $previousStableId = $player->game_player_id === null ? null : (string) $player->game_player_id;

                    $attributes = ['current_kingdom_id' => $kingdomId, 'current_name' => $name];
                    if ($stableId !== null && $player->game_player_id === null) {
                        $attributes['game_player_id'] = $stableId;
                    }
                    $player->forceFill($attributes)->save();
                    $this->auditChanges($player, $actor, $previousKingdomId, $previousName, $previousStableId, $source, $sourceReference, $reason);

                    return (string) $player->id;
                }

                if ($stableId !== null) {
                    $player = Player::query()
                        ->where('game_player_id', $stableId)
                        ->whereNull('canonical_player_id')
                        ->lockForUpdate()
                        ->first();
                    if ($player instanceof Player) {
                        $this->assertExpectedExistingOwner($player, $expectedExistingOwnerUserId);
                        $this->lifecycle->assertKingdomMoveAllowed($player, $kingdomId);
                        $previousKingdomId = (string) $player->current_kingdom_id;
                        $previousName = (string) $player->current_name;
                        $previousStableId = (string) $player->game_player_id;
                        $this->recordTransition($player, $kingdomId, $name, $previousStableId, $source, $sourceReference, $observedAt, $confidenceBasisPoints, $reason);
                        $player->forceFill(['current_kingdom_id' => $kingdomId, 'current_name' => $name])->save();
                        $this->auditChanges($player, $actor, $previousKingdomId, $previousName, $previousStableId, $source, $sourceReference, $reason);

                        return (string) $player->id;
                    }
                }

                $player = Player::query()->create([
                    'current_kingdom_id' => $kingdomId,
                    'game_player_id' => $stableId,
                    'current_name' => $name,
                    'canonical_player_id' => null,
                ]);
                $this->history->recordInitial($player, $source, $sourceReference, $observedAt, $confidenceBasisPoints, $reason);
                $this->audit->record('player.created', $actor, $player, null, [
                    'kingdom_id' => $kingdomId,
                    'game_player_id' => $stableId,
                    'source_type' => $source->value,
                    'source_reference' => $sourceReference,
                ]);

                return (string) $player->id;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // The owner transaction/savepoint has rolled back before validation
            // reaches an enclosing caller. Preserve unrelated integrity errors.
            $diagnostic = explode("\n", (string) ($exception->errorInfo[2] ?? ''), 2)[0];
            if ($stableId === null || ! str_contains($diagnostic, '"players_game_player_id_unique"')) {
                throw $exception;
            }
            throw ValidationException::withMessages([
                'game_player_id' => 'That game Player ID was assigned concurrently. Reload the current identity before retrying.',
            ]);
        }

        return $this->references->require($playerId);
    }

    private function assertExpectedExistingOwner(Player $player, ?int $expectedUserId): void
    {
        if ($expectedUserId !== null && ($player->user_id === null || (int) $player->user_id !== $expectedUserId)) {
            throw ValidationException::withMessages([
                'game_player_id' => 'That game Player ID already exists. Use an evidence-backed claim or recovery workflow instead of silently taking ownership.',
            ]);
        }
    }

    private function recordTransition(
        Player $player,
        string $kingdomId,
        string $name,
        ?string $stableId,
        PlayerIdentitySource $source,
        ?string $sourceReference,
        ?DateTimeInterface $observedAt,
        ?int $confidenceBasisPoints,
        ?string $reason,
    ): void {
        $this->history->transition(
            $player,
            $player->user_id === null ? null : (int) $player->user_id,
            $kingdomId,
            $name,
            $stableId,
            $source,
            $sourceReference,
            $observedAt,
            $confidenceBasisPoints,
            $reason,
        );
    }

    private function auditChanges(
        Player $player,
        ?AuditActor $actor,
        string $previousKingdomId,
        string $previousName,
        ?string $previousStableId,
        PlayerIdentitySource $source,
        ?string $sourceReference,
        ?string $reason,
    ): void {
        $metadata = [
            'source_type' => $source->value,
            'source_reference' => $sourceReference,
            'reason' => $reason,
        ];
        if ($previousName !== (string) $player->current_name) {
            $this->audit->record('player.name_changed', $actor, $player, null, $metadata + [
                'previous_name' => $previousName,
                'name' => (string) $player->current_name,
            ]);
        }
        if ($previousKingdomId !== (string) $player->current_kingdom_id) {
            $this->audit->record('player.kingdom_changed', $actor, $player, null, $metadata + [
                'previous_kingdom_id' => $previousKingdomId,
                'kingdom_id' => (string) $player->current_kingdom_id,
            ]);
        }
        $currentStableId = $player->game_player_id === null ? null : (string) $player->game_player_id;
        if ($previousStableId === null && $currentStableId !== null) {
            $this->audit->record('player.game_id_attached', $actor, $player, null, $metadata + [
                'game_player_id' => $currentStableId,
            ]);
        }
    }
}
