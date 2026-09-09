<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Tests\Support\ScenarioFactory;

final class AllianceRoleVisualFixture
{
    public static function seed(): void
    {
        $factory = app(ScenarioFactory::class);
        $user = User::factory()->create(['name' => 'Role Boundary Visual', 'email' => 'role-boundary-visual@example.test']);
        $player = $factory->player((int) $user->id, 59353);
        $alliance = $factory->alliance($player);
        $member = $factory->unclaimedPlayer(59353);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId,
            'player_id' => $member->playerId,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        for ($index = 0; $index < 35; $index++) {
            app(CreateAllianceRole::class)->handle($alliance->allianceId, $player->playerId, sprintf('Picker target %02d', $index), []);
        }
    }
}
