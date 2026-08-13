<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RosterExperienceTest extends TestCase
{
    public function test_roster_pages_use_shared_shell_and_preserve_real_routes(): void
    {
        $roster = $this->read('resources/js/pages/Alliance/Roster.vue');
        $intelligence = $this->read('resources/js/pages/Alliance/RosterIntelligence.vue');
        $manage = $this->read('resources/js/pages/Alliance/RosterManage.vue');
        $import = $this->read('resources/js/pages/Alliance/RosterImport.vue');
        $history = $this->read('resources/js/pages/Alliance/RosterHistory.vue');

        foreach ([$roster, $intelligence, $manage, $import, $history] as $source) {
            self::assertStringContainsString('AppLayout', $source);
            self::assertMatchesRegularExpression('/:?has-active-alliance\s*=\s*"true"/', $source);
            self::assertStringNotContainsString('<main', $source);
            self::assertStringContainsString('<h1', $source);
            self::assertStringNotContainsString('role="button"', $source);
        }

        foreach ([
            '/alliance/roster',
            '/alliance/roster/intelligence',
            '/alliance/roster/manage',
            '/alliance/roster/${entry.id}/history',
        ] as $route) {
            self::assertStringContainsString($route, $roster.$intelligence.$manage.$history);
        }

        foreach ([
            'filters.q',
            'filters.state',
            'filters.linkage',
            'filters.role',
            'filters.observation',
            'clearFilters',
            'applyFilters',
            'preserveState: true',
            'replace: true',
        ] as $contract) {
            self::assertStringContainsString($contract, $roster);
        }

        foreach ([
            "createForm.post('/alliance/roster'",
            'router.patch(`/alliance/roster/${entry.id}`',
            'router.post(`/alliance/roster/${entry.id}/leave`',
            'href="/alliance/roster/import"',
        ] as $contract) {
            self::assertStringContainsString($contract, $manage);
        }

        foreach ([
            "uploadForm.post('/alliance/roster/import/preview'",
            'commitForm.post(`/alliance/roster/import/${props.importRecord.id}/commit`',
            '/alliance/roster/export.csv?scope=member',
            '/alliance/roster/export.csv?scope=management',
        ] as $contract) {
            self::assertStringContainsString($contract, $import);
        }

        self::assertStringContainsString(
            '.post(`/alliance/roster/${props.entry.id}/snapshots`',
            $history,
        );
    }

    public function test_roster_views_have_intentional_mobile_and_desktop_presentations(): void
    {
        $roster = $this->read('resources/js/pages/Alliance/Roster.vue');
        $intelligence = $this->read('resources/js/pages/Alliance/RosterIntelligence.vue');
        $import = $this->read('resources/js/pages/Alliance/RosterImport.vue');
        $history = $this->read('resources/js/pages/Alliance/RosterHistory.vue');

        foreach ([$roster, $intelligence, $import, $history] as $source) {
            self::assertStringContainsString('lg:hidden', $source);
            self::assertStringContainsString('hidden overflow-x-auto lg:block', $source);
            self::assertStringContainsString('ks-surface-gold', $source);
        }

        foreach ([$roster, $intelligence, $history] as $source) {
            self::assertStringContainsString('xl:sticky xl:top-24', $source);
        }

        self::assertStringContainsString('linkedResults', $roster);
        self::assertStringContainsString('snapshotPercent', $roster);
        self::assertStringContainsString('snapshotTone', $intelligence);
        self::assertStringContainsString('rosterStateTone', $intelligence);
        self::assertStringContainsString('outcomeTone', $import);
        self::assertStringContainsString('freshnessTone', $history);
    }

    public function test_roster_intelligence_only_presents_recorded_metrics(): void
    {
        $source = $this->read('resources/js/pages/Alliance/RosterIntelligence.vue');

        foreach ([
            'metrics.trackedPlayers',
            'metrics.recordedPowerPlayers',
            'metrics.totalPower',
            'metrics.averagePower',
            'metrics.medianPower',
            'metrics.snapshotQuality.current',
            'metrics.snapshotQuality.stale',
            'metrics.snapshotQuality.missing',
            'metrics.recentRoster.joins',
            'metrics.recentRoster.departures',
            'metrics.linkage.percent',
            'metrics.sevenDayTrend.change',
            'metrics.thirtyDayTrend.change',
            'metrics.comparisons',
        ] as $metric) {
            self::assertStringContainsString($metric, $source);
        }

        foreach ([
            'Top Players',
            'Top Kingdom',
            'All Kingdoms',
            'Last Active',
            'Very Active',
            'Power Distribution',
            'Role Distribution',
            'Kingdom Distribution',
            'Auto snapshot every',
            'Download snapshot',
            'AI insights',
            'AI recommendations',
            'AI forecast',
            'AI analysis',
        ] as $invented) {
            self::assertStringNotContainsString($invented, $source);
        }
    }

    public function test_roster_catalogues_cover_all_supported_locales(): void
    {
        $catalogues = [
            $this->read('resources/js/localization/messages/roster.ts'),
            $this->read('resources/js/localization/messages/roster-management.ts'),
            $this->read('resources/js/localization/messages/roster-workflows.ts'),
        ];

        foreach ($catalogues as $source) {
            foreach ([
                'en',
                'ar',
                'de',
                'es',
                'fr',
                'id',
                'it',
                'ja',
                'ko',
                'pl',
                'pt-BR',
                'ru',
                'th',
                'tr',
                'vi',
                'zh-CN',
                'zh-TW',
            ] as $locale) {
                $present = str_contains($source, $locale.': locale(')
                    || str_contains($source, "'".$locale."': locale(")
                    || str_contains($source, '"'.$locale.'": locale(');

                self::assertTrue($present, 'Missing roster locale '.$locale);
            }
        }

        $roster = $catalogues[0];
        foreach ([
            'title',
            'freshnessHelp',
            'intelligenceTitle',
            'snapshotQuality',
            'sevenDayChange',
            'trendMethodBody',
            'managerDetail',
        ] as $key) {
            self::assertStringContainsString("'".$key."'", $roster);
        }

        $management = $catalogues[1];
        foreach (['manageSubtitle', 'markLeftConfirm', 'managerNotes', 'savePlayer'] as $key) {
            self::assertStringContainsString("'".$key."'", $management);
        }

        $workflows = $catalogues[2];
        foreach (['confirmAtomic', 'recordSnapshot', 'historyHelp', 'committedSummary'] as $key) {
            self::assertStringContainsString("'".$key."'", $workflows);
        }

        $overrides = $this->read('resources/js/localization/messages/roster-workflow-overrides.ts');
        foreach (['pl', 'ru', 'th', 'tr', 'vi'] as $locale) {
            self::assertMatchesRegularExpression('/(?:^|\s)'.preg_quote($locale, '/').':\s*\{/', $overrides);
        }
    }

    public function test_roster_controllers_only_add_authenticated_shell_identity(): void
    {
        $roster = $this->read('app/Domain/Kingdoms/Http/Controllers/RosterController.php');
        $intelligence = $this->read('app/Domain/Kingdoms/Http/Controllers/RosterIntelligenceController.php');
        $csv = $this->read('app/Domain/Kingdoms/Http/Controllers/RosterCsvController.php');
        $snapshots = $this->read('app/Domain/Kingdoms/Http/Controllers/PlayerSnapshotController.php');

        foreach ([$roster, $intelligence, $csv, $snapshots] as $source) {
            self::assertStringContainsString("'name' => (string) \$user->name", $source);
            self::assertStringContainsString("'email' => (string) \$user->email", $source);
            self::assertStringContainsString('PermissionKey::AllianceView', $source);
        }

        self::assertStringContainsString("Rule::in(['linked', 'unlinked'])", $roster);
        self::assertStringContainsString("Rule::in(['current', 'stale', 'missing'])", $roster);
        self::assertStringContainsString('PermissionKey::KingdomManage', $roster);
        self::assertStringContainsString("Rule::in(['member', 'management'])", $csv);
        self::assertStringContainsString("'power' => ['required', 'string', 'regex:/^\\d{1,19}$/']", $snapshots);
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
