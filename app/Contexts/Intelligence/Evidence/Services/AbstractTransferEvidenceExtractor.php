<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\ValueObjects\ExtractedFieldCandidate;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;

abstract class AbstractTransferEvidenceExtractor
{
    /** @param list<OcrToken> $tokens */
    protected function lineText(array $tokens): string
    {
        return trim(implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $tokens)));
    }

    protected function normalizeInteger(string $value): ?int
    {
        $compact = str_replace([',', ' ', ':'], '', trim($value));
        if ($compact === '' || preg_match('/^\d+$/', $compact) !== 1) {
            return null;
        }
        if (strlen($compact) > 19) {
            return null;
        }
        $number = (int) $compact;
        if ($number < 0) {
            return null;
        }

        return $number;
    }

    /**
     * @param list<OcrToken> $line
     * @param list<string> $signals
     */
    protected function numericCandidate(array $line, array $signals, string $fieldKey, int $ordinal = 0): ?ExtractedFieldCandidate
    {
        $text = mb_strtolower($this->lineText($line));
        $matched = false;
        foreach ($signals as $signal) {
            if (str_contains($text, mb_strtolower($signal))) {
                $matched = true;
                break;
            }
        }
        if (! $matched) {
            return null;
        }

        for ($index = count($line) - 1; $index >= 0; $index--) {
            $normalized = $this->normalizeInteger($line[$index]->text);
            if ($normalized !== null) {
                return $this->candidate($fieldKey, $ordinal, [$line[$index]], (string) $normalized, 'integer');
            }
        }

        return null;
    }

    /** @param list<OcrToken> $line */
    protected function kingdomNumber(array $line): ?int
    {
        $text = $this->lineText($line);
        if (preg_match('/\bkingdom\s*#?\s*(\d{1,6})\b/i', $text, $match) === 1) {
            return (int) $match[1];
        }
        if (preg_match('/(?:^|\s)#(\d{1,6})(?:\s|$)/', $text, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    /** @param non-empty-list<OcrToken> $tokens */
    protected function candidate(string $key, int $ordinal, array $tokens, string $normalized, string $type, array $warnings = []): ExtractedFieldCandidate
    {
        $confidenceTotal = 0.0;
        $left = $tokens[0]->left;
        $top = $tokens[0]->top;
        $right = $tokens[0]->left + $tokens[0]->width;
        $bottom = $tokens[0]->top + $tokens[0]->height;
        foreach ($tokens as $token) {
            $confidenceTotal += $token->confidence;
            $left = min($left, $token->left);
            $top = min($top, $token->top);
            $right = max($right, $token->left + $token->width);
            $bottom = max($bottom, $token->top + $token->height);
        }

        return new ExtractedFieldCandidate(
            fieldKey: $key,
            rowOrdinal: $ordinal,
            rawText: $this->lineText($tokens),
            normalizedValue: $normalized,
            dataType: $type,
            confidence: max(0.0, min(1.0, $confidenceTotal / count($tokens))),
            boundingBox: ['left' => $left, 'top' => $top, 'width' => $right - $left, 'height' => $bottom - $top],
            warnings: $warnings,
        );
    }
}
