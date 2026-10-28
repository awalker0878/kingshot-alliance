<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class SupportedLocale
{
    /** @var list<string> */
    public const CODES = ['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'];

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $code = strtolower(str_replace('_', '-', trim($value)));
        if ($code === 'pt' || str_starts_with($code, 'pt-br')) {
            return 'pt-BR';
        }
        if (preg_match('/^zh-(tw|hant|hk)(-|$)/', $code) === 1) {
            return 'zh-TW';
        }
        if ($code === 'zh' || preg_match('/^zh-(cn|hans)(-|$)/', $code) === 1) {
            return 'zh-CN';
        }

        $language = explode('-', $code)[0];

        return in_array($language, self::CODES, true) ? $language : null;
    }

    public static function orDefault(mixed $value): string
    {
        return self::normalize($value) ?? 'en';
    }
}
