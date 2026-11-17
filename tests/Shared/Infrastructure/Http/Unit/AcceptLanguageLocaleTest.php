<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Http\Unit;

use App\Shared\Infrastructure\Http\AcceptLanguageLocale;
use PHPUnit\Framework\TestCase;

final class AcceptLanguageLocaleTest extends TestCase
{
    public function test_quality_weights_are_sorted_and_ties_keep_original_order(): void
    {
        foreach ([
            ['fr-CA;q=0.2,de-DE;q=0.9', 'de'],
            ['fr-CA;q=0.7,de-DE;q=0.7', 'fr'],
            ['fr-CA;q=0.001,de-DE;q=0.010', 'de'],
            ['pt-BR;q=1,fr-CA;q=0.8', 'pt-BR'],
            ['en-US,en;q=0.9', 'en'],
            ['zh-Hant-TW,zh;q=0.9', 'zh-TW'],
            ['ar-u-nu-latn;q=0.8,fr-CA;q=0.5', 'ar'],
        ] as [$header, $expected]) {
            self::assertSame($expected, AcceptLanguageLocale::firstSupported($header), $header);
        }
    }

    public function test_invalid_ranges_do_not_hide_later_valid_candidates(): void
    {
        foreach ([
            [';;, , ;,fr-CA;q=0.9', 'fr'],
            ['zh-hantX;q=1,fr-CA;q=0.4', 'fr'],
            ['pt-brX;q=1,es-419;q=0.4', 'es'],
            ['fr-CA-;q=1,pt-BR;q=0.3', 'pt-BR'],
            ['en--US;q=1,ar-IQ;q=0.3', 'ar'],
            ['fr-CA;q=1.5,de-DE;q=0.4', 'de'],
            ['fr-CA;q=banana,ja-JP;q=0.2', 'ja'],
            ['fr-CA;q=0,es-419;q=0.4', 'es'],
            ['fr-CA;q=0,fr;q=0', 'en'],
            ["fr-CA\n;q=1,it-IT;q=0.2", 'it'],
            ['fr_CA;q=1,de-DE;q=0.3', 'de'],
            ['zh-Hant-HK-x-private;q=0.4,fr-CA;q=0.1', 'zh-TW'],
            ['fr-CA;q=1;extra=yes,de-DE;q=0.4', 'de'],
            ['*;q=1,ko-KR;q=0.8', 'ko'],
            ['zz-ZZ;q=1,vi-VN;q=0.2', 'vi'],
            [null, 'en'],
            ['', 'en'],
        ] as [$header, $expected]) {
            self::assertSame($expected, AcceptLanguageLocale::firstSupported($header), (string) $header);
        }
    }

    public function test_overlong_french_candidate_cannot_become_a_short_valid_prefix(): void
    {
        $long = 'fr-'.str_repeat('ab-', 80).'cd';
        self::assertGreaterThan(128, strlen($long));
        self::assertSame('en', AcceptLanguageLocale::firstSupported($long.';q=1,xx-ZZ;q=0.5'));
        self::assertSame('de', AcceptLanguageLocale::firstSupported($long.';q=1,de-DE;q=0.4'));
    }
}
