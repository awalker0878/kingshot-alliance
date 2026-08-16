<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Rallies\Actions\SavePlayerFormation;
use App\Contexts\Operations\Rallies\Models\PlayerFormation;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OperationsContextContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_scoped_event_authority_never_aggregates_players_owned_by_same_user(): void
    {
        $user = User::factory()->create();
        $kingdom = $this->kingdom(9101);
        $first = $this->player($user, $kingdom, 'ops-9101-a', 'Operations Alpha');
        $second = $this->player($user, $kingdom, 'ops-9101-b', 'Operations Bravo');
        $type = EventType::query()->where('slug', 'hall-of-governors')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Player);
        $create = $this->app->make(CreateEvent::class);

        $event = $create->handle(
            actor: $first,
            configuration: $configuration,
            target: $first,
            firstLocalStart: CarbonImmutable::parse('2026-09-01 12:00', 'UTC'),
            durationMinutes: 60,
        );

        self::assertSame((string) $first->id, (string) $event->player_id);
        self::assertSame((string) $first->id, (string) $event->created_by_player_id);

        $this->expectException(AuthorizationException::class);
        $create->handle(
            actor: $first,
            configuration: $configuration,
            target: $second,
            firstLocalStart: CarbonImmutable::parse('2026-09-02 12:00', 'UTC'),
            durationMinutes: 60,
        );
    }

    public function test_kingdom_operations_permissions_are_composed_outside_game_world_and_bound_to_exact_kingdom(): void
    {
        $user = User::factory()->create();
        $firstKingdom = $this->kingdom(9102);
        $secondKingdom = $this->kingdom(9103);
        $actor = $this->player($user, $firstKingdom, 'ops-9102-admin', 'Operations Administrator');
        $authorization = $this->app->make(EventAuthorization::class);

        self::assertFalse($authorization->allows(
            $actor,
            EventScope::Kingdom,
            $firstKingdom,
            OperationsPermission::EventKingdomCreate,
        ));

        $this->app->make(BootstrapKingdomAdministrator::class)->handle($firstKingdom, $actor);

        self::assertTrue($authorization->allows(
            $actor,
            EventScope::Kingdom,
            $firstKingdom,
            OperationsPermission::EventKingdomCreate,
        ));
        self::assertFalse($authorization->allows(
            $actor,
            EventScope::Kingdom,
            $secondKingdom,
            OperationsPermission::EventKingdomCreate,
        ));
    }

    public function test_kingdom_event_creation_uses_player_principal_after_governance_bootstrap(): void
    {
        $user = User::factory()->create();
        $kingdom = $this->kingdom(9104);
        $actor = $this->player($user, $kingdom, 'ops-9104-admin', 'Kingdom Scheduler');
        $this->app->make(BootstrapKingdomAdministrator::class)->handle($kingdom, $actor);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);

        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $kingdom,
            firstLocalStart: CarbonImmutable::parse('2026-09-03 19:00', 'UTC'),
            durationMinutes: 90,
        );

        self::assertSame(EventScope::Kingdom, $event->scope);
        self::assertSame((string) $kingdom->id, (string) $event->kingdom_id);
        self::assertSame((string) $actor->id, (string) $event->created_by_player_id);
        self::assertCount(1, $event->occurrences);
    }

    public function test_rally_formations_are_owned_by_player_not_user(): void
    {
        $user = User::factory()->create();
        $kingdom = $this->kingdom(9105);
        $first = $this->player($user, $kingdom, 'ops-9105-a', 'Formation Alpha');
        $second = $this->player($user, $kingdom, 'ops-9105-b', 'Formation Bravo');
        $this->app->make(CreateAlliance::class)->handle($first, 'Operations Rally', 'operations-rally');
        $save = $this->app->make(SavePlayerFormation::class);

        $save->handle($first, $first, 'Alpha Formation', new FormationComposition(10, 10, 80), isDefault: true);
        $save->handle($second, $second, 'Bravo Formation', new FormationComposition(20, 10, 70), isDefault: true);

        self::assertSame(['Alpha Formation'], PlayerFormation::query()->where('player_id', $first->id)->pluck('name')->all());
        self::assertSame(['Bravo Formation'], PlayerFormation::query()->where('player_id', $second->id)->pluck('name')->all());
    }

    private function kingdom(int $number): Kingdom
    {
        return Kingdom::query()->create([
            'number' => $number,
            'status' => 'active',
        ]);
    }

    private function player(User $user, Kingdom $kingdom, string $gamePlayerId, string $name): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }
}
