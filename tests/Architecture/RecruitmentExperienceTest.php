<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RecruitmentExperienceTest extends TestCase
{
    public function test_private_recruitment_pages_use_shared_shell_and_existing_routes(): void
    {
        $manage = $this->read('resources/js/pages/Alliance/Recruitment/Manage.vue');
        $candidate = $this->read('resources/js/pages/Alliance/Recruitment/Candidate.vue');

        foreach ([$manage, $candidate] as $source) {
            self::assertStringContainsString('AppLayout', $source);
            self::assertStringContainsString(':has-active-alliance="true"', $source);
            self::assertStringNotContainsString('<main', $source);
            self::assertStringContainsString('<h1', $source);
            self::assertStringNotContainsString('role="button"', $source);
        }

        foreach ([
            '/alliance/recruitment/settings',
            '/alliance/recruitment/questions',
            '/alliance/recruitment/application-invites',
            '/alliance/recruitment/decision-templates',
            '/alliance/recruitment/onboarding-items',
            '/alliance/recruitment/${candidate.id}',
        ] as $route) {
            self::assertStringContainsString($route, $manage.$candidate);
        }

        foreach ([
            '/stage',
            '/reviewers/',
            '/notes',
            '/tags',
            '/merge/',
            '/communications/',
            '/convert',
            '/alliance/recruitment/onboarding/',
        ] as $contract) {
            self::assertStringContainsString($contract, $candidate);
        }
    }

    public function test_recruitment_visuals_do_not_introduce_mockup_only_features(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Recruitment/Manage.vue')
            .$this->read('resources/js/pages/Alliance/Recruitment/Candidate.vue');

        foreach ([
            'Campaign Performance',
            'Active Campaigns',
            'Applications Trend',
            'Top Sources (7 Days)',
            'automated scoring',
            'AI score',
            'AI recommendation',
            'Discord Bot',
            'QR Code',
        ] as $invented) {
            self::assertStringNotContainsString($invented, $source);
        }
    }

    public function test_recruitment_catalogue_covers_all_supported_locales(): void
    {
        $root = dirname(__DIR__, 2);
        $english = file_get_contents($root.'/resources/js/localization/messages/recruitment/en.ts');
        self::assertIsString($english);

        foreach (['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'] as $locale) {
            self::assertFileExists($root."/resources/js/localization/messages/recruitment/{$locale}.ts");
        }

        self::assertStringContainsString('satisfies MessageCatalogue', $english);
        foreach (['title:', 'pipeline:', 'settings:', 'questions:', 'candidateRecord:', 'privateNotes:', 'stageHistory:'] as $required) {
            self::assertStringContainsString($required, $english, $required);
        }

        $registry = file_get_contents($root.'/resources/js/localization/registry.ts');
        self::assertIsString($registry);
        self::assertStringContainsString("'recruitment'", $registry);
    }

    public function test_recruitment_controllers_only_add_authenticated_shell_identity(): void
    {
        foreach ([
            'app/Domain/Recruitment/Http/Controllers/RecruitmentManagementController.php',
            'app/Domain/Recruitment/Http/Controllers/RecruitmentCandidateController.php',
        ] as $path) {
            $source = $this->read($path);
            self::assertStringContainsString("'name' => (string) \$user->name", $source);
            self::assertStringContainsString("'email' => (string) \$user->email", $source);
            self::assertStringContainsString('AlliancePermission::RecruitmentManage', $source);
        }
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
