<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Http\Unit;

use App\Shared\Infrastructure\Http\SupportedLocale;
use PHPUnit\Framework\TestCase;

final class SupportedLocaleTest extends TestCase
{
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
}
