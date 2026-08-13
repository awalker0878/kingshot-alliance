<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomTransferExperienceTest extends TestCase
{
    public function test_transfer_pages_use_shared_shell_and_localization(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';

        foreach ([
            'TransferPlans.vue',
            'TransferPlansManage.vue',
            'TransferReadinessManage.vue',
            'TransferCompletionManage.vue',
        ] as $page) {
            $source = file_get_contents($root.$page);
            self::assertIsString($source);
            self::assertStringContainsString('AppLayout', $source, $page);
            self::assertStringContainsString('useLocale', $source, $page);
            self::assertStringContainsString('kingdomP7D.', $source, $page);
            self::assertStringNotContainsString('<main', $source, $page);
        }
    }

    public function test_transfer_routes_remain_the_existing_explicit_contract(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/kingdoms.php');
        self::assertIsString($routes);

        foreach ([
            '/alliance/transfers',
            '/alliance/transfers/manage',
            '/alliance/transfers/readiness',
            '/alliance/transfers/completion',
            '/alliance/transfers/{plan}/open',
            '/alliance/transfers/{plan}/lock',
            '/alliance/transfers/{plan}/close',
            '/alliance/transfers/{plan}/cancel',
            '/alliance/transfers/{plan}/groups',
            '/alliance/transfers/{plan}/participants',
            '/alliance/transfers/{plan}/participants/{participant}/group',
            '/alliance/transfers/{plan}/participants/{participant}/readiness',
            '/alliance/transfers/{plan}/participants/{participant}/blockers',
            '/alliance/transfers/{plan}/participants/{participant}/withdraw',
            '/alliance/transfers/{plan}/participants/{participant}/complete',
        ] as $contract) {
            self::assertStringContainsString($contract, $routes);
        }
    }

    public function test_transfer_controllers_supply_only_shell_identity_for_the_app_layout(): void
    {
        $root = dirname(__DIR__, 2).'/app/Domain/Kingdoms/Http/Controllers/';
        $plan = file_get_contents($root.'TransferPlanController.php');
        self::assertIsString($plan);
        self::assertGreaterThanOrEqual(2, substr_count($plan, "'user' => ["));

        foreach (['TransferReadinessController.php', 'TransferCompletionController.php'] as $controller) {
            $source = file_get_contents($root.$controller);
            self::assertIsString($source);
            self::assertStringContainsString("'user' => [", $source, $controller);
            self::assertStringContainsString("'name' => (string) \$user->name", $source, $controller);
            self::assertStringContainsString("'email' => (string) \$user->email", $source, $controller);
        }
    }

    public function test_transfer_pages_keep_the_existing_mutation_paths(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';
        $manage = file_get_contents($root.'TransferPlansManage.vue');
        $readiness = file_get_contents($root.'TransferReadinessManage.vue');
        $completion = file_get_contents($root.'TransferCompletionManage.vue');
        self::assertIsString($manage);
        self::assertIsString($readiness);
        self::assertIsString($completion);

        foreach ([
            "createForm.post('/alliance/transfers'",
            '/alliance/transfers/${plan.id}/${action}',
            '/alliance/transfers/${props.mutablePlan.id}/groups',
            '/alliance/transfers/${props.mutablePlan.id}/participants',
            '/alliance/transfers/${props.mutablePlan.id}/participants/${participant.id}/group',
            '/alliance/transfers/${props.mutablePlan.id}/participants/${participant.id}/withdraw',
        ] as $contract) {
            self::assertStringContainsString($contract, $manage);
        }

        foreach ([
            '/alliance/transfers/${props.plan.id}/participants/${participant.id}/readiness',
            '/alliance/transfers/${props.plan.id}/participants/${participant.id}/blockers',
            '/alliance/transfers/${props.plan.id}/participants/${participant.id}/withdraw',
        ] as $contract) {
            self::assertStringContainsString($contract, $readiness);
        }

        self::assertStringContainsString(
            '/alliance/transfers/${props.plan.id}/participants/${participant.id}/complete',
            $completion,
        );
        self::assertStringContainsString("participant.readiness === 'confirmed'", $completion);
        self::assertStringContainsString('props.plan.completable', $completion);
    }

    public function test_transfer_visual_target_does_not_create_mockup_only_product_contracts(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';
        $source = '';
        foreach ([
            'TransferPlans.vue',
            'TransferPlansManage.vue',
            'TransferReadinessManage.vue',
            'TransferCompletionManage.vue',
        ] as $page) {
            $pageSource = file_get_contents($root.$page);
            self::assertIsString($pageSource);
            $source .= $pageSource;
        }

        foreach ([
            'transferHealth',
            'policyCompliance',
            'destinationRanking',
            'eligibilityScore',
            'ticketCount',
            'resourceScore',
            'automaticTransfer',
            'bulkComplete',
            'activityFeed',
            'exportTransfer',
            'aiRecommendation',
        ] as $inventedCapability) {
            self::assertStringNotContainsString($inventedCapability, $source);
        }
    }

    public function test_transfer_catalogue_covers_all_supported_locales(): void
    {
        $root = dirname(__DIR__, 2);
        $english = file_get_contents($root.'/resources/js/localization/messages/transfers/en.ts');
        self::assertIsString($english);

        foreach (['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'] as $locale) {
            self::assertFileExists($root."/resources/js/localization/messages/transfers/{$locale}.ts");
        }

        self::assertStringContainsString('satisfies MessageCatalogue', $english);
        foreach (['kingdomP7D:'] as $required) {
            self::assertStringContainsString($required, $english, $required);
        }

        $registry = file_get_contents($root.'/resources/js/localization/registry.ts');
        self::assertIsString($registry);
        self::assertStringContainsString("'transfers'", $registry);
    }
}
