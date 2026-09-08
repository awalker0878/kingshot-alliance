<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\CommandOverview;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\ReadModels\CommandOverview\Queries\AllianceCommandQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceCommandAndBriefV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_officer_only_recomputable_and_owner_linked(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78101);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        $command = app(AllianceCommandQuery::class)->for(
            $account->userId,
            $actor,
            $alliance->allianceId,
        );

        self::assertNotNull($command);
        self::assertGreaterThan(0, $command['actionCount']);
        self::assertSame('governor_observation_freshness', $command['items'][0]['code']);
        foreach ($command['items'] as $item) {
            self::assertNotSame('', $item['owner']);
            self::assertStringStartsWith('/', $item['handoff']['href']);
        }
        self::assertDatabaseMissing('events', ['title' => 'Alliance Command attention']);

        AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $actor->playerId)
            ->update(['rank' => AllianceRank::R4->value]);
        self::assertNotNull(app(AllianceCommandQuery::class)->for(
            $account->userId,
            $actor,
            $alliance->allianceId,
        ));
        self::assertNull(app(AllianceCommandQuery::class)->for(
            $account->userId + 1000,
            $actor,
            $alliance->allianceId,
        ));

        $memberAccount = $scenario->account();
        $member = $scenario->player($memberAccount->userId, 78101);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId,
            'player_id' => $member->playerId,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        self::assertNull(app(AllianceCommandQuery::class)->for(
            $memberAccount->userId,
            $member,
            $alliance->allianceId,
        ));
    }
}
