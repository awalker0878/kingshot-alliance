<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ExtractedFieldCandidate;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;

final class BearHuntBattleReportExtractor implements EvidenceExtractor
{
    public function key(): string
    {
        return 'bear-hunt-ranking-v1';
    }

    public function version(): string
    {
        return '1.1.0';
    }

    public function schemaVersion(): string
    {
        return 'bear-hunt-report/1';
    }

    public function supports(EvidenceKind $kind): bool
    {
        return $kind === EvidenceKind::BearHuntBattleReport;
    }

    public function extract(OcrDocument $document): array
    {
        $fields = [];
        $rankingSeen = false;
        /** @var list<OcrToken>|null $pendingName */
        $pendingName = null;
        $ordinal = 0;

        foreach ($document->lines() as $line) {
            if ($line === []) {
                continue;
            }
            $lineText = trim(implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $line)));
            if ($lineText === '') {
                continue;
            }

            $timestamp = $this->timestamp($lineText);
            if ($timestamp !== null && ! $rankingSeen) {
                $fields[] = $this->candidate('report_timestamp', 0, $line, $timestamp, 'datetime_text');

                continue;
            }

            if (str_contains(mb_strtolower($lineText), 'ranking')) {
                $rankingSeen = true;
                $pendingName = null;

                continue;
            }
            if (! $rankingSeen) {
                continue;
            }

            $damageIndex = $this->damageTokenIndex($line);
            if ($damageIndex !== null) {
                $damage = $this->normalizeDamage($line[$damageIndex]->text);
                if ($damage === null) {
                    continue;
                }

                $inlineNameTokens = $this->inlineNameTokens($line, $damageIndex);
                $nameSource = $inlineNameTokens !== [] ? $inlineNameTokens : $pendingName;
                if ($nameSource === null || $nameSource === []) {
                    continue;
                }
                $nameSource = array_values($nameSource);
                [$rank, $nameTokens] = $this->rankAndName($nameSource);
                if ($nameTokens === []) {
                    continue;
                }
                $name = trim(implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $nameTokens)));
                if ($name === '') {
                    continue;
                }

                $ordinal++;
                if ($rank !== null) {
                    $fields[] = $this->candidate('rank', $ordinal, [$nameSource[0]], (string) $rank, 'integer');
                }
                $fields[] = $this->candidate('player_name', $ordinal, $nameTokens, $name, 'string');
                $fields[] = $this->candidate('damage', $ordinal, [$line[$damageIndex]], (string) $damage, 'integer');
                $pendingName = null;

                continue;
            }

            if ($this->isCandidateNameLine($lineText)) {
                $pendingName = array_values($line);
            }
        }

        return $fields;
    }

    private function timestamp(string $text): ?string
    {
        if (preg_match('/\b(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\b/', $text, $match) !== 1) {
            return null;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', (int) $match[1], (int) $match[2], (int) $match[3], (int) $match[4], (int) $match[5], isset($match[6]) ? (int) $match[6] : 0);
    }

    /** @param  list<OcrToken>  $line */
    private function damageTokenIndex(array $line): ?int
    {
        $text = mb_strtolower(implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $line)));
        if (! str_contains($text, 'damage')) {
            return null;
        }
        for ($index = count($line) - 1; $index >= 0; $index--) {
            if ($this->normalizeDamage($line[$index]->text) !== null) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  list<OcrToken>  $line
     * @return list<OcrToken>
     */
    private function inlineNameTokens(array $line, int $damageIndex): array
    {
        $damageWord = null;
        for ($index = 0; $index < $damageIndex; $index++) {
            if (str_contains(mb_strtolower($line[$index]->text), 'damage')) {
                $damageWord = $index;
                break;
            }
        }
        if ($damageWord === null || $damageWord === 0) {
            return [];
        }

        return array_values(array_slice($line, 0, $damageWord));
    }

    /**
     * @param  list<OcrToken>  $tokens
     * @return array{0:?int,1:list<OcrToken>}
     */
    private function rankAndName(array $tokens): array
    {
        if ($tokens === []) {
            return [null, []];
        }
        $candidate = ltrim(trim($tokens[0]->text), '#');
        if (ctype_digit($candidate) && (int) $candidate >= 1 && (int) $candidate <= 999 && count($tokens) > 1) {
            return [(int) $candidate, array_values(array_slice($tokens, 1))];
        }

        return [null, array_values($tokens)];
    }

    private function isCandidateNameLine(string $text): bool
    {
        $lower = mb_strtolower($text);
        foreach (['battle record', 'damage points', 'during this attack', 'raging bear', 'ranking'] as $header) {
            if (str_contains($lower, $header)) {
                return false;
            }
        }

        return preg_match('/[\p{L}\p{S}]/u', $text) === 1 && mb_strlen($text) <= 96;
    }

    private function normalizeDamage(string $value): ?int
    {
        $compact = strtoupper(str_replace([' ', ',', ':'], '', trim($value)));
        if (preg_match('/^(\d+(?:\.\d+)?)([KMB])?$/', $compact, $match) !== 1) {
            return null;
        }
        $multiplier = match ($match[2] ?? '') {
            'K' => 1_000,
            'M' => 1_000_000,
            'B' => 1_000_000_000,
            default => 1,
        };
        $damage = (float) $match[1] * $multiplier;
        if (! is_finite($damage) || $damage < 0 || $damage > PHP_INT_MAX) {
            return null;
        }

        return (int) round($damage);
    }

    /** @param  non-empty-list<OcrToken>  $tokens */
    private function candidate(string $key, int $ordinal, array $tokens, string $normalized, string $type): ExtractedFieldCandidate
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
            rawText: implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $tokens)),
            normalizedValue: $normalized,
            dataType: $type,
            confidence: max(0.0, min(1.0, $confidenceTotal / count($tokens))),
            boundingBox: ['left' => $left, 'top' => $top, 'width' => $right - $left, 'height' => $bottom - $top],
        );
    }
}
