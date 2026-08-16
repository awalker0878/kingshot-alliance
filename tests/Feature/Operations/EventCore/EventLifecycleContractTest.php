<?php

declare(strict_types=1);

namespace Tests\Feature\Operations\EventCore;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Actions\CreateEventFromTemplate;
use App\Contexts\Operations\EventCore\Actions\CreateEventTemplate;
use App\Contexts\Operations\EventCore\Actions\UpdateEvent;
use App\Contexts\Operations\EventCore\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Enums\RecurrenceFrequency;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class EventLifecycleContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_scoped_events_are_owned_by_the_active_player_not_the_account(): void
    {
        $user = User::factory()->create();
        $factory = new ScenarioFactory;
        $first = $factory->playerFor($user, 4401, 'Alpha', 'game-4401-a')['player'];
        $second = $factory->playerFor($user, 4401, 'Bravo', 'game-4401-b')['player'];
        $type = EventType::query()->where('slug', 'hall-of-governors')->sole();
        $configuration = app(EventTypeRegistry::class)->scope($type, EventScope::Player);
        $create = app(CreateEvent::class);

        $firstEvent = $create->handle($first, $configuration, $first, CarbonImmutable::parse('2026-08-20 12:00', 'UTC'), durationMinutes: 60);
        $secondEvent = $create->handle($second, $configuration, $second, CarbonImmutable::parse('2026-08-21 12:00', 'UTC'), durationMinutes: 60);

        self::assertSame($first->id, $firstEvent->player_id);
        self::assertSame($second->id, $secondEvent->player_id);
        self::assertNotSame($firstEvent->id, $secondEvent->id);
        self::assertTrue(DB::table('outbox_messages')->where('aggregate_id', $firstEvent->id)->where('partition_key', 'player:'.$first->id)->exists());
        self::assertTrue(DB::table('outbox_messages')->where('aggregate_id', $secondEvent->id)->where('partition_key', 'player:'.$second->id)->exists());

        $this->expectException(AuthorizationException::class);
        $create->handle($first, $configuration, $second, CarbonImmutable::parse('2026-08-22 12:00', 'UTC'), durationMinutes: 60);
    }

    public function test_alliance_event_creation_is_tenant_scoped_and_emits_durable_state_change(): void
    {
        $scenario = (new ScenarioFactory)->allianceEvent(4402, 'custom', 'event-core-tenant');

        self::assertSame($scenario['alliance']->id, $scenario['event']->alliance_id);
        self::assertSame($scenario['player']->id, $scenario['event']->created_by_player_id);
        self::assertSame('alliance:'.$scenario['alliance']->id, DB::table('outbox_messages')->where('aggregate_id', $scenario['event']->id)->value('partition_key'));
    }

    public function test_template_snapshots_configuration_and_materializes_independent_event_occurrences(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4403, 'Template Owner', 'Template Alliance', 'event-template-v2');
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = app(EventTypeRegistry::class)->scope($type, EventScope::Alliance);

        $template = app(CreateEventTemplate::class)->handle(
            actor: $scenario['player'],
            configuration: $configuration,
            target: $scenario['alliance'],
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
        $event = app(CreateEventFromTemplate::class)->handle(
            actor: $scenario['player'],
            template: $template,
            firstLocalStart: CarbonImmutable::parse('2026-08-22 19:00', 'UTC'),
            recurrenceUntilLocal: CarbonImmutable::parse('2026-09-05 19:00', 'UTC'),
        );

        self::assertSame($template->id, $event->template_id);
        self::assertSame(90, $event->duration_minutes);
        self::assertSame(40, $event->capacity);
        self::assertSame(RecurrenceFrequency::Weekly, $event->recurrence_frequency);
        self::assertSame(['battle_plan' => ['formation' => 'alpha']], $event->settings);
        self::assertCount(3, $event->occurrences);
    }

    public function test_existing_event_keeps_its_schedule_policy_snapshot_when_catalogue_configuration_changes(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4404, 'Snapshot Owner', 'Snapshot Alliance', 'event-snapshot-v2');
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = app(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = app(CreateEvent::class)->handle(
            actor: $scenario['player'],
            configuration: $configuration,
            target: $scenario['alliance'],
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

        $updated = app(UpdateEvent::class)->handle($scenario['player'], $event, durationMinutes: 75);
        self::assertSame(EventRecurrencePolicy::Configurable, $updated->recurrence_policy);
        self::assertSame(RecurrenceFrequency::Weekly, $updated->recurrence_frequency);
        self::assertSame(75, $updated->duration_minutes);
    }

    public function test_calendar_driven_configuration_rejects_application_recurrence(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4405, 'Policy Owner', 'Policy Alliance', 'event-policy-v2');
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = app(EventTypeRegistry::class)->scope($type, EventScope::Alliance);

        $this->expectException(InvalidArgumentException::class);
        app(CreateEvent::class)->handle(
            actor: $scenario['player'],
            configuration: $configuration,
            target: $scenario['alliance'],
            firstLocalStart: CarbonImmutable::parse('2026-08-20 12:00', 'UTC'),
            frequency: RecurrenceFrequency::Weekly,
        );
    }
}
