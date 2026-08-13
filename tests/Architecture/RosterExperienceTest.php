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

        foreach ([$roster, $intelligence] as $source) {
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
            self::assertStringContainsString($route, $roster.$intelligence);
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

    public function test_roster_catalogue_covers_all_supported_locales(): void
    {
        $source = $this->read('resources/js/localization/messages/roster.ts');

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
            $present = str_contains($source, $locale.': locale([')
                || str_contains($source, "'".$locale."': locale([")
                || str_contains($source, '"'.$locale.'": locale([');

            self::assertTrue($present, 'Missing roster locale '.$locale);
        }

        foreach ([
            'title',
            'freshnessHelp',
            'intelligenceTitle',
            'snapshotQuality',
            'sevenDayChange',
            'trendMethodBody',
            'managerDetail',
        ] as $key) {
            self::assertStringContainsString("'".$key."'", $source);
        }
    }

    public function test_roster_controllers_only_add_authenticated_shell_identity(): void
    {
        $roster = $this->read('app/Domain/Kingdoms/Http/Controllers/RosterController.php');
        $intelligence = $this->read('app/Domain/Kingdoms/Http/Controllers/RosterIntelligenceController.php');

        foreach ([$roster, $intelligence] as $source) {
            self::assertStringContainsString("'name' => (string) \$user->name", $source);
            self::assertStringContainsString("'email' => (string) \$user->email", $source);
            self::assertStringContainsString('PermissionKey::AllianceView', $source);
        }

        self::assertStringContainsString("Rule::in(['linked', 'unlinked'])", $roster);
        self::assertStringContainsString("Rule::in(['current', 'stale', 'missing'])", $roster);
        self::assertStringContainsString('PermissionKey::KingdomManage', $roster);
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
