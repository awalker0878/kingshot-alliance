<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Http\Unit;

use App\Shared\Infrastructure\Http\SupportedLocale;
use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

final class LanguageCatalogueTest extends TestCase
{
    public function test_account_and_framework_catalogues_preserve_every_key_and_parameter(): void
    {
        $root = dirname(__DIR__, 5).'/lang';
        $framework = dirname(__DIR__, 5).'/vendor/laravel/framework/src/Illuminate/Translation/lang/en';
        foreach (['accounts', 'auth', 'passwords', 'validation'] as $catalogue) {
            $english = Arr::dot(require ($catalogue === 'accounts' ? $root.'/en' : $framework).'/'.$catalogue.'.php');
            foreach (SupportedLocale::CODES as $locale) {
                $localized = $locale === 'en' ? $english : Arr::dot(require $root.'/'.$locale.'/'.$catalogue.'.php');
                self::assertSame(array_keys($english), array_keys($localized), $locale.'/'.$catalogue);
                foreach ($english as $key => $source) {
                    if (! is_string($source)) {
                        continue;
                    }
                    $value = $localized[$key];
                    self::assertIsString($value);
                    self::assertNotSame('', trim($value), $locale.'/'.$catalogue.'.'.$key);
                    preg_match_all('/(?<![\w\/]):[A-Za-z_][A-Za-z0-9_]*/', $source, $expected);
                    preg_match_all('/(?<![\w\/]):[A-Za-z_][A-Za-z0-9_]*/', $value, $actual);
                    sort($expected[0]);
                    sort($actual[0]);
                    self::assertSame($expected[0], $actual[0], $locale.'/'.$catalogue.'.'.$key);
                    if ($catalogue === 'accounts' && $locale !== 'en') {
                        self::assertNotSame($source, $value, $locale.'/'.$catalogue.'.'.$key);
                    }
                }
            }
        }
    }
}
