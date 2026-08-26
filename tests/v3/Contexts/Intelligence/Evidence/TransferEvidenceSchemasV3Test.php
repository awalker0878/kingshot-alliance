<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransferEvidenceSchemasV3Test extends TestCase
{
    #[DataProvider('classificationCases')]
    public function test_classifier_identifies_each_supported_schema(EvidenceKind $kind, array $lines): void
    {
        $decision = (new TransferEvidenceClassifier)->classify($kind, $this->document($lines));

        self::assertSame($kind, $decision->kind);
        self::assertGreaterThanOrEqual(0.55, $decision->confidence);
    }

    /** @return iterable<string,array{0:EvidenceKind,1:list<list<string>>}> */
    public static function classificationCases(): iterable
    {
        yield 'governor status' => [EvidenceKind::TransferGovernorStatus, [
            ['Transfer', 'Governor', 'Status'], ['Governor', 'Power', '12,345,678'],
        ]];
        yield 'score and passes' => [EvidenceKind::TransferScorePasses, [
            ['Transfer', 'Score', '8,765,432'], ['Passes', 'Available', '9'], ['Passes', 'Required', '12'],
        ]];
        yield 'invitation' => [EvidenceKind::TransferInvitation, [
            ['Special', 'Invite', 'Approved'], ['Kingdom', '#321'],
        ]];
        yield 'target rules' => [EvidenceKind::TransferTargetKingdomRules, [
            ['Kingdom', '#654'], ['Power', 'Cap', '90,000,000'], ['Ordinary', 'Kingdom'],
        ]];
        yield 'official group' => [EvidenceKind::TransferOfficialGroup, [
            ['Transfer', 'Group', 'A'], ['Kingdom', '#101'], ['Kingdom', '#102'], ['Kingdom', '#103'],
        ]];
    }

    public function test_selected_expected_class_is_not_trusted_when_classifier_detects_another_class(): void
    {
        $decision = (new TransferEvidenceClassifier)->classify(
            EvidenceKind::TransferScorePasses,
            $this->document([['Governor', 'Power', '42,000,000']]),
        );

        self::assertSame(EvidenceKind::TransferGovernorStatus, $decision->kind);
    }

    public function test_generic_transfer_screen_is_not_treated_as_supported_or_in_game_rules_verified(): void
    {
        $document = $this->document([
            ['Kingdom', 'Transfer'],
            ['Transfer', 'available', 'soon'],
            ['Governor', 'information'],
        ]);
        $decision = (new TransferEvidenceClassifier)->classify(
            EvidenceKind::TransferGovernorStatus,
            $document,
        );

        self::assertSame(EvidenceKind::Unknown, $decision->kind);
        foreach ($this->extractors() as [$kind, $extractor]) {
            $keys = array_map(
                static fn (ExtractedFieldCandidate $field): string => $field->fieldKey,
                $extractor->extract($kind, $document),
            );
            self::assertNotContains('in_game_rules_verified', $keys);
        }
    }

    public function test_score_pass_extractor_never_calculates_missing_required_passes(): void
    {
        $fields = (new TransferScorePassesExtractor)->extract(
            EvidenceKind::TransferScorePasses,
            $this->document([
                ['Transfer', 'Score', '9,000,000'],
                ['Passes', 'Available', '7'],
                ['A', 'nearby', 'unrelated', 'number', '99'],
            ]),
        );
        $values = $this->byKey($fields);

        self::assertSame('9000000', $values['transfer_score']->normalizedValue);
        self::assertSame('7', $values['transfer_passes_available']->normalizedValue);
        self::assertArrayNotHasKey('transfer_passes_required', $values);
    }

    public function test_governor_status_extracts_only_explicit_governor_power_and_not_power_cap(): void
    {
        $fields = (new TransferGovernorStatusExtractor)->extract(
            EvidenceKind::TransferGovernorStatus,
            $this->document([
                ['Power', 'Cap', '80,000,000'],
                ['Governor', 'Power', '72,345,678'],
                ['Transfer', 'Score', '5,000,000'],
            ]),
        );
        $values = $this->byKey($fields);

        self::assertSame(['governor_power'], array_keys($values));
        self::assertSame('72345678', $values['governor_power']->normalizedValue);
    }

    public function test_invitation_extractor_normalizes_only_owner_enum_phrases(): void
    {
        $extractor = new TransferInvitationExtractor;
        $supported = $this->byKey($extractor->extract(
            EvidenceKind::TransferInvitation,
            $this->document([['Special', 'Invitation', 'Pending'], ['Kingdom', '#222']]),
        ));
        self::assertSame('special_pending', $supported['invitation_status']->normalizedValue);
        self::assertSame('222', $supported['target_kingdom_number']->normalizedValue);

        $unsupported = $this->byKey($extractor->extract(
            EvidenceKind::TransferInvitation,
            $this->document([['Invitation', 'might', 'be', 'available'], ['Kingdom', '#222']]),
        ));
        self::assertArrayNotHasKey('invitation_status', $unsupported);
    }

    public function test_target_rules_extract_only_fixture_proven_target_fields(): void
    {
        $values = $this->byKey((new TransferTargetKingdomRulesExtractor)->extract(
            EvidenceKind::TransferTargetKingdomRules,
            $this->document([
                ['Kingdom', '#777'],
                ['Power', 'Cap', '88,000,000'],
                ['Leading', 'Kingdom'],
                ['Governor', 'Power', '12,000,000'],
            ]),
        ));

        self::assertSame(['target_kingdom_number', 'power_cap', 'kingdom_classification'], array_keys($values));
        self::assertSame('777', $values['target_kingdom_number']->normalizedValue);
        self::assertSame('88000000', $values['power_cap']->normalizedValue);
        self::assertSame('leading', $values['kingdom_classification']->normalizedValue);
    }

    public function test_official_group_membership_is_unique_and_sorted_from_visible_kingdoms_only(): void
    {
        $fields = (new TransferOfficialGroupExtractor)->extract(
            EvidenceKind::TransferOfficialGroup,
            $this->document([
                ['Transfer', 'Group', 'B'],
                ['Kingdom', '#303'],
                ['Kingdom', '#101'],
                ['Kingdom', '#303'],
                ['Kingdom', '#202'],
            ]),
        );
        $group = array_values(array_filter($fields, static fn (ExtractedFieldCandidate $field): bool => $field->fieldKey === 'official_group_identifier'));
        $kingdoms = array_values(array_filter($fields, static fn (ExtractedFieldCandidate $field): bool => $field->fieldKey === 'kingdom_number'));

        self::assertCount(1, $group);
        self::assertSame('B', $group[0]->normalizedValue);
        self::assertSame(['101', '202', '303'], array_map(static fn (ExtractedFieldCandidate $field): string => $field->normalizedValue, $kingdoms));
    }

    public function test_registry_contract_is_explicit_for_every_schema_and_forbids_unproven_verification_field(): void
    {
        $registry = new TransferEvidenceSchemaRegistry;
        $expectedActions = [
            EvidenceKind::TransferGovernorStatus->value => 'RecordGovernorStatusEvidence',
            EvidenceKind::TransferScorePasses->value => 'RecordTransferScorePassEvidence',
            EvidenceKind::TransferInvitation->value => 'RecordTransferInvitationEvidence',
            EvidenceKind::TransferTargetKingdomRules->value => 'RecordTransferKingdomRulesEvidence',
            EvidenceKind::TransferOfficialGroup->value => 'RecordOfficialTransferGroupEvidence',
        ];

        foreach (EvidenceKind::transferCases() as $kind) {
            $schema = $registry->require($kind);
            self::assertStringEndsWith('/1', $schema->version);
            self::assertNotEmpty($schema->requiredFields);
            self::assertNotEmpty($schema->fixtureCorpus);
            self::assertSame($expectedActions[$kind->value], $schema->destinationAction);
            self::assertNotContains('in_game_rules_verified', $schema->supportedFields);
            foreach ($schema->requiredFields as $field) {
                self::assertContains($field, $schema->supportedFields);
            }
        }
    }

    public function test_fixture_corpus_manifest_exists_for_every_registered_schema(): void
    {
        $registry = new TransferEvidenceSchemaRegistry;
        foreach (EvidenceKind::transferCases() as $kind) {
            $schema = $registry->require($kind);
            $path = dirname(__DIR__, 4).'/Fixtures/Evidence/Transfer/'.$schema->fixtureCorpus.'.json';
            self::assertFileExists($path, $schema->fixtureCorpus);
            $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($schema->version, $fixture['schema_version'] ?? null);
            self::assertNotEmpty($fixture['positive'] ?? []);
            self::assertNotEmpty($fixture['negative'] ?? []);
            self::assertNotEmpty($fixture['ambiguity'] ?? []);
            self::assertNotEmpty($fixture['semantic_duplicate'] ?? []);
            self::assertNotEmpty($fixture['semantic_newer'] ?? []);
        }
    }

    /** @return list<array{0:EvidenceKind,1:object}> */
    private function extractors(): array
    {
        return [
            [EvidenceKind::TransferGovernorStatus, new TransferGovernorStatusExtractor],
            [EvidenceKind::TransferScorePasses, new TransferScorePassesExtractor],
            [EvidenceKind::TransferInvitation, new TransferInvitationExtractor],
            [EvidenceKind::TransferTargetKingdomRules, new TransferTargetKingdomRulesExtractor],
            [EvidenceKind::TransferOfficialGroup, new TransferOfficialGroupExtractor],
        ];
    }

    /** @param list<ExtractedFieldCandidate> $fields @return array<string,ExtractedFieldCandidate> */
    private function byKey(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field->fieldKey] = $field;
        }

        return $result;
    }

    /** @param list<list<string>> $lines */
    private function document(array $lines): OcrDocument
    {
        $tokens = [];
        foreach ($lines as $lineNumber => $words) {
            foreach ($words as $index => $word) {
                $tokens[] = new OcrToken(
                    text: $word,
                    confidence: 0.93 - min(0.12, $index * 0.01),
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

        return new OcrDocument('fixture', '1', 'eng', $tokens);
    }
}
