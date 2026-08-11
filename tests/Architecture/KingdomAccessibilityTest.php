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
            'KingdomAlliances.vue',
            'KingdomAlliancesManage.vue',
            'KingdomAllianceHistory.vue',
            'KingdomAllianceDiplomacy.vue',
            'KingdomAllianceDiplomacyContacts.vue',
            'KingdomAllianceIntelligence.vue',
            'KingdomIngestionManage.vue',
            'TransferPlans.vue',
            'TransferPlansManage.vue',
            'TransferReadinessManage.vue',
            'TransferCompletionManage.vue',
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
            'KingdomAlliancesManage.vue',
            'KingdomAllianceHistory.vue',
            'KingdomAllianceDiplomacy.vue',
            'KingdomAllianceDiplomacyContacts.vue',
            'KingdomAllianceIntelligence.vue',
            'KingdomIngestionManage.vue',
            'TransferPlansManage.vue',
            'TransferReadinessManage.vue',
            'TransferCompletionManage.vue',
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

        $observationHistory = file_get_contents($root.'KingdomAllianceHistory.vue');
        self::assertIsString($observationHistory);
        foreach ([
            'for="observation-name"',
            'for="observation-tag"',
            'for="observation-power"',
            'for="observation-members"',
            'for="observation-captured"',
            'for="correction-reason"',
            'for="invalidation-reason"',
        ] as $label) {
            self::assertStringContainsString($label, $observationHistory);
        }

        $diplomacy = file_get_contents($root.'KingdomAllianceDiplomacy.vue');
        self::assertIsString($diplomacy);
        foreach ([
            'for="diplomacy-state"',
            'for="diplomacy-effective"',
            'for="diplomacy-review"',
            'for="diplomacy-expiry"',
            'for="diplomacy-terms"',
            'for="diplomacy-rationale"',
        ] as $label) {
            self::assertStringContainsString($label, $diplomacy);
        }

        $contacts = file_get_contents($root.'KingdomAllianceDiplomacyContacts.vue');
        self::assertIsString($contacts);
        foreach ([
            'for="contact-display-name"',
            'for="contact-game-role"',
            'for="contact-channel"',
            'for="contact-handle"',
            'for="contact-last-verified"',
            'for="contact-manager-notes"',
        ] as $label) {
            self::assertStringContainsString($label, $contacts);
        }

        $intelligence = file_get_contents($root.'KingdomAllianceIntelligence.vue');
        self::assertIsString($intelligence);
        foreach ([
            'for="tracking-filter"',
            'for="freshness-filter"',
            'for="diplomacy-filter"',
            'for="sort-filter"',
            'for="direction-filter"',
            '<caption class="sr-only">',
        ] as $accessibilityContract) {
            self::assertStringContainsString($accessibilityContract, $intelligence);
        }

        $ingestion = file_get_contents($root.'KingdomIngestionManage.vue');
        self::assertIsString($ingestion);
        self::assertStringContainsString('for="ingestion-adapter"', $ingestion);
        self::assertStringContainsString('<caption class="sr-only">', $ingestion);
    }

    public function test_transfer_readiness_and_completion_controls_keep_programmatic_context(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';
        $readiness = file_get_contents($root.'TransferReadinessManage.vue');
        $completion = file_get_contents($root.'TransferCompletionManage.vue');
        self::assertIsString($readiness);
        self::assertIsString($completion);

        self::assertStringContainsString('<fieldset', $readiness);
        self::assertStringContainsString('<legend', $readiness);
        self::assertStringContainsString(':for="`readiness-${participant.id}`"', $readiness);
        self::assertStringContainsString(':for="`blocker-summary-${participant.id}`"', $readiness);
        self::assertStringContainsString(':for="`blocker-details-${participant.id}`"', $readiness);

        self::assertStringContainsString(':for="`roster-result-${participant.id}`"', $completion);
        self::assertStringContainsString(':id="`roster-result-${participant.id}`"', $completion);
        self::assertStringContainsString('Record actual completion', $completion);
    }

    public function test_roster_transfer_and_kingdom_alliance_tables_keep_narrow_viewport_overflow(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';

        foreach ([
            'Roster.vue',
            'RosterHistory.vue',
            'RosterIntelligence.vue',
            'RosterImport.vue',
            'KingdomAlliances.vue',
            'KingdomAlliancesManage.vue',
            'KingdomAllianceHistory.vue',
            'KingdomAllianceDiplomacy.vue',
            'KingdomAllianceDiplomacyContacts.vue',
            'KingdomAllianceIntelligence.vue',
            'KingdomIngestionManage.vue',
            'TransferPlans.vue',
            'TransferPlansManage.vue',
        ] as $page) {
            $source = file_get_contents($root.$page);
            self::assertIsString($source);
            self::assertStringContainsString('<table', $source, $page.' must retain semantic tabular markup.');
            self::assertStringContainsString('<th', $source, $page.' must retain table headers.');
            self::assertStringContainsString('overflow-x-auto', $source, $page.' must retain horizontal overflow at narrow widths.');
        }
    }
}
