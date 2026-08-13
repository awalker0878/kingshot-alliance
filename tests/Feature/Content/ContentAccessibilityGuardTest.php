<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ContentAccessibilityGuardTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function phaseTwoPages(): array
    {
        return [
            'public alliance' => ['resources/js/pages/Public/Alliance.vue'],
            'public content' => ['resources/js/pages/Public/Content.vue'],
            'member content index' => ['resources/js/pages/Alliance/ContentIndex.vue'],
            'member content detail' => ['resources/js/pages/Alliance/ContentDetail.vue'],
            'content management' => ['resources/js/pages/Alliance/ContentManage.vue'],
            'alliance overview' => ['resources/js/pages/Alliance/Overview.vue'],
        ];
    }

    #[DataProvider('phaseTwoPages')]
    public function test_phase_two_pages_keep_structural_accessibility_guards(string $path): void
    {
        $source = file_get_contents(base_path($path));
        self::assertIsString($source);

        $this->assertMainLandmark($path, $source);
        self::assertStringNotContainsString('v-html', $source, "{$path} must not render untrusted HTML.");
        self::assertDoesNotMatchRegularExpression(
            '/\btabindex\s*=\s*["\']\s*[1-9][0-9]*\s*["\']/i',
            $source,
            "{$path} must not introduce a positive tabindex.",
        );

        preg_match_all('/<button\b[^>]*>/is', $source, $buttons);
        foreach ($buttons[0] as $button) {
            self::assertStringContainsString('type=', $button, "{$path} buttons must declare their type.");
        }
    }

    private function assertMainLandmark(string $path, string $source): void
    {
        if (str_contains($source, '<main')) {
            return;
        }

        if (str_contains($source, 'PublicLayout')) {
            self::assertStringContainsString('<PublicLayout>', $source, "{$path} must render the shared public layout.");

            $layout = file_get_contents(base_path('resources/js/layouts/PublicLayout.vue'));
            self::assertIsString($layout);
            self::assertStringContainsString(
                '<main id="public-content"',
                $layout,
                'PublicLayout must expose the main landmark.',
            );
            self::assertStringContainsString(
                'tabindex="-1"',
                $layout,
                'PublicLayout main must accept skip-link focus.',
            );

            return;
        }

        self::assertStringContainsString('AppLayout', $source, "{$path} must use the shared application layout.");
        self::assertStringContainsString('<AppLayout', $source, "{$path} must render the shared application layout.");

        $layout = file_get_contents(base_path('resources/js/layouts/AppLayout.vue'));
        self::assertIsString($layout);
        self::assertStringContainsString(
            '<main id="main-content"',
            $layout,
            'AppLayout must expose the main landmark.',
        );
    }
}
