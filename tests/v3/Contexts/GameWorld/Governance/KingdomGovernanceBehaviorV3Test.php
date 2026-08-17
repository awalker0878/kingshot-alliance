<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Governance;

use App\Contexts\GameWorld\Governance\Actions\BootstrapKingdomAdministrator;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class KingdomGovernanceBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_bootstrap_is_kingdom_scoped_durable_and_idempotent_for_same_player(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $player = $factory->player($owner->userId, 13001);
        $kingdom = $factory->kingdom(13001);

        $assignment = app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $player->playerId);
        $again = app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $player->playerId);

        self::assertSame($kingdom->kingdomId, $assignment->kingdomId);
        self::assertSame($player->playerId, $assignment->playerId);
        self::assertSame($assignment->assignmentId, $again->assignmentId);
        self::assertTrue(OutboxMessage::query()->where('event_type', 'kingdom.role_bootstrapped')->where('aggregate_id', $assignment->assignmentId)->exists());
    }

    public function test_bootstrap_rejects_player_from_another_kingdom(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $targetKingdom = $factory->kingdom(13002);
        $other = $factory->player($owner->userId, 13003);

        $this->expectException(ValidationException::class);
        app(BootstrapKingdomAdministrator::class)->handle($targetKingdom->kingdomId, $other->playerId);
    }
}
