<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domain\Alliances\Models\Alliance;

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
