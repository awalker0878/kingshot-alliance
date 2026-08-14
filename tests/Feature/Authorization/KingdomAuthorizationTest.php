<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Authorization\Actions\AssignKingdomRole;
use App\Domain\Authorization\Actions\BootstrapKingdomAdministrator;
use App\Domain\Authorization\Actions\RemoveKingdomRole;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\ResolvePlayer;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrapped_player_kingdom_admin_has_kingdom_authority_without_alliance_rank_implication(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1201, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'kingdom-admin-player',
            'current_name' => 'Kingdom Admin',
        ]);

        $assignment = $this->app->make(BootstrapKingdomAdministrator::class)->handle($kingdom, $player);
        $authorization = $this->app->make(KingdomAuthorization::class);

        self::assertTrue($authorization->allows($player, $kingdom, PermissionKey::EventKingdomManage));
        self::assertTrue($authorization->allows($player, $kingdom, PermissionKey::KingdomRoleManage));
        self::assertSame(DefaultKingdomRole::Administrator->value, $assignment->role()->sole()->key);
    }

    public function test_kingdom_admin_player_can_delegate_roles_only_inside_exact_kingdom(): void
    {
        $first = Kingdom::query()->create(['number' => 1301, 'status' => 'active']);
        $second = Kingdom::query()->create(['number' => 1302, 'status' => 'active']);
        $admin = Player::query()->create([
            'current_kingdom_id' => $first->id,
            'game_player_id' => 'delegate-admin',
            'current_name' => 'Delegate Admin',
        ]);
        $coordinator = Player::query()->create([
            'current_kingdom_id' => $first->id,
            'game_player_id' => 'delegate-coordinator',
            'current_name' => 'Delegate Coordinator',
        ]);
        $viewer = Player::query()->create([
            'current_kingdom_id' => $first->id,
            'game_player_id' => 'delegate-viewer',
            'current_name' => 'Delegate Viewer',
        ]);
        $otherKingdomPlayer = Player::query()->create([
            'current_kingdom_id' => $second->id,
            'game_player_id' => 'delegate-other',
            'current_name' => 'Other Kingdom',
        ]);

        $this->app->make(BootstrapKingdomAdministrator::class)->handle($first, $admin);
        $assign = $this->app->make(AssignKingdomRole::class);
        $assign->handle($admin, $first, $coordinator, DefaultKingdomRole::EventCoordinator);
        $assign->handle($admin, $first, $viewer, DefaultKingdomRole::Viewer);

        $authorization = $this->app->make(KingdomAuthorization::class);
        self::assertTrue($authorization->allows($coordinator, $first, PermissionKey::EventKingdomCreate));
        self::assertTrue($authorization->allows($coordinator, $first, PermissionKey::EventKingdomManage));
        self::assertFalse($authorization->allows($coordinator, $first, PermissionKey::KingdomRoleManage));
        self::assertTrue($authorization->allows($viewer, $first, PermissionKey::EventKingdomView));
        self::assertFalse($authorization->allows($viewer, $first, PermissionKey::EventKingdomCreate));
        self::assertFalse($authorization->allows($admin, $second, PermissionKey::EventKingdomManage));

        $this->expectException(AuthorizationException::class);
        $assign->handle($admin, $second, $otherKingdomPlayer, DefaultKingdomRole::Viewer);
    }

    public function test_kingdom_role_assignment_is_idempotent_and_removal_is_scope_checked(): void
    {
        $first = Kingdom::query()->create(['number' => 1401, 'status' => 'active']);
        $second = Kingdom::query()->create(['number' => 1402, 'status' => 'active']);
        $admin = Player::query()->create([
            'current_kingdom_id' => $first->id,
            'game_player_id' => 'idempotent-admin',
            'current_name' => 'Idempotent Admin',
        ]);
        $target = Player::query()->create([
            'current_kingdom_id' => $first->id,
            'game_player_id' => 'idempotent-target',
            'current_name' => 'Idempotent Target',
        ]);
        $secondAdmin = Player::query()->create([
            'current_kingdom_id' => $second->id,
            'game_player_id' => 'idempotent-second-admin',
            'current_name' => 'Second Admin',
        ]);

        $bootstrap = $this->app->make(BootstrapKingdomAdministrator::class);
        $bootstrap->handle($first, $admin);
        $bootstrap->handle($second, $secondAdmin);
        $assign = $this->app->make(AssignKingdomRole::class);
        $firstAssignment = $assign->handle($admin, $first, $target, DefaultKingdomRole::Viewer);
        $secondAssignment = $assign->handle($admin, $first, $target, DefaultKingdomRole::Viewer);

        self::assertSame($firstAssignment->id, $secondAssignment->id);
        self::assertSame(1, KingdomRoleAssignment::query()
            ->where('kingdom_id', $first->id)
            ->where('player_id', $target->id)
            ->count());

        try {
            $this->app->make(RemoveKingdomRole::class)->handle($secondAdmin, $second, $firstAssignment);
            self::fail('A Kingdom role assignment must not be removable through another Kingdom context.');
        } catch (AuthorizationException) {
            self::assertTrue(KingdomRoleAssignment::query()->whereKey($firstAssignment->id)->exists());
        }

        $this->app->make(RemoveKingdomRole::class)->handle($admin, $first, $firstAssignment);
        self::assertFalse(KingdomRoleAssignment::query()->whereKey($firstAssignment->id)->exists());
    }

    public function test_kingdom_role_must_be_removed_before_roster_resolution_can_move_a_player(): void
    {
        $first = Kingdom::query()->create(['number' => 1441, 'status' => 'active']);
        $second = Kingdom::query()->create(['number' => 1442, 'status' => 'active']);
        $admin = Player::query()->create([
            'current_kingdom_id' => $first->id,
            'game_player_id' => 'role-drift-admin',
            'current_name' => 'Role Drift Admin',
        ]);
        $target = Player::query()->create([
            'current_kingdom_id' => $first->id,
            'game_player_id' => 'role-drift-target',
            'current_name' => 'Role Drift Target',
        ]);
        $secondOwnerUser = User::factory()->create();
        $secondOwner = Player::query()->create([
            'user_id' => $secondOwnerUser->id,
            'current_kingdom_id' => $second->id,
            'game_player_id' => 'role-drift-second-owner',
            'current_name' => 'Second Kingdom Owner',
        ]);
        $secondAlliance = $this->app->make(CreateAlliance::class)
            ->handle($secondOwner, 'Role Drift Destination', 'role-drift-destination');

        $this->app->make(BootstrapKingdomAdministrator::class)->handle($first, $admin);
        $this->app->make(AssignKingdomRole::class)
            ->handle($admin, $first, $target, DefaultKingdomRole::Viewer);

        try {
            $this->app->make(ResolvePlayer::class)
                ->handle($secondAlliance, 'Role Drift Target', 'role-drift-target');
            self::fail('Roster resolution must not become an implicit Kingdom transfer for a Player with Kingdom roles.');
        } catch (ValidationException) {
            self::assertSame($first->id, $target->refresh()->current_kingdom_id);
        }
    }

    public function test_database_rejects_player_kingdom_drift_while_a_kingdom_role_exists(): void
    {
        $first = Kingdom::query()->create(['number' => 1443, 'status' => 'active']);
        $second = Kingdom::query()->create(['number' => 1444, 'status' => 'active']);
        $admin = Player::query()->create([
            'current_kingdom_id' => $first->id,
            'game_player_id' => 'role-drift-db-admin',
            'current_name' => 'Role Drift DB Admin',
        ]);
        $this->app->make(BootstrapKingdomAdministrator::class)->handle($first, $admin);

        $this->expectException(QueryException::class);
        $admin->forceFill(['current_kingdom_id' => $second->id])->save();
    }

    public function test_final_kingdom_admin_cannot_be_removed_even_by_that_admin(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 1450, 'status' => 'active']);
        $admin = Player::query()->create([
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'final-admin',
            'current_name' => 'Final Admin',
        ]);
        $assignment = $this->app->make(BootstrapKingdomAdministrator::class)->handle($kingdom, $admin);

        $this->expectException(ValidationException::class);
        $this->app->make(RemoveKingdomRole::class)->handle($admin, $kingdom, $assignment);
    }

    public function test_r1_player_with_explicit_kingdom_admin_role_can_manage_roles_through_http_surface(): void
    {
        $ownerUser = User::factory()->create();
        $adminUser = User::factory()->create(['email' => 'kingdom-admin@example.com']);
        $targetUser = User::factory()->create(['email' => 'kingdom-viewer@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 1460, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $ownerUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'role-surface-owner',
            'current_name' => 'Role Surface Owner',
        ]);
        $adminPlayer = Player::query()->create([
            'user_id' => $adminUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'role-surface-admin',
            'current_name' => 'Role Surface Admin',
        ]);
        $targetPlayer = Player::query()->create([
            'user_id' => $targetUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'role-surface-viewer',
            'current_name' => 'Role Surface Viewer',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Role Surface', 'role-surface');
        $adminMembership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $adminPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        self::assertSame(AllianceRank::R1, $adminMembership->rank);
        $this->app->make(BootstrapKingdomAdministrator::class)->handle($kingdom, $adminPlayer);

        $session = [
            (string) config('identity.active_player_session_key') => $adminPlayer->id,
            'auth.password_confirmed_at' => time(),
        ];

        $this->actingAs($adminUser)
            ->withSession($session)
            ->get('/alliance/settings/kingdom/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/KingdomRoles')
                ->where('kingdom.number', 1460)
                ->where('alliance.id', (string) $alliance->id));

        $this->withSession($session)
            ->post('/alliance/settings/kingdom/roles', [
                'player_id' => $targetPlayer->id,
                'role' => DefaultKingdomRole::Viewer->value,
            ])
            ->assertRedirect();

        $assignment = KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdom->id)
            ->where('player_id', $targetPlayer->id)
            ->whereHas('role', static fn ($query) => $query->where('key', DefaultKingdomRole::Viewer->value))
            ->sole();

        $this->withSession($session)
            ->delete('/alliance/settings/kingdom/roles/'.$assignment->id)
            ->assertRedirect();
        self::assertFalse(KingdomRoleAssignment::query()->whereKey($assignment->id)->exists());
    }
}
