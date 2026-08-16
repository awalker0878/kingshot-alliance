<?php

declare(strict_types=1);

namespace Tests\TenantIsolation\Alliance;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class AllianceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorization_and_database_constraints_reject_cross_alliance_role_leakage(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4401]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'isolation-first-owner',
            'current_name' => 'Isolation First Owner',
        ]);
        $secondOwnerPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'isolation-second-owner',
            'current_name' => 'Isolation Second Owner',
        ]);
        $firstOwnerSecondPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'isolation-first-user-second-player',
            'current_name' => 'Isolation Sibling Player',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);

        $firstAlliance = $createAlliance->handle($firstPlayer, 'First Alliance', 'first-alliance');
        $secondAlliance = $createAlliance->handle($secondOwnerPlayer, 'Second Alliance', 'second-alliance');
        $secondMembership = AllianceMembership::query()->create([
            'alliance_id' => $secondAlliance->id,
            'player_id' => $firstOwnerSecondPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $authorization = $this->app->make(AllianceAuthorization::class);

        self::assertTrue($authorization->allows($firstPlayer, $firstAlliance, AlliancePermission::Manage));
        self::assertFalse($authorization->allows($firstPlayer, $secondAlliance, AlliancePermission::View));
        self::assertTrue($authorization->allows($firstOwnerSecondPlayer, $secondAlliance, AlliancePermission::View));
        self::assertFalse($authorization->allows($firstOwnerSecondPlayer, $secondAlliance, AlliancePermission::Manage));

        $firstAllianceRole = Role::query()
            ->where('alliance_id', $firstAlliance->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();

        try {
            DB::transaction(static function () use ($secondMembership, $firstAllianceRole, $secondAlliance): void {
                $secondMembership->roles()->attach($firstAllianceRole->id, [
                    'alliance_id' => $secondAlliance->id,
                ]);
            });

            self::fail('The database must reject a role owned by another alliance.');
        } catch (QueryException) {
            self::assertFalse($authorization->allows($firstPlayer, $secondAlliance, AlliancePermission::View));
            self::assertTrue($authorization->allows($firstOwnerSecondPlayer, $secondAlliance, AlliancePermission::View));
            self::assertFalse($authorization->allows($firstOwnerSecondPlayer, $secondAlliance, AlliancePermission::Manage));
        }
    }

    public function test_suspended_membership_fails_closed(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4411]);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'isolation-suspended-owner',
            'current_name' => 'Isolation Suspended Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($player, 'Suspended Alliance', 'suspended-alliance');

        AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->update(['status' => MembershipStatus::Suspended->value]);

        self::assertFalse($this->app->make(AllianceAuthorization::class)
            ->allows($player, $alliance, AlliancePermission::View));
    }

    public function test_alliance_context_can_be_activated_and_cleared(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4421]);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'isolation-context-owner',
            'current_name' => 'Isolation Context Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($player, 'Context Alliance', 'context-alliance');
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->sole();
        $context = $this->app->make(AllianceContext::class);

        $context->activate($player, $membership, $alliance);
        self::assertSame($alliance->id, $context->alliance()->id);
        self::assertSame($player->id, $context->player()->id);
        self::assertSame($player->id, $context->membership()->player_id);

        $context->clear();

        $this->expectException(LogicException::class);
        $context->alliance();
    }

    public function test_sibling_player_cannot_activate_another_players_alliance_context(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4431]);
        $memberPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'isolation-member-player',
            'current_name' => 'Isolation Member Player',
        ]);
        $siblingPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'isolation-sibling-player',
            'current_name' => 'Isolation Sibling Player Two',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($memberPlayer, 'Private Alliance', 'private-alliance');
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $memberPlayer->id)
            ->sole();

        $this->expectException(LogicException::class);
        $this->app->make(AllianceContext::class)->activate($siblingPlayer, $membership, $alliance);
    }
}
