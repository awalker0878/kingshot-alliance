<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Lifecycle;

use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceLifecycleBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_claimed_player_creates_alliance_and_bootstraps_r5_membership(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->account();
        $player = $factory->player($user->userId, 14001);
        $alliance = $factory->alliance($player);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $player->playerId)
            ->firstOrFail();

        self::assertSame(MembershipStatus::Active, $membership->status);
        self::assertSame(AllianceRank::R5, $membership->rank);
        self::assertSame($player->kingdomId, $alliance->kingdomId);
        self::assertTrue(OutboxMessage::query()->where('event_type', 'alliance.created')->where('aggregate_id', $alliance->allianceId)->exists());

        $this->expectException(ValidationException::class);
        app(CreateAlliance::class)->handle($player->playerId, 'Second Alliance', 'second-alliance');
    }

    public function test_unclaimed_player_cannot_create_alliance(): void
    {
        $player = (new ScenarioFactory)->unclaimedPlayer(14002);

        $this->expectException(ValidationException::class);
        app(CreateAlliance::class)->handle($player->playerId, 'Invalid Alliance', 'invalid-alliance');
    }
}
