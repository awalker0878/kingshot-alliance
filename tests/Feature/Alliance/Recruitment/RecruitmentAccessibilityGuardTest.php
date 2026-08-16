<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Recruitment;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RecruitmentAccessibilityGuardTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function phaseFourPages(): array
    {
        return [
            'public recruitment application' => ['resources/js/pages/Public/RecruitmentApply.vue'],
            'recruitment pipeline' => ['resources/js/pages/Alliance/Recruitment/Manage.vue'],
            'recruitment candidate review' => ['resources/js/pages/Alliance/Recruitment/Candidate.vue'],
        ];
    }

    #[DataProvider('phaseFourPages')]
    public function test_phase_four_pages_keep_structural_accessibility_guards(string $path): void
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

        if (str_contains($source, 'AppLayout')) {
            self::assertStringContainsString('<AppLayout', $source, "{$path} must render the shared app layout.");
            $layout = file_get_contents(base_path('resources/js/layouts/AppLayout.vue'));
            self::assertIsString($layout);
            self::assertStringContainsString('<main', $layout, 'AppLayout must expose the main landmark.');

            return;
        }

        self::assertStringContainsString(
            "import PublicLayout from '../../layouts/PublicLayout.vue';",
            $source,
            "{$path} must either own a main landmark or use a shared layout.",
        );
        self::assertStringContainsString('<PublicLayout>', $source, "{$path} must render the shared public layout.");

        $layout = file_get_contents(base_path('resources/js/layouts/PublicLayout.vue'));
        self::assertIsString($layout);
        self::assertStringContainsString('<main id="public-content"', $layout, 'PublicLayout must expose the main landmark.');
        self::assertStringContainsString('tabindex="-1"', $layout, 'PublicLayout main must accept skip-link focus.');
    }
}
