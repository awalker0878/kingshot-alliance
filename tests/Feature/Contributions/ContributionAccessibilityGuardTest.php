<?php

declare(strict_types=1);

namespace Tests\Feature\Contributions;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ContributionAccessibilityGuardTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function phaseFivePages(): array
    {
        return [
            'member contribution progress' => ['resources/js/pages/Alliance/Contributions/Index.vue'],
            'contribution reporting management' => ['resources/js/pages/Alliance/Contributions/Manage.vue'],
        ];
    }

    #[DataProvider('phaseFivePages')]
    public function test_phase_five_pages_use_shared_shell_and_accessible_native_controls(string $path): void
    {
        $source = file_get_contents(base_path($path));
        self::assertIsString($source);

        self::assertStringContainsString('AppLayout', $source, "{$path} must use the shared application shell.");
        self::assertStringNotContainsString('<main', $source, "{$path} must not duplicate the shared main landmark.");
        self::assertStringContainsString('<h1', $source, "{$path} must retain a primary heading.");
        self::assertStringNotContainsString('v-html', $source, "{$path} must not render untrusted HTML.");
        self::assertDoesNotMatchRegularExpression(
            '/\btabindex\s*=\s*["\']\s*[1-9][0-9]*\s*["\']/i',
            $source,
            "{$path} must not introduce a positive tabindex.",
        );

        preg_match_all('/<button\b[^>]*>/is', $source, $buttons);
        foreach ($buttons[0] as $button) {
            self::assertStringContainsString('type=', $button, "{$path} buttons must declare their type.");
        }
    }

    public function test_contribution_member_view_preserves_only_real_member_workflows(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Contributions/Index.vue');

        foreach ([
            '/alliance/contributions/manage',
            '/alliance/contributions/self-report',
            'reporting.progress',
            'reporting.history',
            'reporting.leaderboards',
            'selfReportAllowed',
            'evidenceRequired',
            'calculationDescription',
            'calculationVersion',
        ] as $contract) {
            self::assertStringContainsString($contract, $source);
        }

        foreach ([
            'Contributions Trend',
            'Last Active',
            'Contributions by Kingdom',
            'Top Events',
            'Recent Snapshots',
            'AI insights',
            'Predicted trend',
        ] as $invented) {
            self::assertStringNotContainsString($invented, $source);
        }
    }

    public function test_contribution_manager_view_preserves_existing_mutation_and_reporting_routes(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Contributions/Manage.vue');

        foreach ([
            '/alliance/contributions/categories',
            '/alliance/contributions/records',
            '/alliance/contributions/records/${id}/approve',
            '/alliance/contributions/records/${r.id}/correct',
            '/alliance/contributions/records/${r.id}/reverse',
            '/alliance/contributions/reconcile-events',
            '/alliance/contributions/data-quality/refresh',
            '/alliance/contributions/data-quality/${id}/resolve',
            '/alliance/contributions/report-schedules',
            '/alliance/contributions/export.csv',
            '/alliance/contributions/export.xls',
        ] as $route) {
            self::assertStringContainsString($route, $source);
        }

        foreach ([
            'reporting.metrics.activeMembers',
            'reporting.metrics.attendanceRate',
            'reporting.metrics.recruitmentJoined',
            'reporting.metrics.pendingContributionApprovals',
            'reporting.metrics.openDataQualityFlags',
            'reporting.dataQualityFlags',
            'reporting.pendingRecords',
            'reporting.reportSchedules',
            'reporting.recentReportRuns',
            'reporting.categories',
            'reporting.leaderboards',
            'reporting.recentRecords',
        ] as $payload) {
            self::assertStringContainsString($payload, $source);
        }
    }

    public function test_contribution_controller_only_adds_authenticated_shell_identity(): void
    {
        $source = $this->read('app/Domain/Contributions/Http/Controllers/ContributionController.php');

        self::assertSame(2, substr_count($source, "'name' => (string) \$user->name"));
        self::assertSame(2, substr_count($source, "'email' => (string) \$user->email"));
        self::assertStringContainsString('PermissionKey::ContributionManage', $source);
        self::assertStringContainsString('memberDashboard($alliance, $membership)', $source);
        self::assertStringContainsString('managementDashboard($alliance)', $source);
    }

    public function test_contribution_copy_is_available_through_every_supported_catalogue(): void
    {
        $index = $this->read('resources/js/localization/messages/index.ts');
        $overrides = implode("\n", [
            $this->read('resources/js/localization/messages/contribution-extra.ts'),
            $this->read('resources/js/localization/messages/contribution-extra-2.ts'),
            $this->read('resources/js/localization/messages/contribution-extra-3.ts'),
            $this->read('resources/js/localization/messages/contribution-extra-4.ts'),
        ]);

        self::assertStringContainsString('const contributionCopy = {', $index);
        self::assertStringContainsString('contributionOverrides[locale]', $index);
        foreach (['title', 'selfReportTitle', 'managerTitle', 'approvalQueue', 'scheduledReport'] as $key) {
            self::assertStringContainsString($key.':', $index);
        }

        foreach ([
            'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW',
        ] as $locale) {
            self::assertStringContainsString($locale.':', $overrides, 'Missing contribution locale '.$locale);
        }
    }

    private function read(string $path): string
    {
        $source = file_get_contents(base_path($path));
        self::assertIsString($source);

        return $source;
    }
}
