<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Services\TransferEvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Services\TransferEvidenceSchemaRegistry;
use App\Contexts\Intelligence\Evidence\Services\TransferGovernorStatusExtractor;
use App\Contexts\Intelligence\Evidence\Services\TransferInvitationExtractor;
use App\Contexts\Intelligence\Evidence\Services\TransferOfficialGroupExtractor;
use App\Contexts\Intelligence\Evidence\Services\TransferScorePassesExtractor;
use App\Contexts\Intelligence\Evidence\Services\TransferTargetKingdomRulesExtractor;
use App\Contexts\Intelligence\Evidence\ValueObjects\ExtractedFieldCandidate;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use App\Contexts\Intelligence\Evidence\ValueObjects\TransferEvidenceSchema;
use PHPUnit\Framework\TestCase;

final class TransferEvidenceFixtureCorpusV3Test extends TestCase
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

    public function test_every_transfer_schema_executes_the_complete_fixture_family_contract(): void
    {
        $registry = new TransferEvidenceSchemaRegistry;
        $classifier = new TransferEvidenceClassifier;

        foreach (EvidenceKind::transferCases() as $kind) {
            $schema = $registry->require($kind);
            $fixture = $this->fixture($schema);
            $extractor = $this->extractor($kind);
            $cases = $fixture['coverage_cases'];
            $categories = array_column($cases, 'category');
            sort($categories);
            $required = self::REQUIRED_CATEGORIES;
            sort($required);
            self::assertSame($required, $categories, $schema->fixtureCorpus.' fixture categories');

            $byName = [];
            foreach ($cases as $case) {
                $byName[$case['name']] = $case;
                $document = $this->document(
                    $case['lines'],
                    isset($case['ocr_confidence']) ? (float) $case['ocr_confidence'] : 0.93,
                );
                $expectedKind = $case['expected_kind'] === 'unknown'
                    ? EvidenceKind::Unknown
                    : EvidenceKind::from($case['expected_kind']);
                $decision = $classifier->classify($kind, $document);
                self::assertSame(
                    $expectedKind,
                    $decision->kind,
                    $schema->fixtureCorpus.':'.$case['name'].':classification',
                );

                $fields = $extractor->extract($kind, $document);
                $this->assertFields($case['fields'], $fields, $schema->fixtureCorpus.':'.$case['name']);
                foreach ($fields as $field) {
                    self::assertContains(
                        $field->fieldKey,
                        $schema->supportedFields,
                        $schema->fixtureCorpus.':'.$case['name'].':allowlist',
                    );
                }
                foreach ($case['forbidden_fields'] ?? [] as $fieldKey) {
                    self::assertNotContains(
                        $fieldKey,
                        array_map(static fn (ExtractedFieldCandidate $field): string => $field->fieldKey, $fields),
                        $schema->fixtureCorpus.':'.$case['name'].':forbidden field',
                    );
                }
                foreach ($case['forbidden_kingdom_numbers'] ?? [] as $number) {
                    self::assertNotContains(
                        (int) $number,
                        $this->kingdomNumbers($fields),
                        $schema->fixtureCorpus.':'.$case['name'].':forbidden Kingdom',
                    );
                }
                if (($case['expect_below_field_threshold'] ?? false) === true) {
                    self::assertNotEmpty($fields, $schema->fixtureCorpus.':'.$case['name'].':low confidence candidate');
                    self::assertLessThan(
                        $schema->minimumFieldConfidence,
                        min(array_map(static fn (ExtractedFieldCandidate $field): float => $field->confidence, $fields)),
                        $schema->fixtureCorpus.':'.$case['name'].':field threshold',
                    );
                }
            }

            $visual = $this->caseByCategory($cases, 'visual_duplicate_recompressed');
            $canonical = $byName[$visual['visual_duplicate_of']] ?? null;
            self::assertIsArray($canonical, $schema->fixtureCorpus.':visual duplicate reference');
            self::assertSame($canonical['fields'], $visual['fields'], $schema->fixtureCorpus.':visual meaning');

            $equal = $this->caseByCategory($cases, 'semantic_equal');
            $newer = $this->caseByCategory($cases, 'semantic_newer');
            self::assertNotSame(
                $equal['semantic']['observed_at'] ?? null,
                $newer['semantic']['observed_at'] ?? null,
                $schema->fixtureCorpus.':newer observation boundary',
            );
        }
    }

    /**
     * @return array{coverage_cases:list<array<string,mixed>>}
     */
    private function fixture(TransferEvidenceSchema $schema): array
    {
        $path = dirname(__DIR__, 4).'/Fixtures/Evidence/Transfer/'.$schema->fixtureCorpus.'.json';
        self::assertFileExists($path);
        $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        self::assertSame($schema->version, $fixture['schema_version'] ?? null);
        self::assertIsArray($fixture['coverage_cases'] ?? null);

        /** @var array{coverage_cases:list<array<string,mixed>>} $fixture */
        return $fixture;
    }

    /** @param list<array<string,mixed>> $cases */
    private function caseByCategory(array $cases, string $category): array
    {
        foreach ($cases as $case) {
            if (($case['category'] ?? null) === $category) {
                return $case;
            }
        }

        self::fail('Missing fixture category '.$category);
    }

    private function extractor(EvidenceKind $kind): EvidenceExtractor
    {
        return match ($kind) {
            EvidenceKind::TransferGovernorStatus => new TransferGovernorStatusExtractor,
            EvidenceKind::TransferScorePasses => new TransferScorePassesExtractor,
            EvidenceKind::TransferInvitation => new TransferInvitationExtractor,
            EvidenceKind::TransferTargetKingdomRules => new TransferTargetKingdomRulesExtractor,
            EvidenceKind::TransferOfficialGroup => new TransferOfficialGroupExtractor,
            default => self::fail('Unsupported Transfer Evidence fixture kind.'),
        };
    }

    /**
     * @param array<string,mixed> $expected
     * @param list<ExtractedFieldCandidate> $fields
     */
    private function assertFields(array $expected, array $fields, string $case): void
    {
        $byKey = [];
        foreach ($fields as $field) {
            $byKey[$field->fieldKey][] = $field;
        }

        foreach ($expected as $key => $value) {
            if ($key === 'kingdom_numbers') {
                $expectedNumbers = array_map('intval', is_array($value) ? $value : []);
                sort($expectedNumbers);
                self::assertSame($expectedNumbers, $this->kingdomNumbers($fields), $case.':kingdom_numbers');

                continue;
            }

            self::assertArrayHasKey($key, $byKey, $case.':'.$key);
            self::assertSame((string) $value, $byKey[$key][0]->normalizedValue, $case.':'.$key);
        }
    }

    /** @param list<ExtractedFieldCandidate> $fields @return list<int> */
    private function kingdomNumbers(array $fields): array
    {
        $numbers = array_map(
            static fn (ExtractedFieldCandidate $field): int => (int) $field->normalizedValue,
            array_values(array_filter(
                $fields,
                static fn (ExtractedFieldCandidate $field): bool => $field->fieldKey === 'kingdom_number',
            )),
        );
        sort($numbers);

        return $numbers;
    }

    /** @param list<string> $lines */
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

        return new OcrDocument('transfer-fixture-corpus', '1', 'eng', $tokens);
    }
}
