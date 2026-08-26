<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Services\BearHuntBattleReportExtractor;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use PHPUnit\Framework\TestCase;

final class BearHuntBattleReportExtractorV3Test extends TestCase
{
    public function test_extractor_starts_at_ranking_and_preserves_field_confidence(): void
    {
        $document = new OcrDocument(
            engine: 'fixture',
            engineVersion: '1',
            language: 'eng',
            tokens: [
                ...$this->line(1, ['Raging', 'Bear', 'Battle', 'Record'], 0.99),
                ...$this->line(2, ['2026-08-22', '13:05:23'], 0.98),
                ...$this->line(3, ['Damage', 'Points:', '9,999,999'], 0.97),
                ...$this->line(4, ['Ranking'], 0.99),
                ...$this->line(5, ['Randy'], 0.92),
                ...$this->line(6, ['Damage', 'Points:', '1,156,200'], 0.91),
                ...$this->line(7, ['2', 'Luna'], 0.88),
                ...$this->line(8, ['Damage', 'Points:', '427.171K'], 0.89),
            ],
        );

        $fields = (new BearHuntBattleReportExtractor)->extract(
            EvidenceKind::BearHuntBattleReport,
            $document,
        );
        $byKey = [];
        foreach ($fields as $field) {
            $byKey[$field->rowOrdinal][$field->fieldKey] = $field;
        }

        self::assertSame('2026-08-22 13:05:23', $byKey[0]['report_timestamp']->normalizedValue);
        self::assertSame('Randy', $byKey[1]['player_name']->normalizedValue);
        self::assertSame('1156200', $byKey[1]['damage']->normalizedValue);
        self::assertArrayNotHasKey('rank', $byKey[1]);
        self::assertSame('Luna', $byKey[2]['player_name']->normalizedValue);
        self::assertSame('2', $byKey[2]['rank']->normalizedValue);
        self::assertSame('427171', $byKey[2]['damage']->normalizedValue);
        self::assertEqualsWithDelta(0.91, $byKey[1]['damage']->confidence, 0.001);
        self::assertCount(6, $fields);
    }

    /** @param list<string> $words @return list<OcrToken> */
    private function line(int $line, array $words, float $confidence): array
    {
        $tokens = [];
        foreach ($words as $index => $word) {
            $tokens[] = new OcrToken(
                text: $word,
                confidence: $confidence,
                page: 1,
                block: 1,
                paragraph: 1,
                line: $line,
                word: $index + 1,
                left: 10 + ($index * 90),
                top: $line * 30,
                width: 80,
                height: 20,
            );
        }

        return $tokens;
    }
}
