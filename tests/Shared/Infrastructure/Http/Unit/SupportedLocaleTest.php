<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Http\Unit;

use App\Contexts\Alliance\Lifecycle\Enums\SupportedAllianceLocale;
use App\Shared\Infrastructure\Http\LocaleDefinitions;
use App\Shared\Infrastructure\Http\SupportedLocale;
use PHPUnit\Framework\TestCase;

final class SupportedLocaleTest extends TestCase
{
    public function test_registry_definitions_and_alliance_content_language_remain_aligned(): void
    {
        $registry = json_decode(file_get_contents(dirname(__DIR__, 5).'/resources/data/localization/locales.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(array_column($registry['locales'], 'code'), SupportedLocale::CODES);
        self::assertSame($registry['defaultLocale'], SupportedLocale::orDefault(null));
        // The enum stays Alliance-owned; adding an interface locale requires an
        // explicit decision about its support as an alliance content language.
        self::assertSame(SupportedLocale::CODES, array_column(SupportedAllianceLocale::cases(), 'value'));
        foreach ($registry['locales'] as $locale) {
            $definition = $locale;
            unset($definition['code'], $definition['aliases']);
            self::assertSame($definition, LocaleDefinitions::LOCALES[$locale['code']]);
            self::assertSame($locale['direction'], SupportedLocale::direction($locale['code']));
            foreach ($locale['aliases'] as $alias) {
                self::assertSame($locale['code'], SupportedLocale::normalize($alias['tag']));
                self::assertSame($alias['descendants'] ? $locale['code'] : null, SupportedLocale::normalize($alias['tag'].'-xx'));
            }
        }
    }

    public function test_regional_browser_languages_resolve_to_available_catalogues(): void
    {
        foreach (['fr-CA' => 'fr', 'pt_BR' => 'pt-BR', 'zh-Hans-CN' => 'zh-CN', 'zh-Hant-HK' => 'zh-TW', 'ar-IQ' => 'ar', 'EN-us' => 'en'] as $input => $expected) {
            self::assertSame($expected, SupportedLocale::normalize($input));
        }
    }

    public function test_untrusted_preferences_cannot_select_an_unknown_catalogue_or_path(): void
    {
        foreach ([null, [], 17, '', '../fr', 'zz', 'fr/../../en'] as $input) {
            self::assertNull(SupportedLocale::normalize($input));
            self::assertSame('en', SupportedLocale::orDefault($input));
        }
    }

    public function test_php_normalization_matches_the_shared_client_fixture(): void
    {
        $raw = file_get_contents(dirname(__DIR__, 5).'/resources/data/localization/locale-normalization-cases.json');
        self::assertNotFalse($raw);
        $fixtures = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        foreach ($fixtures['cases'] as $case) {
            self::assertSame($case['expected'], SupportedLocale::normalize($case['input']), json_encode($case['input']));
        }
        foreach ($fixtures['priority'] as $case) {
            self::assertSame($case['expected'], SupportedLocale::firstSupported($case['candidates']));
        }
    }
}
