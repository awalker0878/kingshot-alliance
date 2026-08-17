<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlayerIdentityBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_stable_game_identity_is_reused_and_account_claim_is_exclusive(): void
    {
        $factory = new ScenarioFactory;
        $kingdom = $factory->kingdom(12001);
        $first = app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'First Name', 'stable-12001');
        $again = app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'Renamed', 'stable-12001');

        self::assertSame($first->playerId, $again->playerId);
        self::assertSame('Renamed', $again->currentName);

        $owner = $factory->account();
        $other = $factory->account();
        $claimed = app(ClaimPlayerAccount::class)->handle($again->playerId, $owner->userId);
        self::assertSame($owner->userId, $claimed->userId);

        $this->expectException(ValidationException::class);
        app(ClaimPlayerAccount::class)->handle($claimed->playerId, $other->userId);
    }

    public function test_player_context_fails_closed_and_never_accepts_another_users_player(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $other = $factory->account();
        $player = $factory->player($owner->userId, 12002);
        $context = app(PlayerContext::class);

        try {
            $context->player();
            self::fail('Unresolved PlayerContext must fail closed.');
        } catch (LogicException) {
            self::assertNull($context->playerOrNull());
        }

        $context->activate($player, $owner->userId);
        self::assertSame($player->playerId, $context->player()->playerId);
        $context->clear();

        $this->expectException(LogicException::class);
        $context->activate($player, $other->userId);
    }

    public function test_archived_and_invalid_kingdoms_cannot_be_resolved_for_active_use(): void
    {
        $factory = new ScenarioFactory;
        $kingdom = $factory->kingdom(12003);
        Kingdom::query()->whereKey($kingdom->kingdomId)->update(['status' => KingdomStatus::Archived->value]);

        try {
            app(ResolveKingdom::class)->handle('not-a-number');
            self::fail('Invalid kingdom should fail.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        app(ResolveKingdom::class)->handle(12003);
    }
}
