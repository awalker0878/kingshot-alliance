<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Shared\Audit\Models\AuditEvent;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\SaveEventPlayerResult;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventPlayerContext;
use App\Domain\Events\Models\EventPlayerResultMetric;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Contexts\Accounts\Models\User;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Platform\Models\PlatformAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventHistorySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_has_no_alliance_history_bypass(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9011, 'status' => 'active']);
        $leader = $this->player(User::factory()->create(), $kingdom, 'Leader', '9011-leader');
        $alliance = $this->app->make(CreateAlliance::class)->handle($leader, 'Secured History', 'secured-history');

        $platformUser = User::factory()->create();
        PlatformAdministrator::query()->create([
            'user_id' => $platformUser->id,
            'granted_by_user_id' => $platformUser->id,
            'granted_at' => now(),
        ]);
        $platformPlayer = $this->player($platformUser, $kingdom, 'Platform Admin Player', '9011-platform');

        $this->actingAs($platformUser)
            ->withSession([(string) config('game_world.active_player_session_key') => $platformPlayer->id])
            ->get(route('alliances.events.history', ['alliance' => $alliance->id]))
            ->assertForbidden();
    }

    public function test_result_writes_are_idempotent_for_context_and_logical_metric_identity(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9012, 'status' => 'active']);
        $user = User::factory()->create();
        $player = $this->player($user, $kingdom, 'Idempotent Player', '9012-player');
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Idempotent Alliance', 'idempotent-alliance');
        $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            ['name' => 'Idempotent Player', 'game_player_id' => '9012-player'],
            expectedPlayerId: (string) $player->id,
        );
        $eventType = EventType::query()->where('slug', 'bear-hunt')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($eventType, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addHour(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $save = $this->app->make(SaveEventPlayerResult::class);

        $first = $save->handle(
            $player,
            $occurrence,
            $player,
            score: 100,
            metrics: [['key' => 'rallies_joined', 'value' => 3]],
        );
        $second = $save->handle(
            $player,
            $occurrence,
            $player,
            score: 200,
            metrics: [['key' => 'rallies_joined', 'value' => 5]],
        );

        self::assertSame((string) $first->id, (string) $second->id);
        self::assertSame(1, EventPlayerContext::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('player_id', $player->id)
            ->count());
        self::assertSame(1, EventPlayerResultMetric::query()
            ->where('event_player_result_id', $second->id)
            ->count());
        self::assertSame('5.0000', (string) EventPlayerResultMetric::query()
            ->where('event_player_result_id', $second->id)
            ->sole()
            ->value);
    }

    public function test_event_result_audit_is_attributed_to_the_actor_player(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9013, 'status' => 'active']);
        $user = User::factory()->create();
        $player = $this->player($user, $kingdom, 'Audit Player', '9013-player');
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Audit Alliance', 'audit-history');
        $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            ['name' => 'Audit Player', 'game_player_id' => '9013-player'],
            expectedPlayerId: (string) $player->id,
        );
        $eventType = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($eventType, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addHour(),
            durationMinutes: 60,
        );
        $result = $this->app->make(SaveEventPlayerResult::class)->handle(
            $player,
            $event->occurrences->firstOrFail(),
            $player,
            score: 42,
        );

        $audit = AuditEvent::query()
            ->where('event', 'event.player_result.recorded')
            ->where('subject_id', $result->id)
            ->sole();

        self::assertSame((string) $player->id, (string) $audit->actor_player_id);
        self::assertNull($audit->actor_user_id);
        self::assertSame((string) $player->id, (string) $audit->metadata['actor_player_id']);
    }

    private function player(User $user, Kingdom $kingdom, string $name, string $gamePlayerId): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }
}
