<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventMetricSubject;
use App\Domain\Events\Enums\EventRecurrencePolicy;
use App\Domain\Events\Enums\EventScheduleSource;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventCapabilityResolver;
use App\Domain\Events\Services\EventTypeDefaultsResolver;
use App\Domain\Events\Services\EventTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventTypeCataloguePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_is_available_immediately_after_migration(): void
    {
        self::assertTrue(EventType::query()->where('slug', 'bear-hunt')->exists());
        self::assertTrue(EventType::query()->where('slug', 'kingdom-of-power')->exists());
        self::assertTrue(EventType::query()->where('slug', 'custom')->exists());
    }

    public function test_registry_resolves_scope_and_capabilities(): void
    {
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);

        self::assertSame(EventScope::Alliance, $scope->scope);
        self::assertTrue($this->app->make(EventCapabilityResolver::class)->supports($scope, EventCapability::Rosters));
        self::assertTrue($this->app->make(EventCapabilityResolver::class)->supports($scope, EventCapability::Polls));
        self::assertSame(
            ['kills', 'objective_captures', 'objective_occupation_seconds'],
            $scope->metricDefinitions->pluck('key')->all(),
        );
    }

    public function test_defaults_are_persisted_and_resolved_from_database(): void
    {
        $bear = EventType::query()->where('slug', 'bear-hunt')->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($bear, EventScope::Alliance);

        self::assertSame(EventScheduleSource::AllianceControlled, $scope->schedule_source);
        self::assertSame(EventRecurrencePolicy::FixedInterval, $scope->recurrence_policy);
        self::assertSame(2880, $scope->minimum_repeat_interval_minutes);
        self::assertSame('events.metrics.damage', $scope->result_score_label_key);
        self::assertSame('damage', $scope->result_score_unit);
        self::assertTrue($scope->result_score_higher_is_better);

        $defaults = $this->app->make(EventTypeDefaultsResolver::class)->resolve($scope);
        self::assertTrue($defaults['recurrence_allowed']);
        self::assertSame('daily', $defaults['default_recurrence_frequency']);
        self::assertSame(2, $defaults['default_recurrence_interval']);
        self::assertSame(2880, $defaults['default_settings']['cooldown_minutes']);
        self::assertArrayHasKey('rally_guidance', $defaults['capabilities']);
        self::assertSame([
            'label_key' => 'events.metrics.damage',
            'unit' => 'damage',
            'higher_is_better' => true,
        ], $defaults['result_score']);

        $metrics = $defaults['metrics'];
        self::assertIsArray($metrics);
        self::assertSame(['rallies_joined', 'rallies_led'], array_column($metrics, 'key'));
    }

    public function test_kingdom_event_metrics_persist_alliance_and_player_subjects(): void
    {
        $castle = EventType::query()->where('slug', 'castle-battle')->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($castle, EventScope::Kingdom);
        $bySubject = [];

        foreach ($scope->metricDefinitions as $definition) {
            $bySubject[$definition->subject->value][] = $definition->key;
        }

        self::assertSame(['objective_occupation_seconds'], $bySubject[EventMetricSubject::Event->value]);
        self::assertSame(
            ['carnage_points', 'occupation_points', 'casualty_points'],
            $bySubject[EventMetricSubject::Alliance->value],
        );
        self::assertSame(
            ['carnage_points', 'occupation_points', 'casualty_points'],
            $bySubject[EventMetricSubject::Player->value],
        );
    }

    public function test_kingdom_of_power_phase_metric_is_explicitly_dimensioned(): void
    {
        $type = EventType::query()->where('slug', 'kingdom-of-power')->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);

        self::assertCount(3, $scope->metricDefinitions);
        foreach ($scope->metricDefinitions as $definition) {
            self::assertSame('phase_points', $definition->key);
            self::assertSame('phase', $definition->dimension_kind);
        }
    }

    public function test_calendar_driven_event_defaults_disable_application_recurrence(): void
    {
        $swordland = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($swordland, EventScope::Alliance);
        $defaults = $this->app->make(EventTypeDefaultsResolver::class)->resolve($scope);

        self::assertSame(EventScheduleSource::GameCalendar->value, $defaults['schedule_source']);
        self::assertSame(EventRecurrencePolicy::Disabled->value, $defaults['recurrence_policy']);
        self::assertFalse($defaults['recurrence_allowed']);
        self::assertSame('none', $defaults['default_recurrence_frequency']);
        self::assertSame(2880, $defaults['capabilities']['phases']['voting_minutes']);

        $score = $defaults['result_score'];
        self::assertIsArray($score);
        self::assertSame('events.metrics.relic_points', $score['label_key']);
    }
}
