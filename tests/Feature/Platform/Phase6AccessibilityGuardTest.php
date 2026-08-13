<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class Phase6AccessibilityGuardTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return [
            'platform administration' => ['resources/js/pages/Platform/Administration/Index.vue'],
            'integration management' => ['resources/js/pages/Alliance/Integrations/Manage.vue'],
            'account deletion' => ['resources/js/pages/AccountDeletion.vue'],
        ];
    }

    #[DataProvider('pages')]
    public function test_phase_six_pages_keep_structural_accessibility_guards(string $path): void
    {
        $source = file_get_contents(base_path($path));
        self::assertIsString($source);

        $ownsMain = str_contains($source, '<main');
        $usesAppLayout = str_contains($source, '<AppLayout');
        self::assertTrue($ownsMain || $usesAppLayout, "{$path} must expose or inherit a main landmark.");

        if ($usesAppLayout) {
            $layout = file_get_contents(base_path('resources/js/layouts/AppLayout.vue'));
            self::assertIsString($layout);
            self::assertStringContainsString('<main', $layout, 'AppLayout must own the inherited main landmark.');
        }

        self::assertStringContainsString('<h1', $source, "{$path} must expose a primary heading.");
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
}
