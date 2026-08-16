<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AllianceIntelligenceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_permissions_are_interpreted_by_intelligence(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9201, 'status' => 'active']);
        $owner = $this->player($kingdom, 'owner', 'Owner');
        $r4 = $this->player($kingdom, 'r4', 'R4');
        $r3 = $this->player($kingdom, 'r3', 'R3');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Intelligence Authority', 'intelligence-authority');
        $this->membership((string) $alliance->id, (string) $r4->id, AllianceRank::R4);
        $this->membership((string) $alliance->id, (string) $r3->id, AllianceRank::R3);

        $authorization = $this->app->make(AllianceIntelligenceAuthorization::class);

        self::assertTrue($authorization->allows($owner, $alliance, IntelligencePermission::KingdomManage));
        self::assertTrue($authorization->allows($owner, $alliance, IntelligencePermission::ContributionManage));
        self::assertTrue($authorization->allows($r4, $alliance, IntelligencePermission::KingdomManage));
        self::assertFalse($authorization->allows($r4, $alliance, IntelligencePermission::ContributionManage));
        self::assertFalse($authorization->allows($r3, $alliance, IntelligencePermission::KingdomManage));
        self::assertFalse($authorization->allows($r3, $alliance, IntelligencePermission::ContributionManage));
    }

    public function test_intelligence_authority_does_not_aggregate_across_players_owned_by_same_user(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9202, 'status' => 'active']);
        $user = User::factory()->create();
        $primary = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'intel-primary',
            'current_name' => 'Primary',
        ]);
        $farm = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'intel-farm',
            'current_name' => 'Farm',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($primary, 'Exact Intelligence Player', 'exact-intelligence-player');

        $authorization = $this->app->make(AllianceIntelligenceAuthorization::class);
        self::assertTrue($authorization->allows($primary, $alliance, IntelligencePermission::ContributionManage));
        self::assertFalse($authorization->allows($farm, $alliance, IntelligencePermission::KingdomManage));
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
