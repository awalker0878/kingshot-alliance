<?php

declare(strict_types=1);

namespace Tests\v3\Frontend;

use PHPUnit\Framework\TestCase;

final class AllianceContentGameParityFrontendContractV3Test extends TestCase
{
    public function test_alliance_rules_pages_load_the_content_localization_domain(): void
    {
        $root = dirname(__DIR__, 3);
        $registry = $this->source($root.'/resources/js/localization/registry.ts');
        $rules = $this->source($root.'/resources/js/pages/Alliance/Rules/Index.vue');

        self::assertStringContainsString("name.startsWith('Alliance/Rules/')", $registry);
        self::assertMatchesRegularExpression(
            "/Alliance\\/Rules\\/.*?domains\\.add\\('content'\\)/s",
            $registry,
        );
        self::assertStringContainsString("t('contentExperience.rulesTitle')", $rules);
        self::assertStringContainsString("t('contentExperience.rulesEmpty')", $rules);
        self::assertStringContainsString("t('contentExperience.rulesSave')", $rules);
    }

    public function test_notice_reaction_controls_expose_toggle_off_semantics_and_busy_state(): void
    {
        $root = dirname(__DIR__, 3);
        $controls = $this->source($root.'/resources/js/components/alliance/NoticeReactionControls.vue');

        foreach ([
            "router.delete(url, options)",
            "router.put(url, { reaction }, options)",
            ':aria-pressed=',
            ':aria-busy="processing"',
            "removeLikeCountLabel",
            "removeDislikeCountLabel",
        ] as $expected) {
            self::assertStringContainsString($expected, $controls);
        }
    }

    private function source(string $path): string
    {
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
