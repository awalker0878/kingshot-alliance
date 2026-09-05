<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use Carbon\CarbonImmutable;

final class GiftCodeEvidenceExtractor
{
    /**
     * @return list<array{
     *   code:string,
     *   claimed_expires_at:?string,
     *   expiry_precision:?string,
     *   expiry_timezone:?string,
     *   applicability:?array<string,mixed>,
     *   reward:?array<string,mixed>
     * }>
     */
    public function extract(string $content, ?string $publishedAt = null): array
    {
        $text = $this->normalize($content);
        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $results = [];

        foreach ($lines as $index => $line) {
            if (! is_string($line)) {
                continue;
            }
            if (preg_match(
                '/^\s*(?:🎁\s*)?(?:gift\s*code|redeem\s*code)\s*[:：-]\s*([A-Za-z0-9_-]{3,64})\s*[.!]?\s*$/iu',
                trim($line),
                $matches,
            ) !== 1) {
                continue;
            }

            $code = $matches[1];
            $window = array_slice($lines, $index + 1, 8);
            $expiry = $this->extractExpiry($window, $publishedAt);
            $applicability = $this->extractLabelledValue($window, [
                'region', 'regions', 'server', 'servers', 'kingdom', 'kingdoms', 'available in', 'applicable to',
            ]);
            $reward = $this->extractLabelledValue($window, ['reward', 'rewards']);

            $results[] = [
                'code' => $code,
                'claimed_expires_at' => $expiry['value'],
                'expiry_precision' => $expiry['precision'],
                'expiry_timezone' => $expiry['timezone'],
                'applicability' => $applicability === null ? null : ['text' => $applicability],
                'reward' => $reward === null ? null : ['text' => $reward],
            ];
        }

        $seen = [];

        return array_values(array_filter($results, static function (array $result) use (&$seen): bool {
            $key = $result['code'].'|'.($result['claimed_expires_at'] ?? '');
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));
    }

    private function normalize(string $content): string
    {
        $content = preg_replace('/<br\s*\/?\s*>/iu', "\n", $content) ?? $content;
        $content = preg_replace('/<\/(?:p|div|li|h[1-6])\s*>/iu', "\n", $content) ?? $content;
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[\t ]+/u', ' ', $content) ?? $content;
        $content = preg_replace('/\n{3,}/u', "\n\n", $content) ?? $content;

        return trim($content);
    }

    /** @param list<string> $lines */
    private function extractExpiry(array $lines, ?string $publishedAt): array
    {
        foreach ($lines as $line) {
            if (! is_string($line)) {
                continue;
            }
            if (preg_match('/^\s*(?:valid\s+until|valid\s+till|expires?|expiry)\s*[:：-]\s*(.+?)\s*$/iu', trim($line), $matches) !== 1) {
                continue;
            }
            $raw = trim($matches[1]);
            $timezone = preg_match('/\((UTC(?:[+-]\d{1,2}(?::?\d{2})?)?)\)/iu', $raw, $tz) === 1
                ? strtoupper($tz[1])
                : null;
            $candidate = preg_replace('/\s*\(UTC(?:[+-]\d{1,2}(?::?\d{2})?)?\)\s*/iu', ' ', $raw) ?? $raw;
            $candidate = trim($candidate);

            $published = null;
            if ($publishedAt !== null) {
                try {
                    $published = CarbonImmutable::parse($publishedAt);
                } catch (\Throwable) {
                    $published = null;
                }
            }

            if ($published !== null && preg_match('/\b\d{4}\b/u', $candidate) !== 1) {
                $candidate .= ' '.$published->year;
            }

            try {
                $parsed = CarbonImmutable::parse($candidate, 'UTC');
            } catch (\Throwable) {
                return ['value' => null, 'precision' => null, 'timezone' => $timezone];
            }

            return [
                'value' => $parsed->utc()->toIso8601String(),
                'precision' => preg_match('/\b\d{1,2}:\d{2}\b/u', $candidate) === 1 ? 'minute' : 'day',
                'timezone' => $timezone ?? 'UTC',
            ];
        }

        return ['value' => null, 'precision' => null, 'timezone' => null];
    }

    /** @param list<string> $lines @param list<string> $labels */
    private function extractLabelledValue(array $lines, array $labels): ?string
    {
        $quoted = implode('|', array_map(static fn (string $label): string => preg_quote($label, '/'), $labels));
        foreach ($lines as $line) {
            if (! is_string($line)) {
                continue;
            }
            if (preg_match('/^\s*(?:'.$quoted.')\s*[:：-]\s*(.+?)\s*$/iu', trim($line), $matches) === 1) {
                $value = trim($matches[1]);

                return $value === '' ? null : mb_substr($value, 0, 2000);
            }
        }

        return null;
    }
}
