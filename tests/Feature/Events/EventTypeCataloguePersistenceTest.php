<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Events\Enums\EventCapability;
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
    }

    public function test_defaults_are_persisted_and_resolved_from_database(): void
    {
        $bear = EventType::query()->where('slug', 'bear-hunt')->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($bear, EventScope::Alliance);

        self::assertSame(EventScheduleSource::AllianceControlled, $scope->schedule_source);
        self::assertSame(EventRecurrencePolicy::FixedInterval, $scope->recurrence_policy);
        self::assertSame(2880, $scope->minimum_repeat_interval_minutes);

        $defaults = $this->app->make(EventTypeDefaultsResolver::class)->resolve($scope);
        self::assertTrue($defaults['recurrence_allowed']);
        self::assertSame('daily', $defaults['default_recurrence_frequency']);
        self::assertSame(2, $defaults['default_recurrence_interval']);
        self::assertSame(2880, $defaults['default_settings']['cooldown_minutes']);
        self::assertArrayHasKey('rally_guidance', $defaults['capabilities']);
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
    }
}
