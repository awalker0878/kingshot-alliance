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
            self::assertStringContainsString(':player-alliance-name="alliance.name"', $source);
            self::assertStringContainsString(':has-player-alliance="true"', $source);
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
        $root = dirname(__DIR__, 2);
        $english = file_get_contents($root.'/resources/js/localization/messages/roster/en.ts');
        self::assertIsString($english);

        foreach (['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'] as $locale) {
            self::assertFileExists($root."/resources/js/localization/messages/roster/{$locale}.ts");
        }

        self::assertStringContainsString('satisfies MessageCatalogue', $english);
        foreach (['title:', 'freshnessHelp:', 'intelligenceTitle:', 'snapshotQuality:', 'sevenDayChange:', 'trendMethodBody:', 'managerDetail:', 'manageSubtitle:', 'markLeftConfirm:', 'managerNotes:', 'savePlayer:', 'confirmAtomic:', 'recordSnapshot:', 'historyHelp:', 'committedSummary:'] as $required) {
            self::assertStringContainsString($required, $english, $required);
        }

        $registry = file_get_contents($root.'/resources/js/localization/registry.ts');
        self::assertIsString($registry);
        self::assertStringContainsString("'roster'", $registry);
    }

    public function test_roster_controllers_only_add_authenticated_shell_identity(): void
    {
        $root = 'app/Contexts/Intelligence/Roster/Http/Controllers/';
        $roster = $this->read($root.'RosterController.php');
        $intelligence = $this->read($root.'RosterIntelligenceController.php');
        $csv = $this->read($root.'RosterCsvController.php');
        $snapshots = $this->read($root.'PlayerSnapshotController.php');

        foreach ([$roster, $intelligence, $csv, $snapshots] as $source) {
            self::assertStringContainsString("'name' => (string) \$user->name", $source);
            self::assertStringContainsString("'email' => (string) \$user->email", $source);
            self::assertStringContainsString('IntelligencePermission::', $source);
        }

        self::assertStringContainsString('IntelligencePermission::View', $roster);
        self::assertStringContainsString('IntelligencePermission::View', $intelligence);
        self::assertStringContainsString('IntelligencePermission::View', $snapshots);
        self::assertStringContainsString("Rule::in(['linked', 'unlinked'])", $roster);
        self::assertStringContainsString("Rule::in(['current', 'stale', 'missing'])", $roster);
        self::assertStringContainsString('IntelligencePermission::KingdomManage', $roster);
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
