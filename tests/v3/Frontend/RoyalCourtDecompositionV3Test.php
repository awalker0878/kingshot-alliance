<?php

declare(strict_types=1);

namespace Tests\v3\Frontend;

use PHPUnit\Framework\TestCase;

final class RoyalCourtDecompositionV3Test extends TestCase
{
    public function test_royal_court_page_is_a_thin_coordinator_with_context_bound_child_forms(): void
    {
        $root = dirname(__DIR__, 3);
        $page = $this->source($root.'/resources/js/pages/Kingdom/RoyalCourt/Appointments.vue');

        foreach ([
            'RoyalCourtStrategy',
            'RoyalCourtLiveOperations',
            'RoyalCourtRequests',
            'RoyalCourtSmartFillForm',
            'RoyalCourtAppointments',
            'RoyalCourtAppointmentForm',
            'RoyalCourtSkills',
            'RoyalCourtSkillForm',
        ] as $component) {
            self::assertStringContainsString($component, $page);
        }

        self::assertLessThan(16_000, strlen($page), 'Royal Court coordinator regressed into an oversized page.');

        foreach ([
            'RoyalCourtSmartFillForm.vue',
            'RoyalCourtAppointmentForm.vue',
            'RoyalCourtSkillForm.vue',
        ] as $file) {
            $form = $this->source($root.'/resources/js/components/royal-court/'.$file);
            self::assertStringContainsString('useContextForm', $form, $file.' must remain Governor-context bound.');
        }
    }

    public function test_royal_court_domain_presentation_types_are_centralized(): void
    {
        $root = dirname(__DIR__, 3);
        $types = $this->source($root.'/resources/js/types/king-perks.ts');

        foreach ([
            'export type AppointmentType',
            'export type Appointment',
            'export type PerkRequest',
            'export type Skill',
            'export type Plan',
            'export type LiveCourt',
            'export type StrategyDay',
        ] as $expected) {
            self::assertStringContainsString($expected, $types);
        }
    }

    private function source(string $path): string
    {
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
