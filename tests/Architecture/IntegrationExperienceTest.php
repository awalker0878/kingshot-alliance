<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class IntegrationExperienceTest extends TestCase
{
    public function test_integration_management_uses_shared_shell_and_existing_routes(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Integrations/Manage.vue');

        self::assertStringContainsString('AppLayout', $source);
        self::assertStringContainsString(':has-active-alliance="true"', $source);
        self::assertStringNotContainsString('<main', $source);
        self::assertStringContainsString('<h1', $source);
        self::assertStringNotContainsString('role="button"', $source);

        foreach ([
            '/alliance/integrations/api-credentials',
            '/alliance/integrations/api-credentials/${credential.id}',
            '/alliance/integrations/webhooks',
            '/alliance/integrations/webhooks/${webhook.id}',
        ] as $route) {
            self::assertStringContainsString($route, $source);
        }
    }

    public function test_integration_visuals_only_present_real_platform_state(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Integrations/Manage.vue');

        foreach ([
            'Discord Bot',
            'Integration marketplace',
            'Connect Discord',
            'Reconnect',
            'Retry delivery',
            'Retry now',
            'Delivery success rate',
            'Uptime',
            'Health score',
            'AI integration',
        ] as $invented) {
            self::assertStringNotContainsString($invented, $source);
        }

        foreach ([
            'settings.apiAccessEnabled',
            'settings.webhooksEnabled',
            'limits.apiCredentials',
            'limits.webhookSubscriptions',
            'allowedScopes',
            'credentials',
            'webhooks',
            'recentDeliveries',
            'issuedCredential',
            'issuedWebhookSecret',
        ] as $real) {
            self::assertStringContainsString($real, $source);
        }
    }

    public function test_integration_catalogue_covers_all_supported_locales(): void
    {
        $source = $this->read('resources/js/localization/messages/integration-experience.ts');

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
            self::assertStringContainsString("'".$locale."'", $source);
        }

        foreach ([
            'title',
            'apiCredentials',
            'webhookSubscriptions',
            'deliveryLog',
            'saveCredentialNow',
            'saveWebhookNow',
        ] as $key) {
            self::assertStringContainsString($key.':', $source);
        }
    }

    public function test_integration_controller_only_adds_shell_identity_to_existing_contract(): void
    {
        $source = $this->read('app/Domain/Integrations/Http/Controllers/IntegrationManagementController.php');

        self::assertStringContainsString("'user' => [", $source);
        self::assertStringContainsString("'name' => (string)", $source);
        self::assertStringContainsString("'email' => (string)", $source);
        self::assertStringContainsString('PermissionKey::AllianceManage', $source);
        self::assertStringContainsString('CreateApiCredential::allowedScopes()', $source);
        self::assertStringContainsString('PlanEntitlementService', $source);
        self::assertStringContainsString('recentDeliveries', $source);
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
