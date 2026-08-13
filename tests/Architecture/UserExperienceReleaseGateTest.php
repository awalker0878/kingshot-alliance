<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class UserExperienceReleaseGateTest extends TestCase
{
    public function test_shared_shells_and_css_keep_accessibility_and_international_layout_contracts(): void
    {
        $app = $this->read('resources/js/layouts/AppLayout.vue');
        $public = $this->read('resources/js/layouts/PublicLayout.vue');
        $auth = $this->read('resources/js/layouts/AuthLayout.vue');
        $css = $this->read('resources/css/app.css');

        foreach ([['href="#main-content"', $app], ['id="main-content"', $app], ['tabindex="-1"', $app], ['start-0', $app], ['border-e', $app], ['href="#public-content"', $public], ['tabindex="-1"', $public], ['href="#auth-content"', $auth], ['tabindex="-1"', $auth]] as [$needle, $source]) {
            self::assertStringContainsString($needle, $source, $needle);
        }

        foreach ([':focus-visible', "html[dir='rtl']", "html[lang='ja']", "html[lang='zh-CN']", "html[lang='th']", 'forced-colors: active', 'prefers-reduced-motion: reduce', 'overflow-wrap: break-word', 'max-width: 100%'] as $needle) {
            self::assertStringContainsString($needle, $css, $needle);
        }
    }

    public function test_browser_visual_gate_covers_desktop_mobile_rtl_and_keyboard(): void
    {
        $package = $this->read('package.json');
        $config = $this->read('playwright.config.ts');
        $spec = $this->read('tests/Visual/ux-p9.spec.ts');
        $workflow = $this->read('.github/workflows/visual-regression.yml');

        self::assertStringContainsString('@playwright/test', $package);
        self::assertStringContainsString('test:visual', $package);
        self::assertStringContainsString("name: 'desktop'", $config);
        self::assertStringContainsString("name: 'mobile'", $config);
        self::assertStringContainsString("reducedMotion: 'reduce'", $config);

        foreach (['home Arabic RTL baseline', 'login Arabic RTL baseline', 'authenticated application shell English baseline', 'authenticated application shell Arabic RTL baseline', 'keyboard skip link reaches the main application content'] as $coverage) {
            self::assertStringContainsString($coverage, $spec, $coverage);
        }

        self::assertStringContainsString('npm run test:visual', $workflow);
        self::assertStringContainsString('playwright install --with-deps chromium', $workflow);
        self::assertGreaterThanOrEqual(12, count($this->baselines()));
    }

    public function test_release_matrix_and_catalogue_cover_p9_quality_tiers(): void
    {
        $qa = $this->read('docs/product/ux-001-release-qa.md');
        $locales = $this->read('resources/js/localization/locales.ts');

        foreach (['Tier A — primary visual QA', 'Tier B — complex-script/layout QA', 'Tier C — catalogue and stress QA', 'Accessibility checklist', 'Responsive and international-layout checklist', 'Motion, media, and layout stability', 'Final terminology review', 'Rollout evidence', '1440px desktop', '768px tablet', '390px mobile'] as $needle) {
            self::assertStringContainsString($needle, $qa, $needle);
        }

        foreach (['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'] as $locale) {
            self::assertStringContainsString("'".$locale."'", $locales, $locale);
        }
    }

    public function test_release_candidate_contains_no_temporary_ux_files(): void
    {
        self::assertSame([], glob($this->root().'/.ux-*') ?: []);
        self::assertSame([], glob($this->root().'/.github/workflows/ux-*-apply.yml') ?: []);
    }

    private function baselines(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root().'/tests/Visual'));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'png') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
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
