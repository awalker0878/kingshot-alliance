<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AuthExperienceTest extends TestCase
{
    /** @return list<string> */
    private function authPages(): array
    {
        return [
            'resources/js/pages/Auth/Login.vue',
            'resources/js/pages/Auth/Register.vue',
            'resources/js/pages/Auth/Invitation.vue',
            'resources/js/pages/Auth/ForgotPassword.vue',
            'resources/js/pages/Auth/ResetPassword.vue',
            'resources/js/pages/Auth/VerifyEmail.vue',
            'resources/js/pages/Auth/ConfirmPassword.vue',
            'resources/js/pages/Auth/TwoFactorChallenge.vue',
        ];
    }

    public function test_auth_experience_catalogue_is_complete_for_every_supported_locale(): void
    {
        $messages = $this->read('resources/js/localization/messages/auth/en.ts');
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
                self::assertMatchesRegularExpression(
                    '/(?:^|\s)en,\s*$/m',
                    $messages,
                    $locale,
                );

                continue;
            }

            self::assertMatchesRegularExpression(
                '/(?:^|\s)[\'\"]?'.preg_quote($locale, '/').'[\'\"]?\s*:/m',
                $messages,
                $locale,
            );
        }

        self::assertStringContainsString('Record<LocaleCode, AuthExtraTree>', $messages);
        self::assertStringContainsString("import { authExtraMessages } from './auth-extra';", $index);
        self::assertStringContainsString('...authExtraMessages[locale]', $index);
    }

    public function test_auth_layout_owns_the_localized_responsive_main_landmark(): void
    {
        $layout = $this->read('resources/js/layouts/AuthLayout.vue');

        self::assertStringContainsString("import LocaleSwitcher from '../components/navigation/LocaleSwitcher.vue';", $layout);
        self::assertStringContainsString("import { useLocale } from '../localization';", $layout);
        self::assertStringContainsString('<main', $layout);
        self::assertStringContainsString("t('authExperience.shell.headline')", $layout);
        self::assertStringContainsString("t('authExperience.shell.intro')", $layout);
        self::assertStringContainsString('lg:grid-cols-', $layout);
    }

    public function test_auth_pages_use_shared_shell_without_unsupported_authentication_capabilities(): void
    {
        foreach ($this->authPages() as $path) {
            $page = $this->read($path);

            self::assertStringContainsString("import AuthLayout from '../../layouts/AuthLayout.vue';", $page, $path);
            self::assertStringContainsString("import { useLocale } from '../../localization';", $page, $path);
            self::assertStringContainsString('<AuthLayout>', $page, $path);
            self::assertStringNotContainsString('<main', $page, $path);
            self::assertStringNotContainsString('v-html', $page, $path);
            self::assertDoesNotMatchRegularExpression(
                '/\btabindex\s*=\s*["\']\s*[1-9][0-9]*\s*["\']/i',
                $page,
                $path,
            );

            foreach (['Google', 'Discord', 'Apple', 'Microsoft', 'Magic link'] as $unsupported) {
                self::assertStringNotContainsString($unsupported, $page, $path.' '.$unsupported);
            }

            preg_match_all('/<button\b[^>]*>/is', $page, $buttons);
            foreach ($buttons[0] as $button) {
                self::assertStringContainsString('type=', $button, $path.' buttons must declare their type.');
            }
        }
    }

    public function test_login_and_registration_keep_existing_behavior_contracts(): void
    {
        $login = $this->read('resources/js/pages/Auth/Login.vue');
        $register = $this->read('resources/js/pages/Auth/Register.vue');

        self::assertStringContainsString("form.post('/login'", $login);
        self::assertStringContainsString('remember: false', $login);
        self::assertStringContainsString('invitation_token: props.invitationToken', $login);
        self::assertStringContainsString('href="/forgot-password"', $login);
        self::assertStringContainsString('`/register?invitation=${encodeURIComponent(props.invitationToken)}`', $login);

        self::assertStringContainsString("form.post('/register'", $register);
        self::assertStringContainsString("props.registrationMode === 'open' || props.invitationToken", $register);
        self::assertStringContainsString("v-else-if=\"props.registrationMode !== 'open'\"", $register);
        self::assertStringContainsString('email: props.invitedEmail', $register);
        self::assertStringContainsString('timezone: Intl.DateTimeFormat()', $register);
        self::assertStringContainsString('invitation_token: props.invitationToken', $register);
        self::assertStringContainsString('minlength="12"', $register);
    }

    public function test_invitation_keeps_existing_acceptance_and_identity_contract(): void
    {
        $page = $this->read('resources/js/pages/Auth/Invitation.vue');

        self::assertStringContainsString('router.post(`/invitations/${props.invitation.token}/accept`)', $page);
        self::assertStringContainsString('authenticatedEmail?.toLowerCase()', $page);
        self::assertStringContainsString('invitation.email.toLowerCase()', $page);
        self::assertStringContainsString('`/register?invitation=${encodeURIComponent(invitation.token)}`', $page);
        self::assertStringContainsString('`/login?invitation=${encodeURIComponent(invitation.token)}`', $page);
        self::assertStringContainsString('formatDate(props.invitation.expiresAt', $page);
    }

    public function test_password_verification_and_two_factor_actions_remain_unchanged(): void
    {
        $forgot = $this->read('resources/js/pages/Auth/ForgotPassword.vue');
        $reset = $this->read('resources/js/pages/Auth/ResetPassword.vue');
        $verify = $this->read('resources/js/pages/Auth/VerifyEmail.vue');
        $confirm = $this->read('resources/js/pages/Auth/ConfirmPassword.vue');
        $twoFactor = $this->read('resources/js/pages/Auth/TwoFactorChallenge.vue');

        self::assertStringContainsString("form.post('/forgot-password')", $forgot);
        self::assertStringContainsString("form.post('/reset-password'", $reset);
        self::assertStringContainsString('token: props.token', $reset);
        self::assertStringContainsString('email: props.email', $reset);
        self::assertStringContainsString("router.post('/email/verification-notification')", $verify);
        self::assertStringContainsString("form.post('/confirm-password'", $confirm);
        self::assertStringContainsString("form.post('/two-factor-challenge'", $twoFactor);
        self::assertStringContainsString("form.recovery_code = ''", $twoFactor);
        self::assertStringContainsString("form.code = ''", $twoFactor);
        self::assertStringContainsString('pattern="\\d{6}"', $twoFactor);
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
