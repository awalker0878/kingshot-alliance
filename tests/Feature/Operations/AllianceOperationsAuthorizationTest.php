<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AllianceOperationsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_semantics_are_owned_by_operations_not_alliance(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9101, 'status' => 'active']);
        $owner = $this->player($kingdom, 'owner', 'Owner');
        $r4 = $this->player($kingdom, 'r4', 'R4');
        $member = $this->player($kingdom, 'member', 'Member');
        $coordinator = $this->player($kingdom, 'coordinator', 'Coordinator');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Operations Authority', 'operations-authority');

        $r4Membership = $this->membership($alliance->id, $r4->id, AllianceRank::R4);
        $this->membership($alliance->id, $member->id, AllianceRank::R1);
        $coordinatorMembership = $this->membership($alliance->id, $coordinator->id, AllianceRank::R1);
        $eventCoordinator = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();
        $coordinatorMembership->roles()->attach($eventCoordinator->id);

        $authorization = $this->app->make(AllianceOperationsAuthorization::class);

        self::assertTrue($authorization->allows($member, $alliance, OperationsPermission::EventAllianceView));
        self::assertFalse($authorization->allows($member, $alliance, OperationsPermission::EventAllianceManage));
        self::assertTrue($authorization->allows($r4, $alliance, OperationsPermission::EventAllianceManage));
        self::assertTrue($authorization->allows($r4, $alliance, OperationsPermission::EventPlayerManage));
        self::assertTrue($authorization->allows($coordinator, $alliance, OperationsPermission::EventAllianceCreate));
        self::assertTrue($authorization->allows($coordinator, $alliance, OperationsPermission::EventAllianceManage));
        self::assertFalse($authorization->allows($coordinator, $alliance, OperationsPermission::EventPlayerManage));

        self::assertSame(AllianceRank::R4, $r4Membership->rank);
    }

    public function test_authority_is_bound_to_exact_player_not_user(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9102, 'status' => 'active']);
        $user = User::factory()->create();
        $primary = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'shared-primary',
            'current_name' => 'Primary',
        ]);
        $farm = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'shared-farm',
            'current_name' => 'Farm',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($primary, 'Exact Player', 'exact-player');

        $authorization = $this->app->make(AllianceOperationsAuthorization::class);
        self::assertTrue($authorization->allows($primary, $alliance, OperationsPermission::EventAllianceManage));
        self::assertFalse($authorization->allows($farm, $alliance, OperationsPermission::EventAllianceView));
    }

    private function player(Kingdom $kingdom, string $gamePlayerId, string $name): Player
    {
        $user = User::factory()->create();

        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }

    private function membership(string $allianceId, string $playerId, AllianceRank $rank): AllianceMembership
    {
        return AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'player_id' => $playerId,
            'status' => MembershipStatus::Active,
            'rank' => $rank,
            'joined_at' => now(),
        ]);
    }
}
