<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceDashboard;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\ReadModels\AllianceDashboard\Queries\MembershipManagementQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class MembershipManagementQueryBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_membership_administration_uses_bounded_cursor_pages_with_a_complete_total(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 57001);
        $alliance = $scenario->alliance($owner);

        foreach (range(0, 50) as $index) {
            $player = $scenario->unclaimedPlayer(57001);
            AllianceMembership::query()->create([
                'alliance_id' => $alliance->allianceId,
                'player_id' => $player->playerId,
                'status' => MembershipStatus::Active,
                'rank' => AllianceRank::R1,
                'joined_at' => now(),
            ]);
        }

        $query = app(MembershipManagementQuery::class);
        $first = $query->forAlliance($alliance->allianceId);

        self::assertSame(52, $first['total']);
        self::assertCount(50, $first['page']['items']);
        self::assertTrue($first['page']['hasMore']);
        self::assertTrue($first['page']['isFirstPage']);
        self::assertIsString($first['page']['nextCursor']);

        $second = $query->forAlliance($alliance->allianceId, $first['page']['nextCursor']);

        self::assertSame(52, $second['total']);
        self::assertCount(2, $second['page']['items']);
        self::assertFalse($second['page']['hasMore']);
        self::assertFalse($second['page']['isFirstPage']);
    }
}
