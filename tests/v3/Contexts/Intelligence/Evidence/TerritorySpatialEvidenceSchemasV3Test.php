<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Services\TerritoryEvidenceSchemaRegistry;
use App\Contexts\Intelligence\Evidence\Services\TerritoryMapObservationEvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Services\TerritoryMapObservationExtractor;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use PHPUnit\Framework\TestCase;

final class TerritorySpatialEvidenceSchemasV3Test extends TestCase
{
    public function test_registry_exposes_one_closed_territory_observation_schema(): void
    {
        $schema = (new TerritoryEvidenceSchemaRegistry)->require(EvidenceKind::TerritoryMapObservation);

        self::assertSame(EvidenceKind::TerritoryMapObservation, $schema->kind);
        self::assertSame('territory-map-observation/1', $schema->version);
        self::assertSame('territory-map-observation-v1', $schema->fixtureCorpus);
        self::assertSame('RecordSpatialObservationEvidence', $schema->destinationAction);
        self::assertSame([
            'headquarters_coordinate',
            'bear_trap_coordinate',
            'banner_coordinate',
            'governor_city_coordinate',
            'observed_label',
            'visible_region_bounds',
            'source_timestamp',
        ], $schema->supportedFields);
    }

    public function test_classifier_requires_map_coordinate_and_supported_object_signals(): void
    {
        $classifier = new TerritoryMapObservationEvidenceClassifier;

        $supported = $classifier->classify(
            EvidenceKind::TerritoryMapObservation,
            $this->document([
                ['Alliance', 'Map'],
                ['Headquarters', 'X:100', 'Y:200'],
                ['Governor', 'City', 'X:110', 'Y:205'],
            ]),
        );
        self::assertSame(EvidenceKind::TerritoryMapObservation, $supported->kind);
        self::assertGreaterThanOrEqual(0.60, $supported->confidence);

        $unsupported = $classifier->classify(
            EvidenceKind::TerritoryMapObservation,
            $this->document([['KingShot'], ['Governor'], ['Power', '1000000']]),
        );
        self::assertSame(EvidenceKind::Unknown, $unsupported->kind);
    }

    public function test_extractor_emits_only_explicit_supported_coordinate_candidates(): void
    {
        $candidates = (new TerritoryMapObservationExtractor)->extract(
            EvidenceKind::TerritoryMapObservation,
            $this->document([
                ['Alliance', 'Map'],
                ['Alliance', 'Headquarters', 'X:100', 'Y:200'],
                ['Bear', 'Trap', 'X=120', 'Y=220'],
                ['Banner', 'North', 'X:130', 'Y:230'],
                ['Governor', 'Alpha', 'City', 'X:140', 'Y:240'],
                ['Governor', 'Beta', 'Power', '99,999,999'],
            ]),
        );

        self::assertCount(4, $candidates);
        self::assertSame([
            'headquarters_coordinate',
            'bear_trap_coordinate',
            'banner_coordinate',
            'governor_city_coordinate',
        ], array_map(static fn ($candidate): string => $candidate->fieldKey, $candidates));

        $governor = $candidates[3];
        $normalized = json_decode($governor->normalizedValue, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(140, $normalized['x']);
        self::assertSame(240, $normalized['y']);
        self::assertStringContainsString('Governor Alpha City', (string) $normalized['label']);
    }

    /** @param  list<list<string>>  $lines */
    private function document(array $lines): OcrDocument
    {
        $tokens = [];
        foreach ($lines as $lineIndex => $line) {
            foreach ($line as $wordIndex => $text) {
                $tokens[] = new OcrToken(
                    text: $text,
                    confidence: 0.99,
                    page: 1,
                    block: 1,
                    paragraph: 1,
                    line: $lineIndex + 1,
                    word: $wordIndex + 1,
                    left: $wordIndex * 100,
                    top: $lineIndex * 40,
                    width: 80,
                    height: 20,
                );
            }
        }

        return new OcrDocument('fixture', '1', 'en', $tokens);
    }
}
