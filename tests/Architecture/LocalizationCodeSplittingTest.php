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
}
