<?php

declare(strict_types=1);

namespace Tests\Feature\Contributions;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ContributionAccessibilityGuardTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function phaseFivePages(): array
    {
        return [
            'member contribution progress' => ['resources/js/pages/Alliance/Contributions/Index.vue'],
            'contribution reporting management' => ['resources/js/pages/Alliance/Contributions/Manage.vue'],
        ];
    }

    #[DataProvider('phaseFivePages')]
    public function test_phase_five_pages_keep_structural_accessibility_guards(string $path): void
    {
        $source = file_get_contents(base_path($path));
        self::assertIsString($source);

        self::assertStringContainsString('<main', $source, "{$path} must expose a main landmark.");
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
