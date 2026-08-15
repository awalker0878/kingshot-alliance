<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AccountExperienceTest extends TestCase
{
    public function test_account_catalogue_is_complete_for_every_supported_locale(): void
    {
        $root = dirname(__DIR__, 2);
        $english = file_get_contents($root.'/resources/js/localization/messages/account/en.ts');
        self::assertIsString($english);

        foreach (['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'] as $locale) {
            self::assertFileExists($root."/resources/js/localization/messages/account/{$locale}.ts");
        }

        self::assertStringContainsString('satisfies MessageCatalogue', $english);
        foreach (['accountExperience:'] as $required) {
            self::assertStringContainsString($required, $english, $required);
        }

        $registry = file_get_contents($root.'/resources/js/localization/registry.ts');
        self::assertIsString($registry);
        self::assertStringContainsString("'account'", $registry);
    }

    public function test_profile_uses_the_shared_shell_and_preserves_security_actions(): void
    {
        $page = $this->read('resources/js/pages/Profile.vue');

        self::assertStringContainsString("import AppLayout from '../layouts/AppLayout.vue';", $page);
        self::assertStringContainsString("import { useLocale } from '../localization';", $page);
        self::assertStringContainsString('<AppLayout', $page);
        self::assertStringNotContainsString('<main', $page);
        self::assertStringContainsString("profileForm.patch('/profile')", $page);
        self::assertStringContainsString("passwordForm.put('/profile/password'", $page);
        self::assertStringContainsString("sessionsForm.delete('/profile/sessions/other'", $page);
        self::assertStringContainsString("router.post('/profile/two-factor')", $page);
        self::assertStringContainsString("twoFactorForm.post('/profile/two-factor/confirm'", $page);
        self::assertStringContainsString("router.post('/profile/two-factor/recovery-codes')", $page);
        self::assertStringContainsString("router.delete('/profile/two-factor')", $page);
        self::assertStringContainsString("case 'password-updated':", $page);
        self::assertStringContainsString("case 'other-sessions-revoked':", $page);
        self::assertStringContainsString("case 'two-factor-disabled':", $page);
        self::assertStringNotContainsString('v-html', $page);
    }

    public function test_account_deletion_experience_is_preserved_while_cross_context_orchestration_is_deferred(): void
    {
        $page = $this->read('resources/js/pages/AccountDeletion.vue');
        $routes = $this->read('routes/account.php');

        self::assertStringContainsString("import AppLayout from '../layouts/AppLayout.vue';", $page);
        self::assertStringContainsString('formatDate(props.request.eligibleAt)', $page);
        self::assertStringContainsString('formatDate(props.request.requestedAt)', $page);
        self::assertStringContainsString("router.post('/profile/delete-account')", $page);
        self::assertStringContainsString("window.confirm(t('accountExperience.deletion.confirm'))", $page);
        self::assertStringContainsString("props.status === 'account-deletion-requested'", $page);
        self::assertStringNotContainsString('<main', $page);
        self::assertStringNotContainsString('v-html', $page);

        self::assertStringContainsString('Cross-context account deletion is rebuilt under Workflows in ARCH-V2-P8.', $routes);
        self::assertStringNotContainsString('App\\Domain\\Identity', $routes);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root().'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
