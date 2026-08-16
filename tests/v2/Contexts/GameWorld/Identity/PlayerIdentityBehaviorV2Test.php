<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\GameWorld\Identity;

use App\Contexts\GameWorld\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Enums\KingdomStatus;
use App\Contexts\GameWorld\Services\PlayerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class PlayerIdentityBehaviorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_stable_game_identity_is_reused_and_account_claim_is_exclusive(): void
    {
        $factory = new ScenarioFactory;
        $kingdom = $factory->kingdom(12001);
        $first = app(PersistPlayerIdentity::class)->handle((string) $kingdom->id, 'First Name', 'stable-12001');
        $again = app(PersistPlayerIdentity::class)->handle((string) $kingdom->id, 'Renamed', 'stable-12001');

        self::assertSame((string) $first->id, (string) $again->id);
        self::assertSame('Renamed', $again->current_name);

        $owner = $factory->user();
        $other = $factory->user();
        $claimed = app(ClaimPlayerAccount::class)->handle($again, $owner);
        self::assertSame((int) $owner->id, (int) $claimed->user_id);

        $this->expectException(ValidationException::class);
        app(ClaimPlayerAccount::class)->handle($claimed, $other);
    }

    public function test_player_context_fails_closed_and_never_accepts_another_users_player(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->user();
        $other = $factory->user();
        $player = $factory->player($owner, 12002);
        $context = app(PlayerContext::class);

        try {
            $context->player();
            self::fail('Unresolved PlayerContext must fail closed.');
        } catch (LogicException) {
            self::assertNull($context->playerOrNull());
        }

        $context->activate($player, $owner);
        self::assertSame((string) $player->id, (string) $context->player()->id);
        $context->clear();

        $this->expectException(LogicException::class);
        $context->activate($player, $other);
    }

    public function test_archived_and_invalid_kingdoms_cannot_be_resolved_for_active_use(): void
    {
        $factory = new ScenarioFactory;
        $kingdom = $factory->kingdom(12003);
        $kingdom->forceFill(['status' => KingdomStatus::Archived])->save();

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
