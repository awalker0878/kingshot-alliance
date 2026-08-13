<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EventAccessibilityGuardTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function phaseThreePages(): array
    {
        return [
            'event calendar' => ['resources/js/pages/Alliance/Events/Index.vue'],
            'event detail' => ['resources/js/pages/Alliance/Events/Show.vue'],
            'event coordinator' => ['resources/js/pages/Alliance/Events/Manage.vue'],
        ];
    }

    #[DataProvider('phaseThreePages')]
    public function test_phase_three_pages_keep_structural_accessibility_guards(string $path): void
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
