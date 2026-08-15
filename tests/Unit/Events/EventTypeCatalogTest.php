<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Domain\Events\Catalog\KingShotEventTypeCatalog;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventRecurrencePolicy;
use App\Domain\Events\Enums\EventScheduleSource;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Enums\RecurrenceFrequency;
use PHPUnit\Framework\TestCase;

final class EventTypeCatalogTest extends TestCase
{
    public function test_catalogue_has_unique_stable_slugs_and_supported_scopes(): void
    {
        $definitions = KingShotEventTypeCatalog::definitions();
        $slugs = array_column($definitions, 'slug');

        self::assertCount(count(array_unique($slugs)), $slugs);
        self::assertContains('bear-hunt', $slugs);
        self::assertContains('swordland-showdown', $slugs);
        self::assertContains('kingdom-of-power', $slugs);
        self::assertContains('custom', $slugs);

        $custom = $definitions[array_search('custom', $slugs, true)];
        self::assertSame(
            ['player', 'alliance', 'kingdom'],
            array_map(static fn (array $scope): string => $scope['scope']->value, $custom['scopes']),
        );
    }

    public function test_scope_permissions_are_exactly_scoped(): void
    {
        foreach (KingShotEventTypeCatalog::definitions() as $definition) {
            foreach ($definition['scopes'] as $scope) {
                $expected = match ($scope['scope']) {
                    EventScope::Player => [OperationsPermission::EventPlayerView, OperationsPermission::EventPlayerCreate, OperationsPermission::EventPlayerManage],
                    EventScope::Alliance => [OperationsPermission::EventAllianceView, OperationsPermission::EventAllianceCreate, OperationsPermission::EventAllianceManage],
                    EventScope::Kingdom => [OperationsPermission::EventKingdomView, OperationsPermission::EventKingdomCreate, OperationsPermission::EventKingdomManage],
                };

                self::assertSame($expected[0], $scope['view_permission']);
                self::assertSame($expected[1], $scope['create_permission']);
                self::assertSame($expected[2], $scope['manage_permission']);
            }
        }
    }

    public function test_competitive_types_expose_the_operational_capabilities_their_workspaces_need(): void
    {
        $bySlug = [];
        foreach (KingShotEventTypeCatalog::definitions() as $definition) {
            $bySlug[$definition['slug']] = $definition;
        }

        $swordland = $bySlug['swordland-showdown']['scopes'][0]['capabilities'];
        self::assertContains(EventCapability::Polls, $swordland);
        self::assertContains(EventCapability::Rosters, $swordland);
        self::assertContains(EventCapability::Substitutes, $swordland);
        self::assertContains(EventCapability::Objectives, $swordland);

        $bear = $bySlug['bear-hunt']['scopes'][0]['capabilities'];
        self::assertContains(EventCapability::RallyGuidance, $bear);
        self::assertContains(EventCapability::Formations, $bear);
        self::assertNotContains(EventCapability::Rosters, $bear);
    }

    public function test_recurrence_policy_is_type_aware_for_every_supported_event(): void
    {
        foreach (KingShotEventTypeCatalog::definitions() as $definition) {
            foreach ($definition['scopes'] as $scope) {
                self::assertInstanceOf(EventScheduleSource::class, $scope['schedule_source']);
                self::assertInstanceOf(EventRecurrencePolicy::class, $scope['recurrence_policy']);

                if ($definition['slug'] === 'bear-hunt') {
                    self::assertSame(EventScheduleSource::AllianceControlled, $scope['schedule_source']);
                    self::assertSame(EventRecurrencePolicy::FixedInterval, $scope['recurrence_policy']);
                    self::assertSame(RecurrenceFrequency::Daily, $scope['default_recurrence_frequency']);
                    self::assertSame(2, $scope['default_recurrence_interval']);
                    self::assertSame(2880, $scope['minimum_repeat_interval_minutes']);

                    continue;
                }

                if ($definition['slug'] === 'custom') {
                    self::assertSame(EventScheduleSource::Manual, $scope['schedule_source']);
                    self::assertSame(EventRecurrencePolicy::Configurable, $scope['recurrence_policy']);

                    continue;
                }

                self::assertSame(
                    EventRecurrencePolicy::Disabled,
                    $scope['recurrence_policy'],
                    $definition['slug'].' must follow its stored game schedule rather than application recurrence.',
                );
                self::assertSame(RecurrenceFrequency::None, $scope['default_recurrence_frequency']);
            }
        }
    }

    public function test_verified_gameplay_defaults_are_part_of_the_catalogue_contract(): void
    {
        $bySlug = [];
        foreach (KingShotEventTypeCatalog::definitions() as $definition) {
            $bySlug[$definition['slug']] = $definition['scopes'][0];
        }

        self::assertSame(2880, $bySlug['bear-hunt']['default_settings']['cooldown_minutes']);
        self::assertSame(7, $bySlug['viking-vengeance']['default_settings']['town_center_min']);
        self::assertSame(15, $bySlug['alliance-mobilization']['default_settings']['alliance_members_min']);
        self::assertSame(10, $bySlug['alliance-championship']['default_settings']['alliance_registrants_min']);
        self::assertSame(30, $bySlug['swordland-showdown']['default_settings']['combatant_capacity']);
        self::assertSame(2, $bySlug['tri-alliance-clash']['default_settings']['max_legions']);
        self::assertSame(60, $bySlug['flamedragon-tyrant']['default_settings']['combatant_capacity']);
        self::assertSame(20, $bySlug['swordland-summit-league']['default_settings']['substitute_capacity_per_legion']);
        self::assertSame(150, $bySlug['castle-battle']['default_settings']['consecutive_occupation_win_minutes']);
        self::assertSame(300, $bySlug['kingdom-of-power']['default_settings']['castle_battle_minutes']);
        self::assertSame(10, $bySlug['eternitys-reach']['default_registration_opens_minutes_before']);
    }
}
