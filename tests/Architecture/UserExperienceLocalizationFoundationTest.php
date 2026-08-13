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

    public function test_major_kingshot_locale_catalogue_is_declared(): void
    {
        $locales = $this->read('resources/js/localization/locales.ts');

        foreach ([
            "'en'",
            "'ar'",
            "'de'",
            "'es'",
            "'fr'",
            "'id'",
            "'it'",
            "'ja'",
            "'ko'",
            "'pl'",
            "'pt-BR'",
            "'ru'",
            "'th'",
            "'tr'",
            "'vi'",
            "'zh-CN'",
            "'zh-TW'",
        ] as $locale) {
            self::assertStringContainsString($locale, $locales, $locale);
        }

        self::assertStringContainsString("code: 'ar'", $locales);
        self::assertStringContainsString("direction: 'rtl'", $locales);
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

    public function test_initial_translation_catalogue_only_names_current_auth_capabilities(): void
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
