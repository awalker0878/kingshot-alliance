<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomAccessibilityTest extends TestCase
{
    public function test_kingdoms_surfaces_keep_semantic_landmarks_and_native_controls(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';

        foreach ([
            'KingdomSettings.vue',
            'Roster.vue',
            'RosterManage.vue',
            'RosterHistory.vue',
            'RosterIntelligence.vue',
            'RosterImport.vue',
        ] as $page) {
            $source = file_get_contents($root.$page);
            self::assertIsString($source);
            self::assertStringContainsString('<main', $source, $page.' must retain a main landmark.');
            self::assertStringContainsString('<h1', $source, $page.' must retain a primary heading.');
            self::assertStringNotContainsString('role="button"', $source, $page.' must use native interactive controls.');
        }
    }

    public function test_forms_and_csv_ambiguity_controls_remain_explicitly_labelled(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';

        foreach ([
            'KingdomSettings.vue',
            'Roster.vue',
            'RosterManage.vue',
            'RosterHistory.vue',
            'RosterImport.vue',
        ] as $page) {
            $source = file_get_contents($root.$page);
            self::assertIsString($source);
            self::assertStringContainsString('<label', $source, $page.' must retain explicit form labels.');
        }

        $import = file_get_contents($root.'RosterImport.vue');
        self::assertIsString($import);
        self::assertStringContainsString(':aria-label="resolutionLabel(row)"', $import);
        self::assertStringContainsString('Resolution for CSV row', $import);
        self::assertStringContainsString('aria-live="polite"', $import);
        self::assertStringContainsString('role="alert"', $import);
    }

    public function test_roster_history_intelligence_and_import_tables_keep_narrow_viewport_overflow(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';

        foreach ([
            'Roster.vue',
            'RosterHistory.vue',
            'RosterIntelligence.vue',
            'RosterImport.vue',
        ] as $page) {
            $source = file_get_contents($root.$page);
            self::assertIsString($source);
            self::assertStringContainsString('<table', $source, $page.' must retain semantic tabular markup.');
            self::assertStringContainsString('<th', $source, $page.' must retain table headers.');
            self::assertStringContainsString('overflow-x-auto', $source, $page.' must retain horizontal overflow at narrow widths.');
        }
    }
}
