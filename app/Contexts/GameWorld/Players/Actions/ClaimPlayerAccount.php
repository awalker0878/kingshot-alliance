<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Services\PlayerIdentityHistoryRecorder;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\AuditTrail\ValueObjects\AuditPrincipal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ClaimPlayerAccount
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private PlayerIdentityHistoryRecorder $history,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $playerId, int $userId): PlayerReference
    {
        return $this->handleWithProvenance($playerId, $userId);
    }

    public function handleWithProvenance(
        string $playerId,
        int $userId,
        PlayerIdentitySource $source = PlayerIdentitySource::AccountOnboarding,
        ?string $sourceReference = null,
        ?AuditActor $actor = null,
    ): PlayerReference {
        return DB::transaction(function () use ($playerId, $userId, $source, $sourceReference, $actor): PlayerReference {
            $this->accounts->lockActive($userId);
            $locked = Player::query()
                ->whereKey($playerId)
                ->whereNull('canonical_player_id')
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->user_id !== null && (int) $locked->user_id !== $userId) {
                throw ValidationException::withMessages(['player' => 'This Player belongs to another account.']);
            }

            if ($locked->user_id === null) {
                $this->history->transition(
                    $locked,
                    $userId,
                    (string) $locked->current_kingdom_id,
                    (string) $locked->current_name,
                    $locked->game_player_id === null ? null : (string) $locked->game_player_id,
                    $source,
                    $sourceReference,
                    reason: 'Player identity claimed by an account.',
                );
                $locked->forceFill(['user_id' => $userId])->save();
                $this->audit->record(
                    'player.claimed',
                    $actor ?? AuditPrincipal::user($userId),
                    $locked,
                    null,
                    ['user_id' => $userId, 'source_type' => $source->value, 'source_reference' => $sourceReference],
                );
            }

            $locked->refresh();

            return new PlayerReference(
                playerId: (string) $locked->id,
                userId: $locked->user_id === null ? null : (int) $locked->user_id,
                kingdomId: (string) $locked->current_kingdom_id,
                currentName: (string) $locked->current_name,
                gamePlayerId: $locked->game_player_id === null ? null : (string) $locked->game_player_id,
                canonicalPlayerId: $locked->canonical_player_id === null ? null : (string) $locked->canonical_player_id,
            );
        });
    }
}
