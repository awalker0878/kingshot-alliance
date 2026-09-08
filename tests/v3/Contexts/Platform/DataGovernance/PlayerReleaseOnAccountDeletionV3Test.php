<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Platform\DataGovernance;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Models\PlayerIdentityHistory;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlayerReleaseOnAccountDeletionV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_releases_player_ownership_but_preserves_durable_game_identity(): void
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $player = $factory->player($account->userId, 19501, 'durable-19501');
        $request = AccountDeletionRequest::query()->create([
            'user_id' => $account->userId,
            'status' => 'pending',
            'requested_at' => now()->subDay(),
            'eligible_at' => now()->subMinute(),
        ]);

        self::assertSame(1, app(ProcessAccountDeletionRequests::class)->handle());

        $persisted = Player::query()->findOrFail($player->playerId);
        self::assertNull($persisted->user_id);
        self::assertSame('durable-19501', (string) $persisted->game_player_id);
        self::assertNull($persisted->canonical_player_id);
        self::assertSame('processed', (string) $request->fresh()->status);
        $currentHistory = PlayerIdentityHistory::query()->where('player_id', $player->playerId)->whereNull('valid_to')->firstOrFail();
        self::assertNull($currentHistory->user_id);
        self::assertSame('data_governance', $currentHistory->source_type->value);
    }
}
