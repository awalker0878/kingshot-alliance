<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ExtractedFieldCandidate;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use InvalidArgumentException;

final class TerritoryMapObservationExtractor implements EvidenceExtractor
{
    public function key(EvidenceKind $kind): string
    {
        $this->assertKind($kind);
        return 'territory-map-observation-v1';
    }

    public function version(EvidenceKind $kind): string
    {
        $this->assertKind($kind);
        return '1.0.0';
    }

    public function schemaVersion(EvidenceKind $kind): string
    {
        $this->assertKind($kind);
        return 'territory-map-observation/1';
    }

    public function supports(EvidenceKind $kind): bool
    {
        return $kind === EvidenceKind::TerritoryMapObservation;
    }

    public function extract(EvidenceKind $kind, OcrDocument $document): array
    {
        $this->assertKind($kind);
        $candidates = [];
        $ordinalByField = [];
        foreach ($document->lines() as $line) {
            if ($line === []) {
                continue;
            }
            $raw = trim(implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $line)));
            if (preg_match('/\bx\s*[:=]?\s*(\d{1,4})\b.*\by\s*[:=]?\s*(\d{1,4})\b/i', $raw, $match) !== 1) {
                continue;
            }
            $field = $this->fieldForLine(mb_strtolower($raw));
            if ($field === null) {
                continue;
            }
            $ordinal = $ordinalByField[$field] ?? 0;
            $ordinalByField[$field] = $ordinal + 1;
            $candidates[] = $this->candidate($field, $ordinal, $line, json_encode([
                'x' => (int) $match[1],
                'y' => (int) $match[2],
                'label' => $this->label($raw),
            ], JSON_THROW_ON_ERROR));
        }

        return $candidates;
    }

    private function fieldForLine(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'headquarters'), preg_match('/\bhq\b/', $text) === 1 => 'headquarters_coordinate',
            str_contains($text, 'bear trap'), preg_match('/\bbear\b/', $text) === 1 => 'bear_trap_coordinate',
            str_contains($text, 'banner') => 'banner_coordinate',
            str_contains($text, 'governor'), str_contains($text, 'city') => 'governor_city_coordinate',
            default => null,
        };
    }

    private function label(string $raw): ?string
    {
        $label = preg_replace('/\bx\s*[:=]?\s*\d{1,4}\b.*$/i', '', $raw);
        $label = is_string($label) ? trim($label, " \t:-") : '';
        return $label === '' ? null : mb_substr($label, 0, 191);
    }

    /** @param non-empty-list<OcrToken> $tokens */
    private function candidate(string $key, int $ordinal, array $tokens, string $normalized): ExtractedFieldCandidate
    {
        $confidence = 0.0;
        $left = $tokens[0]->left;
        $top = $tokens[0]->top;
        $right = $tokens[0]->left + $tokens[0]->width;
        $bottom = $tokens[0]->top + $tokens[0]->height;
        foreach ($tokens as $token) {
            $confidence += $token->confidence;
            $left = min($left, $token->left);
            $top = min($top, $token->top);
            $right = max($right, $token->left + $token->width);
            $bottom = max($bottom, $token->top + $token->height);
        }
        return new ExtractedFieldCandidate(
            fieldKey: $key,
            rowOrdinal: $ordinal,
            rawText: trim(implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $tokens))),
            normalizedValue: $normalized,
            dataType: 'coordinate',
            confidence: max(0.0, min(1.0, $confidence / count($tokens))),
            boundingBox: ['left' => $left, 'top' => $top, 'width' => $right - $left, 'height' => $bottom - $top],
        );
    }

    private function assertKind(EvidenceKind $kind): void
    {
        if (! $this->supports($kind)) {
            throw new InvalidArgumentException('Territory map extractor received an unsupported Evidence kind.');
        }
    }
}
