<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Alliance\Core;

use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class AllianceLifecycleBehaviorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_claimed_player_creates_alliance_and_bootstraps_r5_membership(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->user();
        $player = $factory->player($user, 14001);
        $alliance = $factory->alliance($player);
        $membership = AllianceMembership::query()->where('alliance_id', $alliance->id)->where('player_id', $player->id)->firstOrFail();

        self::assertSame(MembershipStatus::Active, $membership->status);
        self::assertSame(AllianceRank::R5, $membership->rank);
        self::assertSame((string) $player->current_kingdom_id, (string) $alliance->kingdom_id);
        self::assertTrue(OutboxMessage::query()->where('event_type', 'alliance.created')->where('aggregate_id', (string) $alliance->id)->exists());

        $this->expectException(ValidationException::class);
        app(CreateAlliance::class)->handle($player, 'Second Alliance', 'second-alliance');
    }

    public function test_unclaimed_player_cannot_create_alliance(): void
    {
        $player = (new ScenarioFactory)->unclaimedPlayer(14002);

        $this->expectException(ValidationException::class);
        app(CreateAlliance::class)->handle($player, 'Invalid Alliance', 'invalid-alliance');
    }
}
