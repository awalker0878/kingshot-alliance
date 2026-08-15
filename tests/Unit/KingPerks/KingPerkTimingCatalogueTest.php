<?php

declare(strict_types=1);

namespace Tests\Unit\KingPerks;

use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KingPerkTimingCatalogueTest extends TestCase
{
    /** @return iterable<string, array{KingAppointmentType}> */
    public static function appointmentTypes(): iterable
    {
        foreach (KingAppointmentType::cases() as $type) {
            yield $type->value => [$type];
        }
    }

    #[DataProvider('appointmentTypes')]
    public function test_appointment_timing_rules_are_explicit(KingAppointmentType $type): void
    {
        self::assertSame(30, $type->durationMinutes());
        self::assertSame(60, $type->playerCooldownMinutes());
        self::assertSame(30, $type->cancelledPositionCooldownMinutes());
    }

    public function test_king_skills_expose_the_advance_scheduling_window_without_inventing_effect_duration(): void
    {
        foreach (KingSkill::cases() as $skill) {
            self::assertSame(2880, $skill->advanceSchedulingMinutes());
            self::assertNotSame('', $skill->recommendedFocus());
        }
    }
}
