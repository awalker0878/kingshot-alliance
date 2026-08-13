<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AccountExperienceTest extends TestCase
{
    public function test_account_catalogue_is_complete_for_every_supported_locale(): void
    {
        $messages = $this->read('resources/js/localization/messages/account/en.ts');
        $index = $this->read('resources/js/localization/messages/index.ts');

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
            if ($locale === 'en') {
                self::assertMatchesRegularExpression('/(?:^|\s)en,\s*$/m', $messages, $locale);

                continue;
            }

            self::assertMatchesRegularExpression(
                '/(?:^|\s)[\'\"]?'.preg_quote($locale, '/').'[\'\"]?\s*:/m',
                $messages,
                $locale,
            );
        }

        self::assertStringContainsString('Record<LocaleCode, AccountExperienceTree>', $messages);
        self::assertStringContainsString("import { accountExperienceMessages } from './account-extra';", $index);
        self::assertStringContainsString('...accountExperienceMessages[locale]', $index);
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

    public function test_account_deletion_uses_existing_request_contract_and_localized_dates(): void
    {
        $page = $this->read('resources/js/pages/AccountDeletion.vue');
        $controller = $this->read('app/Domain/Identity/Http/Controllers/AccountDeletionController.php');

        self::assertStringContainsString("import AppLayout from '../layouts/AppLayout.vue';", $page);
        self::assertStringContainsString('formatDate(props.request.eligibleAt)', $page);
        self::assertStringContainsString('formatDate(props.request.requestedAt)', $page);
        self::assertStringContainsString("router.post('/profile/delete-account')", $page);
        self::assertStringContainsString("window.confirm(t('accountExperience.deletion.confirm'))", $page);
        self::assertStringContainsString("props.status === 'account-deletion-requested'", $page);
        self::assertStringNotContainsString('<main', $page);
        self::assertStringNotContainsString('v-html', $page);

        self::assertStringContainsString("'name' => \$user->name", $controller);
        self::assertStringContainsString("'email' => \$user->email", $controller);
        self::assertStringContainsString("with('status', 'account-deletion-requested')", $controller);
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
