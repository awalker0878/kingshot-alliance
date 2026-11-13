<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class SupportedLocale
{
    /** @var list<string> */
    public const CODES = ['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'];

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || str_contains($value, "\0")) {
            return null;
        }

        $code = strtolower(str_replace('_', '-', trim($value)));
        if (strlen($code) > 128 || preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*(?:-[a-z0-9](?:-[a-z0-9]{2,8})+)*\z/D', $code) !== 1) {
            return null;
        }
        if ($code === 'pt' || $code === 'pt-br' || str_starts_with($code, 'pt-br-')) {
            return 'pt-BR';
        }
        if (preg_match('/\Azh-(tw|hant|hk)(-|$)/', $code) === 1) {
            return 'zh-TW';
        }
        if ($code === 'zh' || preg_match('/\Azh-(cn|hans)(-|$)/', $code) === 1) {
            return 'zh-CN';
        }

        $language = explode('-', $code)[0];

        return in_array($language, self::CODES, true) ? $language : null;
    }

    /** @param array<array-key, mixed> $candidates */
    public static function firstSupported(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $locale = self::normalize($candidate);
            if ($locale !== null) {
                return $locale;
            }
        }

        return 'en';
    }

    public static function orDefault(mixed $value): string
    {
        return self::normalize($value) ?? 'en';
    }
}
