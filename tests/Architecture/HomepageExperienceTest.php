<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class HomepageExperienceTest extends TestCase
{
    public function test_homepage_uses_public_shell_and_real_authentication_routes_only(): void
    {
        $home = $this->read('resources/js/pages/Home.vue');

        self::assertStringContainsString("import PublicLayout from '../layouts/PublicLayout.vue';", $home);
        self::assertStringContainsString('<PublicLayout>', $home);
        self::assertStringContainsString('href="/login"', $home);
        self::assertStringContainsString('href="/register"', $home);
        self::assertStringContainsString("t('home.heroLine1')", $home);
        self::assertStringContainsString("t('home.heroLine2')", $home);
        self::assertStringContainsString('id="features"', $home);

        foreach ([
            '/up',
            '/health/ready',
            '2.48B',
            '93/100',
            'Google',
            'Discord login',
            'Magic link',
        ] as $unsupported) {
            self::assertStringNotContainsString($unsupported, $home, $unsupported);
        }
    }

    public function test_homepage_strings_are_available_for_every_supported_locale(): void
    {
        $messages = $this->read('resources/js/localization/messages/public/en.ts');
        $messageIndex = $this->read('resources/js/localization/messages/index.ts');

        foreach ([
            'en',
            'ar',
            'de',
            'es',
            'fr',
            'id',
            'it',
            'ja',
            'ko',
            'pl',
            'pt-BR',
            'ru',
            'th',
            'tr',
            'vi',
            'zh-CN',
            'zh-TW',
        ] as $locale) {
            $this->assertTypeScriptObjectKey($messages, $locale);
        }

        foreach ([
            'heroLine1',
            'heroLine2',
            'eventsDesc',
            'rosterDesc',
            'recruitmentDesc',
            'kingdomDesc',
            'transfersDesc',
            'contentDesc',
            'publicPagesTitle',
            'multilingualTitle',
        ] as $key) {
            $this->assertTypeScriptObjectKey($messages, $key);
        }

        self::assertStringContainsString("import { publicMessages } from './public';", $messageIndex);
        self::assertStringContainsString('...publicMessages[locale]', $messageIndex);
    }

    private function assertTypeScriptObjectKey(string $source, string $key): void
    {
        self::assertMatchesRegularExpression(
            '/(?:^|\s)[\'\"]?'.preg_quote($key, '/').'[\'\"]?\s*:/m',
            $source,
            $key,
        );
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
