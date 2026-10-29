<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Http\Unit;

use App\Shared\Infrastructure\Http\SupportedLocale;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LanguageCatalogueTest extends TestCase
{
    #[DataProvider('catalogues')]
    public function test_account_and_framework_catalogues_preserve_every_key_and_parameter(string $locale, string $catalogue): void
    {
        $root = dirname(__DIR__, 5).'/lang';
        $framework = dirname(__DIR__, 5).'/vendor/laravel/framework/src/Illuminate/Translation/lang';
        $english = Arr::dot(require ($catalogue === 'accounts' ? $root : $framework).'/en/'.$catalogue.'.php');
        $localized = $locale === 'en' ? $english : Arr::dot(require $root.'/'.$locale.'/'.$catalogue.'.php');
        $translator = new Translator(new FileLoader(new Filesystem, [$framework, $root]), $locale);
        $validator = new Validator($translator, ['other' => 'selected'], [], [], ['input' => 'sample field', 'other' => 'related field']);

        self::assertSame(array_keys($english), array_keys($localized), $locale.'/'.$catalogue);
        foreach ($english as $key => $source) {
            if (! is_string($source)) {
                continue;
            }
            $context = $locale.'/'.$catalogue.'.'.$key;
            $value = $localized[$key];
            self::assertIsString($value, $context);
            self::assertNotSame('', trim($value), $context);
            self::assertSame($value, $translator->get($catalogue.'.'.$key, [], $locale, false), $context);

            // Laravel supports case variants, and natural translations may repeat a
            // parameter. Compare identities, then prove each rule actually renders them.
            $expected = self::parameters($source);
            self::assertSame($expected, self::parameters($value), $context);
            if ($catalogue === 'accounts' && $locale !== 'en') {
                self::assertNotSame($source, $value, $context);
            }

            if ($catalogue === 'validation') {
                $rule = explode('.', $key)[0];
                $rendered = $validator->makeReplacements($value, 'input', Str::studly($rule), self::ruleParameters($rule));
                if (in_array('attribute', $expected, true)) {
                    self::assertStringContainsString('sample field', strtolower($rendered), $context);
                }
            } else {
                $replacements = array_combine($expected, array_map(static fn (string $parameter): string => 'probe_'.$parameter, $expected));
                $rendered = $translator->get($catalogue.'.'.$key, $replacements, $locale, false);
                self::assertIsString($rendered, $context);
                foreach ($replacements as $replacement) {
                    self::assertStringContainsString($replacement, strtolower($rendered), $context);
                }
            }
            self::assertSame([], self::parameters($rendered), $context.' has unresolved runtime parameters: '.$rendered);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function catalogues(): iterable
    {
        foreach (SupportedLocale::CODES as $locale) {
            foreach (['accounts', 'auth', 'passwords', 'validation'] as $catalogue) {
                yield $locale.'/'.$catalogue => [$locale, $catalogue];
            }
        }
    }

    /** @return list<string> */
    private static function parameters(string $message): array
    {
        preg_match_all('/(?<![\w\/]):([A-Za-z_][A-Za-z0-9_]*)/', $message, $matches);
        $parameters = array_values(array_unique(array_map(strtolower(...), $matches[1])));
        sort($parameters);

        return $parameters;
    }

    /** @return list<string> */
    private static function ruleParameters(string $rule): array
    {
        return match ($rule) {
            'between', 'digits_between', 'decimal' => ['2', '8'],
            'digits', 'max', 'max_digits', 'min', 'min_digits', 'multiple_of', 'size' => ['8'],
            'after', 'after_or_equal', 'before', 'before_or_equal', 'date_equals' => ['2027-01-01'],
            'date_format' => ['Y-m-d'],
            'encoding' => ['UTF-8'],
            'accepted_if', 'declined_if', 'missing_if', 'missing_unless', 'present_if', 'present_unless',
            'prohibited_if', 'prohibited_unless', 'required_if', 'required_unless' => ['other', 'selected', 'alternate'],
            'different', 'gt', 'gte', 'in_array', 'lt', 'lte', 'prohibits', 'same',
            'prohibited_if_accepted', 'prohibited_if_declined', 'required_if_accepted', 'required_if_declined' => ['other'],
            'array_keys', 'doesnt_contain', 'doesnt_end_with', 'doesnt_start_with', 'ends_with', 'extensions',
            'in_array_keys', 'mimes', 'mimetypes', 'missing_with', 'missing_with_all', 'present_with', 'present_with_all',
            'required_array_keys', 'required_with', 'required_with_all', 'required_without', 'required_without_all', 'starts_with' => ['red', 'blue'],
            default => [],
        };
    }
}
