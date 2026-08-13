<?php

use App\Support\Localization\Locale;

it('keeps pages and localization catalogues code split by domain and locale', function (): void {
    $app = file_get_contents(resource_path('js/app.ts'));
    expect($app)
        ->toContain("import.meta.glob<DefineComponent>('./pages/**/*.vue'")
        ->not->toContain('eager: true')
        ->toContain('await ensurePageDomains(name)');

    $registry = file_get_contents(resource_path('js/localization/registry.ts'));
    expect($registry)
        ->toContain("import.meta.glob<MessageModule>('./messages/*/*.ts')")
        ->toContain("'kingdom'")
        ->toContain("'transfers'")
        ->toContain("'platform'");

    $domains = ['core', 'auth', 'account', 'alliance', 'events', 'roster', 'contributions', 'recruitment', 'content', 'integrations', 'kingdom', 'transfers', 'platform', 'public'];
    $locales = ['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'];

    foreach ($domains as $domain) {
        foreach ($locales as $locale) {
            expect(resource_path("js/localization/messages/{$domain}/{$locale}.ts"))->toBeFile();
        }
    }

    $legacyFiles = glob(resource_path('js/localization/messages/*.ts')) ?: [];
    expect($legacyFiles)->toBe([]);
});

it('loads english fallback plus locale overrides and caches domain requests', function (): void {
    $loader = file_get_contents(resource_path('js/localization/loader.ts'));
    expect($loader)
        ->toContain('const catalogues = new Map')
        ->toContain('const pending = new Map')
        ->toContain('await loadOne(domain, defaultLocale)')
        ->toContain('if (locale !== defaultLocale)')
        ->toContain('resolveMessage');

    $runtime = file_get_contents(resource_path('js/localization/index.ts'));
    expect($runtime)
        ->toContain('export async function setLocale')
        ->toContain('export async function ensurePageDomains')
        ->toContain("return ['core', ...currentPageDomains]")
        ->toContain('await loadDomains(locale, activeDomains())');
});
