<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class KingdomRoleBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_bootstrap_establishes_first_player_kingdom_admin_then_player_delegates_roles(): void
    {
        $adminUser = User::factory()->create();
        $coordinatorUser = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1650, 'status' => 'active']);
        $adminPlayer = Player::query()->create([
            'user_id' => $adminUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'bootstrap-admin',
            'current_name' => 'Bootstrap Admin',
        ]);
        $coordinatorPlayer = Player::query()->create([
            'user_id' => $coordinatorUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'bootstrap-coordinator',
            'current_name' => 'Bootstrap Coordinator',
        ]);

        $admin = $this->app->make(BootstrapKingdomAdministrator::class)->handle($kingdom, $adminPlayer);
        self::assertSame($adminPlayer->id, $admin->player_id);
        self::assertSame(DefaultKingdomRole::Administrator->value, $admin->role->key);

        $coordinator = $this->app->make(AssignKingdomRole::class)->handle(
            $adminPlayer,
            $kingdom,
            $coordinatorPlayer,
            DefaultKingdomRole::EventCoordinator,
        );
        self::assertSame($coordinatorPlayer->id, $coordinator->player_id);
        self::assertSame(DefaultKingdomRole::EventCoordinator->value, $coordinator->role->key);
        self::assertSame(2, KingdomRoleAssignment::query()->where('kingdom_id', $kingdom->id)->count());

        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => null,
            'actor_player_id' => null,
            'event' => 'kingdom.role_bootstrapped',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => null,
            'actor_player_id' => $adminPlayer->id,
            'event' => 'kingdom.role_assigned',
        ]);
    }

    public function test_bootstrap_cannot_replace_existing_kingdom_admin(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 1651, 'status' => 'active']);
        $first = Player::query()->create([
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'bootstrap-first',
            'current_name' => 'First Admin',
        ]);
        $second = Player::query()->create([
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'bootstrap-second',
            'current_name' => 'Second Admin',
        ]);
        $bootstrap = $this->app->make(BootstrapKingdomAdministrator::class);
        $bootstrap->handle($kingdom, $first);

        $this->expectException(ValidationException::class);
        $bootstrap->handle($kingdom, $second);
    }

    public function test_bootstrap_rejects_player_from_different_kingdom(): void
    {
        $firstKingdom = Kingdom::query()->create(['number' => 1652, 'status' => 'active']);
        $secondKingdom = Kingdom::query()->create(['number' => 1653, 'status' => 'active']);
        $player = Player::query()->create([
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'bootstrap-wrong-kingdom',
            'current_name' => 'Wrong Kingdom',
        ]);

        $this->expectException(ValidationException::class);
        $this->app->make(BootstrapKingdomAdministrator::class)->handle($firstKingdom, $player);
    }
}
