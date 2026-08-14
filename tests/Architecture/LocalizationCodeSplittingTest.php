<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class LocalizationCodeSplittingTest extends TestCase
{
    public function test_lazy_page_and_catalogue_loading_contract(): void
    {
        $root = dirname(__DIR__, 2);
        $app = file_get_contents($root.'/resources/js/app.ts');
        $registry = file_get_contents($root.'/resources/js/localization/registry.ts');
        $loader = file_get_contents($root.'/resources/js/localization/loader.ts');

        self::assertIsString($app);
        self::assertIsString($registry);
        self::assertIsString($loader);
        self::assertStringContainsString("import.meta.glob<DefineComponent>('./pages/**/*.vue'", $app);
        self::assertStringNotContainsString('eager: true', $app);
        self::assertStringContainsString('await ensurePageDomains(name)', $app);
        self::assertStringContainsString("import.meta.glob<MessageModule>('./messages/*/*.ts')", $registry);
        self::assertStringContainsString('const catalogues = new Map', $loader);
        self::assertStringContainsString('const pending = new Map', $loader);

        $package = file_get_contents($root.'/package.json');
        self::assertIsString($package);
        self::assertFileExists($root.'/scripts/check-event-localization-coverage.mjs');
        self::assertStringContainsString('check:event-localization-coverage', $package);
    }

    public function test_all_domains_have_all_supported_locale_modules(): void
    {
        $root = dirname(__DIR__, 2);
        $domains = ['core', 'auth', 'account', 'alliance', 'events', 'roster', 'contributions', 'recruitment', 'content', 'integrations', 'kingdom', 'transfers', 'platform', 'public'];
        $locales = ['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'];

        foreach ($domains as $domain) {
            foreach ($locales as $locale) {
                self::assertFileExists($root."/resources/js/localization/messages/{$domain}/{$locale}.ts");
            }
        }
    }

    public function test_event_shell_has_localized_operational_keys_in_every_supported_locale(): void
    {
        $root = dirname(__DIR__, 2);
        $locales = ['ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'];
        $requiredSections = [
            '"scope"',
            '"calendar"',
            '"create"',
            '"show"',
            '"manage"',
            '"attention"',
            '"scheduleSources"',
            '"recurrencePolicies"',
            '"attendanceStatuses"',
            '"reminderAudiences"',
            '"eventStatuses"',
            '"capabilities"',
        ];

        foreach ($locales as $locale) {
            $catalogue = file_get_contents($root."/resources/js/localization/messages/events/{$locale}.ts");
            self::assertIsString($catalogue);
            self::assertGreaterThan(100, substr_count($catalogue, "\n"), "Events catalogue for {$locale} must contain the localized operational shell.");

            foreach ($requiredSections as $section) {
                self::assertStringContainsString($section, $catalogue, "Missing {$section} in Events locale {$locale}.");
            }
        }
    }
}
