<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\GameWorld\Governance;

use App\Contexts\GameWorld\Governance\Actions\BootstrapKingdomAdministrator;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class KingdomGovernanceBehaviorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_bootstrap_is_single_kingdom_scoped_and_durable(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->user();
        $player = $factory->player($owner, 13001);
        $kingdom = $factory->kingdom(13001);

        $assignment = app(BootstrapKingdomAdministrator::class)->handle($kingdom, $player);
        self::assertSame((string) $kingdom->id, (string) $assignment->kingdom_id);
        self::assertSame((string) $player->id, (string) $assignment->player_id);
        self::assertTrue(OutboxMessage::query()->where('event_type', 'kingdom.role_bootstrapped')->where('aggregate_id', (string) $assignment->id)->exists());

        $this->expectException(ValidationException::class);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom, $player);
    }

    public function test_bootstrap_rejects_player_from_another_kingdom(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->user();
        $targetKingdom = $factory->kingdom(13002);
        $other = $factory->player($owner, 13003);

        $this->expectException(ValidationException::class);
        app(BootstrapKingdomAdministrator::class)->handle($targetKingdom, $other);
    }
}
