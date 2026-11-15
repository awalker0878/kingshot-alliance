<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class LocalePreference
{
    public const COOKIE = 'kingshot_locale_state';

    public static function locale(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 128
            || preg_match('/\A1\.[a-f0-9]{32}\.(0|[1-9][0-9]{0,15})\.[a-f0-9]{32}\.([A-Za-z-]+)\z/D', $value, $matches) !== 1
            || (int) $matches[1] > 9007199254740991
            || ! in_array($matches[2], SupportedLocale::CODES, true)) {
            return null;
        }

        return $matches[2];
    }
}
