<?php

declare(strict_types=1);

namespace Tests\Feature\Operations\EventCore;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Actions\CreateEventFromTemplate;
use App\Contexts\Operations\EventCore\Actions\CreateEventTemplate;
use App\Contexts\Operations\EventCore\Actions\UpdateEvent;
use App\Contexts\Operations\EventCore\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Enums\RecurrenceFrequency;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class EventSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_event_type_can_exist_independently_for_multiple_players_owned_by_one_user(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7801, 'status' => 'active']);
        $first = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '7801-a',
            'current_name' => 'Alpha',
        ]);
        $second = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '7801-b',
            'current_name' => 'Bravo',
        ]);
        $type = EventType::query()->where('slug', 'hall-of-governors')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Player);
        $create = $this->app->make(CreateEvent::class);

        $firstEvent = $create->handle(
            actor: $first,
            configuration: $configuration,
            target: $first,
            firstLocalStart: CarbonImmutable::parse('2026-08-20 12:00', 'UTC'),
            durationMinutes: 60,
        );
        $secondEvent = $create->handle(
            actor: $second,
            configuration: $configuration,
            target: $second,
            firstLocalStart: CarbonImmutable::parse('2026-08-21 12:00', 'UTC'),
            durationMinutes: 60,
        );

        self::assertSame((string) $type->id, (string) $firstEvent->event_type_id);
        self::assertSame((string) $type->id, (string) $secondEvent->event_type_id);
        self::assertNotSame((string) $firstEvent->id, (string) $secondEvent->id);
        self::assertSame((string) $first->id, (string) $firstEvent->player_id);
        self::assertSame((string) $second->id, (string) $secondEvent->player_id);
        self::assertSame((string) $first->id, (string) $firstEvent->created_by_player_id);
        self::assertSame((string) $second->id, (string) $secondEvent->created_by_player_id);
        self::assertTrue(OutboxMessage::query()->where('aggregate_id', $firstEvent->id)->where('partition_key', 'player:'.$first->id)->exists());
        self::assertTrue(OutboxMessage::query()->where('aggregate_id', $secondEvent->id)->where('partition_key', 'player:'.$second->id)->exists());
    }

    public function test_player_cannot_create_a_player_scoped_event_for_a_sibling_player_owned_by_the_same_user(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7802, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '7802-a',
            'current_name' => 'Actor',
        ]);
        $other = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '7802-b',
            'current_name' => 'Other',
        ]);
        $type = EventType::query()->where('slug', 'hall-of-governors')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Player);

        $this->expectException(AuthorizationException::class);
        $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $other,
            firstLocalStart: CarbonImmutable::parse('2026-08-20 12:00', 'UTC'),
            durationMinutes: 60,
        );
    }

    public function test_same_alliance_event_type_is_not_unique_across_alliances(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 7803, 'status' => 'active']);
        $secondKingdom = Kingdom::query()->create(['number' => 7804, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => '7803-r5',
            'current_name' => 'Alliance A R5',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => '7804-r5',
            'current_name' => 'Alliance B R5',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'Alliance A', 'event-alliance-a');
        $second = $createAlliance->handle($secondPlayer, 'Alliance B', 'event-alliance-b');
        $type = EventType::query()->where('slug', 'bear-hunt')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $create = $this->app->make(CreateEvent::class);

        $a = $create->handle($firstPlayer, $configuration, $first, CarbonImmutable::parse('2026-08-20 12:00', 'UTC'));
        $b = $create->handle($secondPlayer, $configuration, $second, CarbonImmutable::parse('2026-08-20 12:00', 'UTC'));

        self::assertNotSame((string) $a->id, (string) $b->id);
        self::assertSame((string) $first->id, (string) $a->alliance_id);
        self::assertSame((string) $second->id, (string) $b->alliance_id);
    }

    public function test_template_snapshots_event_configuration_and_can_schedule_a_new_event(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7806, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '7806-r5',
            'current_name' => 'Template Alliance R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Template Alliance', 'template-alliance');
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);

        $template = $this->app->make(CreateEventTemplate::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            name: 'Weekly operation',
            instructions: 'Use the saved operation plan.',
            durationMinutes: 90,
            capacity: 40,
            registrationOpensMinutesBefore: 1440,
            registrationClosesMinutesBefore: 60,
            frequency: RecurrenceFrequency::Weekly,
            recurrenceInterval: 1,
            settings: ['battle_plan' => ['formation' => 'alpha']],
        );

        $event = $this->app->make(CreateEventFromTemplate::class)->handle(
            actor: $ownerPlayer,
            template: $template,
            firstLocalStart: CarbonImmutable::parse('2026-08-22 19:00', 'UTC'),
            recurrenceUntilLocal: CarbonImmutable::parse('2026-09-05 19:00', 'UTC'),
        );

        self::assertSame((string) $template->id, (string) $event->template_id);
        self::assertSame(90, $event->duration_minutes);
        self::assertSame(40, $event->capacity);
        self::assertSame('Use the saved operation plan.', $event->instructions);
        self::assertSame(RecurrenceFrequency::Weekly, $event->recurrence_frequency);
        self::assertSame(['battle_plan' => ['formation' => 'alpha']], $event->settings);
        self::assertCount(3, $event->occurrences);
    }

    public function test_existing_event_keeps_its_snapshotted_schedule_policy_when_catalogue_defaults_change(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7807, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '7807-r5',
            'current_name' => 'Snapshot Alliance R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Snapshot Alliance', 'snapshot-alliance');
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);

        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::parse('2026-08-22 19:00', 'UTC'),
            durationMinutes: 60,
            frequency: RecurrenceFrequency::Weekly,
            recurrenceInterval: 1,
            recurrenceUntilLocal: CarbonImmutable::parse('2026-09-05 19:00', 'UTC'),
        );

        self::assertSame(EventRecurrencePolicy::Configurable, $event->recurrence_policy);

        $configuration->forceFill([
            'recurrence_policy' => EventRecurrencePolicy::Disabled,
            'default_recurrence_frequency' => RecurrenceFrequency::None,
            'default_recurrence_interval' => 1,
        ])->save();

        $updated = $this->app->make(UpdateEvent::class)->handle(
            actor: $ownerPlayer,
            event: $event,
            durationMinutes: 75,
        );

        self::assertSame(EventRecurrencePolicy::Configurable, $updated->recurrence_policy);
        self::assertSame(RecurrenceFrequency::Weekly, $updated->recurrence_frequency);
        self::assertSame(1, $updated->recurrence_interval);
        self::assertSame(75, $updated->duration_minutes);
    }

    public function test_calendar_driven_event_rejects_application_recurrence(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7805, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '7805-r5',
            'current_name' => 'Swordland R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Swordland', 'swordland-policy');
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::parse('2026-08-20 12:00', 'UTC'),
            frequency: RecurrenceFrequency::Weekly,
        );
    }
}
