<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Services\GovernorProgressionEvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Services\GovernorProgressionEvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Services\GovernorProgressionEvidenceSchemaRegistry;
use App\Contexts\Intelligence\Evidence\ValueObjects\ExtractedFieldCandidate;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use PHPUnit\Framework\TestCase;

final class GovernorProgressionEvidenceFixtureCorpusV3Test extends TestCase
{
    /** @var list<string> */
    private const REQUIRED_CATEGORIES = [
        'canonical',
        'alternate_resolution',
        'safe_crop',
        'numeric_grouping',
        'low_confidence',
        'adjacent_unrelated_numbers',
        'missing_required_field',
        'unsupported_ui_variant',
        'wrong_screenshot_class',
        'visual_duplicate_recompressed',
        'semantic_equal',
        'semantic_newer',
    ];

    public function test_every_governor_progression_schema_executes_its_fixture_corpus(): void
    {
        $registry = new GovernorProgressionEvidenceSchemaRegistry;
        $classifier = new GovernorProgressionEvidenceClassifier;
        $extractor = new GovernorProgressionEvidenceExtractor($registry);

        foreach (EvidenceKind::governorProgressionCases() as $kind) {
            $schema = $registry->require($kind);
            $fixture = $this->fixture($schema->fixtureCorpus, $schema->version);
            $cases = $fixture['coverage_cases'];
            $categories = array_column($cases, 'category');
            sort($categories);
            $required = self::REQUIRED_CATEGORIES;
            sort($required);
            self::assertSame($required, $categories, $schema->fixtureCorpus.' fixture categories');

            foreach ($cases as $case) {
                $document = $this->document(
                    $case['lines'],
                    isset($case['ocr_confidence']) ? (float) $case['ocr_confidence'] : 0.95,
                );
                $expectedKind = $case['expected_kind'] === 'unknown'
                    ? EvidenceKind::Unknown
                    : EvidenceKind::from((string) $case['expected_kind']);
                $decision = $classifier->classify($kind, $document);
                self::assertSame($expectedKind, $decision->kind, $schema->fixtureCorpus.':'.$case['name'].':classification');

                $fields = $extractor->extract($kind, $document);
                foreach ($fields as $field) {
                    self::assertContains($field->fieldKey, $schema->supportedFields, $schema->fixtureCorpus.':allowlist');
                }
                foreach ($case['forbidden_fields'] ?? [] as $fieldKey) {
                    self::assertNotContains(
                        $fieldKey,
                        array_map(static fn (ExtractedFieldCandidate $field): string => $field->fieldKey, $fields),
                        $schema->fixtureCorpus.':'.$case['name'].':forbidden',
                    );
                }

                if (($case['expect_below_field_threshold'] ?? false) === true) {
                    self::assertNotEmpty($fields, $schema->fixtureCorpus.':'.$case['name'].':low-confidence candidate');
                    self::assertLessThan(
                        $schema->minimumFieldConfidence,
                        min(array_map(static fn (ExtractedFieldCandidate $field): float => $field->confidence, $fields)),
                    );
                }

                $this->assertExpectedFields($case['fields'] ?? [], $fields, $schema->fixtureCorpus.':'.$case['name']);
            }

            $equal = $this->caseByCategory($cases, 'semantic_equal');
            $newer = $this->caseByCategory($cases, 'semantic_newer');
            self::assertNotSame($equal['semantic']['observed_at'], $newer['semantic']['observed_at']);
        }
    }

    /**
     * @return array{coverage_cases:list<array<string,mixed>>}
     */
    private function fixture(string $fixtureCorpus, string $schemaVersion): array
    {
        $path = dirname(__DIR__, 4).'/Fixtures/Evidence/GovernorProgression/'.$fixtureCorpus.'.json';
        self::assertFileExists($path);
        $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        self::assertSame($schemaVersion, $fixture['schema_version'] ?? null);
        self::assertIsArray($fixture['coverage_cases'] ?? null);

        /** @var array{coverage_cases:list<array<string,mixed>>} $fixture */
        return $fixture;
    }

    /**
     * @param  array<string,mixed>  $expected
     * @param  list<ExtractedFieldCandidate>  $fields
     */
    private function assertExpectedFields(array $expected, array $fields, string $case): void
    {
        $byKey = [];
        foreach ($fields as $field) {
            $byKey[$field->fieldKey][] = $field->normalizedValue;
        }

        foreach ($expected as $key => $value) {
            if ($key === 'hero_names') {
                self::assertSame($value, $byKey['hero_name'] ?? [], $case.':hero_names');
                continue;
            }
            if ($key === 'gear_slots') {
                self::assertSame($value, $byKey['gear_slot'] ?? [], $case.':gear_slots');
                continue;
            }
            if ($key === 'charm_slots') {
                self::assertSame($value, $byKey['charm_slot'] ?? [], $case.':charm_slots');
                continue;
            }
            self::assertArrayHasKey($key, $byKey, $case.':'.$key);
            self::assertSame((string) $value, (string) $byKey[$key][0], $case.':'.$key);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $cases
     * @return array<string,mixed>
     */
    private function caseByCategory(array $cases, string $category): array
    {
        foreach ($cases as $case) {
            if (($case['category'] ?? null) === $category) {
                return $case;
            }
        }

        self::fail('Missing fixture category '.$category);
    }

    /**
     * @param  list<string>  $lines
     */
    private function document(array $lines, float $confidence): OcrDocument
    {
        $tokens = [];
        foreach ($lines as $lineNumber => $line) {
            $words = array_values(array_filter(
                preg_split('/\s+/', trim($line)) ?: [],
                static fn (string $word): bool => $word !== '',
            ));
            foreach ($words as $index => $word) {
                $tokens[] = new OcrToken(
                    text: $word,
                    confidence: $confidence,
                    page: 1,
                    block: 1,
                    paragraph: 1,
                    line: $lineNumber + 1,
                    word: $index + 1,
                    left: 10 + ($index * 90),
                    top: 20 + ($lineNumber * 30),
                    width: 80,
                    height: 20,
                );
            }
        }

        return new OcrDocument('governor-progression-fixture-corpus', '1', 'eng', $tokens);
    }
}
