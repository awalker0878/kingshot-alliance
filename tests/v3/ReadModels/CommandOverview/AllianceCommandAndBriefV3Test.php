<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\CommandOverview;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\ReadModels\CommandOverview\Queries\AllianceCommandQuery;
use App\ReadModels\CommandOverview\Queries\OfficerBriefQuery;
use App\ReadModels\CommandOverview\Services\OfficerBriefNotificationPublisher;
use Illuminate\Auth\Access\AuthorizationException;
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

    public function test_brief_groups_and_delivery_fingerprints_are_stable_and_recheck_authority(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78102);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $command = app(AllianceCommandQuery::class)->for(
            $account->userId,
            $actor,
            $alliance->allianceId,
        );
        self::assertNotNull($command);

        $query = app(OfficerBriefQuery::class);
        $first = $query->for($actor, $alliance->allianceId, $command);
        $second = $query->for($actor, $alliance->allianceId, $command);

        self::assertSame(
            ['daily_officer', 'upcoming_event', 'post_event_closeout'],
            array_column($first, 'group'),
        );
        self::assertSame(array_column($first, 'fingerprint'), array_column($second, 'fingerprint'));

        $publisher = app(OfficerBriefNotificationPublisher::class);
        $one = $publisher->publish(
            $account->userId,
            $actor->playerId,
            $alliance->allianceId,
            $first[0],
        );
        $two = $publisher->publish(
            $account->userId,
            $actor->playerId,
            $alliance->allianceId,
            $first[0],
        );

        self::assertSame($one->deliveryIds, $two->deliveryIds);
        self::assertSame(1, NotificationDelivery::query()
            ->where('notification_type', OfficerBriefNotificationPublisher::NOTIFICATION_TYPE)
            ->count());
        $delivery = NotificationDelivery::query()
            ->where('notification_type', OfficerBriefNotificationPublisher::NOTIFICATION_TYPE)
            ->firstOrFail();
        $metadata = is_array($delivery->metadata) ? $delivery->metadata : [];

        self::assertSame('Daily Officer Brief', $metadata['title'] ?? null);
        self::assertSame('/', $metadata['action_url'] ?? null);
        self::assertStringContainsString('owner:', (string) ($metadata['body'] ?? ''));

        AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $actor->playerId)
            ->update(['rank' => AllianceRank::R1->value]);

        $this->expectException(AuthorizationException::class);
        $publisher->publish(
            $account->userId,
            $actor->playerId,
            $alliance->allianceId,
            $first[1],
        );
    }
}
