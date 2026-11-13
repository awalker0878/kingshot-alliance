<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class SupportedLocale
{
    /** @var list<string> */
    public const CODES = LocaleDefinitions::CODES;

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || str_contains($value, "\0")) {
            return null;
        }

        $code = strtolower(str_replace('_', '-', trim($value)));
        if (strlen($code) > 128 || preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*(?:-[a-z0-9](?:-[a-z0-9]{2,8})+)*\z/D', $code) !== 1) {
            return null;
        }
        foreach (LocaleDefinitions::ALIASES as $alias) {
            if ($code === $alias['tag'] || ($alias['descendants'] && str_starts_with($code, $alias['tag'].'-'))) {
                return $alias['code'];
            }
        }

        return null;
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

        return LocaleDefinitions::DEFAULT;
    }

    public static function direction(mixed $value): string
    {
        return LocaleDefinitions::LOCALES[self::orDefault($value)]['direction'];
    }

    public static function orDefault(mixed $value): string
    {
        return self::normalize($value) ?? LocaleDefinitions::DEFAULT;
    }
}
