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
