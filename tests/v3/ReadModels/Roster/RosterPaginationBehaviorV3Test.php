<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\Roster;

use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\ReadModels\Roster\Queries\RosterQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RosterPaginationBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_roster_pages_are_complete_and_summary_counts_are_not_page_limited(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 56001);
        $alliance = $scenario->alliance($owner);

        foreach (range(0, 50) as $index) {
            $player = $scenario->unclaimedPlayer(56001);
            AllianceRosterEntry::query()->create([
                'alliance_id' => $alliance->allianceId,
                'player_id' => $player->playerId,
                'observed_name' => sprintf('Governor %02d', $index),
                'state' => RosterState::Active,
                'source' => 'manual',
            ]);
        }

        $query = app(RosterQuery::class);
        $first = $query->pageForAlliance($alliance->allianceId);
        self::assertCount(50, $first->items);
        self::assertNotNull($first->nextCursor);
        self::assertTrue($first->isFirstPage);

        $second = $query->pageForAlliance(
            $alliance->allianceId,
            cursor: $first->nextCursor,
        );
        self::assertCount(1, $second->items);
        self::assertNull($second->nextCursor);
        self::assertFalse($second->isFirstPage);

        self::assertSame([
            'total' => 51,
            'current' => 0,
            'stale' => 0,
            'missing' => 51,
            'linked' => 0,
        ], $query->summaryForAlliance($alliance->allianceId));
    }
}
