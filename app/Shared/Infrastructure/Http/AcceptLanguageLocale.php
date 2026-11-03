<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class AcceptLanguageLocale
{
    // Deliberately bounded application profile: primary language, optional script,
    // optional region, and complete extension/private-use sequences. Unknown
    // free-form variants are not admitted by the HTTP header boundary.
    // Unlike Symfony's language parser, never recover a truncated prefix.
    private const RANGE_PATTERN = '/\A[a-z]{2,3}(?:-[a-z]{4})?(?:-(?:[a-z]{2}|[0-9]{3}))?(?:-[a-z0-9](?:-[a-z0-9]{2,8})+)*\z/i';

    private const QUALITY_PATTERN = '/\Aq[ \t]*=[ \t]*(0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)\z/i';

    public static function firstSupported(?string $rawHeader): string
    {
        if ($rawHeader === null || $rawHeader === '') {
            return 'en';
        }

        /** @var list<array{locale: string, quality: int, position: int}> $choices */
        $choices = [];
        foreach (explode(',', $rawHeader) as $position => $rawEntry) {
            // Only optional HTTP SP/HTAB is trimmed; control characters are
            // not silently normalized into an otherwise valid language range.
            $entry = trim($rawEntry, " \t");
            $parts = explode(';', $entry);
            if (count($parts) > 2) {
                continue;
            }

            $range = trim($parts[0], " \t");
            if (strlen($range) > 128 || preg_match(self::RANGE_PATTERN, $range) !== 1) {
                continue;
            }

            $quality = 1000; // q=1 when omitted
            if (count($parts) === 2) {
                $parameter = trim($parts[1], " \t");
                if (preg_match(self::QUALITY_PATTERN, $parameter, $match) !== 1) {
                    continue;
                }

                // q has at most three fractional digits; compare as an integer
                // to avoid floating-point ambiguities and keep ties stable.
                $q = $match[1];
                $quality = $q[0] === '1'
                    ? 1000
                    : (int) str_pad(substr($q, 2), 3, '0');
            }

            // q=0 means explicitly unacceptable; never choose it as fallback.
            if ($quality === 0) {
                continue;
            }

            $locale = SupportedLocale::normalize($range);
            if ($locale === null) {
                continue;
            }

            $choices[] = ['locale' => $locale, 'quality' => $quality, 'position' => $position];
        }

        usort($choices, static fn (array $a, array $b): int => ($b['quality'] <=> $a['quality']) ?: ($a['position'] <=> $b['position'])
        );

        return $choices[0]['locale'] ?? 'en';
    }
}
