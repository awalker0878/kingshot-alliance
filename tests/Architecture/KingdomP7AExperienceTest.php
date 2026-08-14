<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomP7AExperienceTest extends TestCase
{
    public function test_p7a_surfaces_use_shared_shell_and_real_kingdom_contracts(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['KingdomAlliances.vue', 'KingdomSettings.vue', 'KingdomRoles.vue', 'KingdomIngestionManage.vue'] as $page) {
            $source = file_get_contents($root.'/resources/js/pages/Alliance/'.$page);
            self::assertIsString($source);
            self::assertStringContainsString('AppLayout', $source);
            self::assertStringContainsString('kingdomP7A.', $source);
            self::assertStringNotContainsString('<main', $source);
        }

        $overview = file_get_contents($root.'/resources/js/pages/Alliance/KingdomAlliances.vue');
        self::assertIsString($overview);
        self::assertStringContainsString('/alliance/kingdom-alliances/manage', $overview);
        self::assertStringContainsString('/alliance/kingdom-alliances/intelligence', $overview);
        self::assertStringContainsString('latestObservation', $overview);
        self::assertStringContainsString('diplomacyNeedsReview', $overview);

        $settings = file_get_contents($root.'/resources/js/pages/Alliance/KingdomSettings.vue');
        self::assertIsString($settings);
        self::assertStringContainsString("form.patch('/alliance/settings/kingdom'", $settings);

        $roles = file_get_contents($root.'/resources/js/pages/Alliance/KingdomRoles.vue');
        self::assertIsString($roles);
        self::assertStringContainsString("form.post('/alliance/settings/kingdom/roles'", $roles);
        self::assertStringContainsString('router.delete(`/alliance/settings/kingdom/roles/', $roles);

        $ingestion = file_get_contents($root.'/resources/js/pages/Alliance/KingdomIngestionManage.vue');
        self::assertIsString($ingestion);
        foreach (['/alliance/kingdom-ingestion/subscriptions', '/state', '/replay', '/reject'] as $contract) {
            self::assertStringContainsString($contract, $ingestion);
        }
    }

    public function test_p7a_rejects_visual_target_only_analytics_and_controls(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';
        $source = file_get_contents($root.'KingdomAlliances.vue').file_get_contents($root.'KingdomSettings.vue').file_get_contents($root.'KingdomIngestionManage.vue');
        self::assertIsString($source);
        foreach (['Kingdom Power Trend', 'Recent Kingdom Activity', 'Export Kingdom Data', 'AI-powered', 'Predicted power', 'Cross-kingdom ranking', 'Auto-snapshot'] as $unsupported) {
            self::assertStringNotContainsString($unsupported, $source);
        }
    }

    public function test_p7a_catalogue_covers_all_supported_locales_and_controllers_supply_shell_identity(): void
    {
        $root = dirname(__DIR__, 2);
        $english = file_get_contents($root.'/resources/js/localization/messages/kingdom/en.ts');
        self::assertIsString($english);

        foreach (['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'] as $locale) {
            self::assertFileExists($root."/resources/js/localization/messages/kingdom/{$locale}.ts");
        }

        self::assertStringContainsString('satisfies MessageCatalogue', $english);
        foreach (['kingdomP7A:'] as $required) {
            self::assertStringContainsString($required, $english, $required);
        }

        $registry = file_get_contents($root.'/resources/js/localization/registry.ts');
        self::assertIsString($registry);
        self::assertStringContainsString("'kingdom'", $registry);

        foreach (['KingdomAllianceController.php', 'KingdomSettingsController.php', 'KingdomIngestionController.php'] as $controller) {
            $source = file_get_contents($root.'/app/Domain/Kingdoms/Http/Controllers/'.$controller);
            self::assertIsString($source);
            self::assertStringContainsString("'name' => (string) \$user->name", $source);
            self::assertStringContainsString("'email' => (string) \$user->email", $source);
        }

        $roleController = file_get_contents($root.'/app/Domain/Authorization/Http/Controllers/KingdomRoleController.php');
        self::assertIsString($roleController);
        self::assertStringContainsString("Inertia::render('Alliance/KingdomRoles'", $roleController);
        self::assertStringContainsString("'name' => (string) \$user->name", $roleController);
        self::assertStringContainsString("'email' => (string) \$user->email", $roleController);
    }
}
