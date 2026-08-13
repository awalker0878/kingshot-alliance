<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class UserExperienceLocalizationFoundationTest extends TestCase
{
    public function test_ux_program_preserves_existing_capability_boundary(): void
    {
        $plan = $this->read('docs/product/ux-001-experience-localization.md');

        self::assertStringContainsString('Existing functionality only', $plan);
        self::assertStringContainsString('generated mockup is a visual target, not a source of new business requirements', $plan);
        self::assertStringContainsString('social login providers', $plan);
        self::assertStringContainsString('magic-link authentication', $plan);
        self::assertStringContainsString('Kingshot-first information architecture', $plan);
    }

    public function test_major_kingshot_locale_catalogue_is_declared_and_complete(): void
    {
        $locales = $this->read('resources/js/localization/locales.ts');
        $catalogues = $this->read('resources/js/localization/messages/catalogues.ts');
        $messageIndex = $this->read('resources/js/localization/messages/index.ts');

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
            self::assertStringContainsString("'".$locale."'", $locales, $locale);

            if ($locale !== 'en') {
                self::assertStringContainsString('"'.$locale.'":', $catalogues, $locale);
            }
        }

        self::assertStringContainsString("code: 'ar'", $locales);
        self::assertStringContainsString("direction: 'rtl'", $locales);
        self::assertStringContainsString('Record<LocaleCode, MessageTree>', $messageIndex);
        self::assertStringContainsString('...additionalCatalogues', $messageIndex);
    }

    public function test_shared_visual_tokens_and_locale_bootstrap_exist(): void
    {
        $css = $this->read('resources/css/app.css');
        $app = $this->read('resources/js/app.ts');

        foreach ([
            '--ks-bg',
            '--ks-surface-1',
            '--ks-gold',
            '--ks-blue',
            '--ks-font-display',
            "html[dir='rtl']",
            'prefers-reduced-motion',
        ] as $token) {
            self::assertStringContainsString($token, $css, $token);
        }

        self::assertStringContainsString("import { initializeLocale } from './localization';", $app);
        self::assertStringContainsString('initializeLocale();', $app);
    }

    public function test_translation_catalogue_only_names_current_auth_capabilities(): void
    {
        $messages = $this->read('resources/js/localization/messages/en.ts');

        foreach ([
            'Sign in',
            'Create account',
            'Forgot password?',
            'Alliance invitation',
            'Two-factor authentication',
        ] as $label) {
            self::assertStringContainsString($label, $messages, $label);
        }

        foreach ([
            'Google',
            'Discord',
            'Apple',
            'Microsoft',
            'Magic link',
        ] as $unsupported) {
            self::assertStringNotContainsString($unsupported, $messages, $unsupported);
        }
    }

    public function test_app_shell_links_only_to_existing_authenticated_capabilities(): void
    {
        $layout = $this->read('resources/js/layouts/AppLayout.vue');

        foreach ([
            '/dashboard',
            '/alliance',
            '/alliance/events',
            '/alliance/roster',
            '/alliance/recruitment',
            '/alliance/content',
            '/alliance/contributions',
            '/alliance/kingdom-alliances',
            '/alliance/transfers',
            '/alliance/integrations',
            '/profile',
            '/logout',
        ] as $path) {
            self::assertStringContainsString($path, $layout, $path);
        }

        self::assertStringContainsString('allianceScoped: true', $layout);
        self::assertStringContainsString('!props.hasActiveAlliance', $layout);
        self::assertStringContainsString('start-0', $layout);
        self::assertStringContainsString('border-e', $layout);

        foreach ([
            '/notifications',
            '/messages',
            '/help',
            '/social',
        ] as $unsupportedPath) {
            self::assertStringNotContainsString($unsupportedPath, $layout, $unsupportedPath);
        }
    }

    public function test_dashboard_uses_shared_authenticated_shell_without_changing_domain_actions(): void
    {
        $dashboard = $this->read('resources/js/pages/Dashboard.vue');

        self::assertStringContainsString("import AppLayout from '../layouts/AppLayout.vue';", $dashboard);
        self::assertStringContainsString("allianceForm.post('/alliances'", $dashboard);
        self::assertStringContainsString('router.put(`/alliances/${allianceId}/active`)', $dashboard);
        self::assertStringContainsString('href="/alliance/roster"', $dashboard);
        self::assertStringContainsString('href="/alliance/kingdom-alliances"', $dashboard);
        self::assertStringContainsString('href="/alliance/transfers"', $dashboard);
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
