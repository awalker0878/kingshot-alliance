<?php

declare(strict_types=1);

namespace Tests\Unit\KingPerks;

use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPushCategory;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use App\Contexts\Operations\KingPerks\Services\KingPerkPreparationPresetCatalog;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class KingPerkPlanningCatalogTest extends TestCase
{
    public function test_appointment_time_rules_are_explicit_and_duration_aware(): void
    {
        foreach (KingAppointmentType::cases() as $type) {
            self::assertSame(30, $type->durationMinutes());
            self::assertSame(60, $type->playerCooldownMinutes());
            self::assertSame('appointment_end', $type->playerCooldownAnchor());
            self::assertSame(30, $type->cancelledPositionCooldownMinutes());
        }
    }

    public function test_training_smart_schedule_prefers_noble_advisor_then_chief_minister_overflow(): void
    {
        self::assertSame(
            [KingAppointmentType::NobleAdvisor, KingAppointmentType::ChiefMinister],
            KingPerkPushCategory::Training->preferredAppointments(),
        );
    }

    public function test_categories_do_not_share_a_universal_priority_list(): void
    {
        self::assertSame(
            [KingAppointmentType::ChiefMinister],
            KingPerkPushCategory::Construction->preferredAppointments(),
        );
        self::assertSame(
            [KingAppointmentType::ChiefMinister],
            KingPerkPushCategory::Research->preferredAppointments(),
        );
        self::assertSame(
            [KingAppointmentType::MinisterOfInterior],
            KingPerkPushCategory::Healing->preferredAppointments(),
        );
        self::assertSame(
            [KingAppointmentType::FieldCommander, KingAppointmentType::Marshal],
            KingPerkPushCategory::Combat->preferredAppointments(),
        );
    }

    public function test_preparation_presets_remain_strategy_recommendations(): void
    {
        $days = (new KingPerkPreparationPresetCatalog)->forWindow(
            CarbonImmutable::parse('2026-09-01 00:00', 'UTC'),
            CarbonImmutable::parse('2026-09-06 10:00', 'UTC'),
        );

        self::assertCount(6, $days);
        self::assertSame('construction', $days[0]['focus']);
        self::assertSame(KingSkill::Groundworks->value, $days[0]['skill']);
        self::assertSame('training', $days[3]['focus']);
        self::assertSame(KingSkill::Mobilize->value, $days[3]['skill']);
        self::assertSame(
            [KingAppointmentType::NobleAdvisor->value, KingAppointmentType::ChiefMinister->value],
            $days[3]['appointmentTypes'],
        );
        self::assertStringContainsString('Strategy preset', $days[3]['strategyNote']);
    }
}
