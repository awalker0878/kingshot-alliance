<?php

declare(strict_types=1);

namespace Tests\Feature\GameWorld\Core\V2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class IdentityLifecycleV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_kingdom_resolution_normalizes_identity_and_reuses_the_same_game_world_record(): void
    {
        $resolver = app(ResolveKingdom::class);

        $first = $resolver->handle(' 004201 ');
        $second = $resolver->handle(4201);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(4201, $first->number);
        self::assertTrue($first->is($second));
    }

    public function test_kingdom_resolution_rejects_values_outside_the_game_world_identity_contract(): void
    {
        foreach (['abc', '0', '-1', '2147483648'] as $invalid) {
            try {
                app(ResolveKingdom::class)->handle($invalid);
                self::fail('Expected kingdom '.$invalid.' to be rejected.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('kingdom', $exception->errors());
            }
        }
    }

    public function test_stable_game_player_identity_is_idempotent_and_updates_current_observation(): void
    {
        $kingdom = app(ResolveKingdom::class)->handle(4202);
        self::assertNotNull($kingdom);

        $persist = app(PersistPlayerIdentity::class);
        $first = $persist->handle((string) $kingdom->id, 'Original Name', 'game-4202-1');
        $second = $persist->handle((string) $kingdom->id, 'Current Name', 'game-4202-1');

        self::assertTrue($first->is($second));
        self::assertSame('Current Name', $second->current_name);
        self::assertSame(1, Player::query()->where('game_player_id', 'game-4202-1')->count());
    }

    public function test_claiming_a_player_is_idempotent_for_the_owner_and_fails_closed_for_another_account(): void
    {
        $scenario = (new ScenarioFactory)->claimedPlayer(4203, 'Claimed Player', 'game-4203-1');
        $claim = app(ClaimPlayerAccount::class);

        $same = $claim->handle($scenario['player'], $scenario['user']);
        self::assertSame($scenario['user']->id, $same->user_id);

        $other = User::factory()->create();
        $this->expectException(ValidationException::class);
        $claim->handle($scenario['player'], $other);
    }

    public function test_expected_player_update_cannot_take_another_players_stable_identifier(): void
    {
        $kingdom = app(ResolveKingdom::class)->handle(4204);
        self::assertNotNull($kingdom);
        $persist = app(PersistPlayerIdentity::class);

        $first = $persist->handle((string) $kingdom->id, 'First', 'game-4204-a');
        $persist->handle((string) $kingdom->id, 'Second', 'game-4204-b');

        $this->expectException(ValidationException::class);
        $persist->handle((string) $kingdom->id, 'Hijack', 'game-4204-b', (string) $first->id);
    }
}
