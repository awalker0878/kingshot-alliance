<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Services\GovernorProgressionEvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Services\GovernorProgressionEvidenceSchemaRegistry;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GovernorProgressionEvidenceSchemasV3Test extends TestCase
{
    #[DataProvider('schemaCases')]
    public function test_registry_defines_each_supported_governor_progression_schema(
        EvidenceKind $kind,
        string $version,
        string $fixtureCorpus,
        string $destinationAction,
    ): void {
        $schema = (new GovernorProgressionEvidenceSchemaRegistry)->require($kind);

        self::assertSame($kind, $schema->kind);
        self::assertSame($version, $schema->version);
        self::assertSame($fixtureCorpus, $schema->fixtureCorpus);
        self::assertSame($destinationAction, $schema->destinationAction);
        self::assertGreaterThan(0.0, $schema->minimumClassificationConfidence);
        self::assertGreaterThan(0.0, $schema->minimumFieldConfidence);
        self::assertSame($schema->supportedFields, array_values(array_unique($schema->supportedFields)));
        foreach ($schema->requiredFields as $requiredField) {
            self::assertContains($requiredField, $schema->supportedFields);
        }
    }

    /**
     * @return iterable<string,array{0:EvidenceKind,1:string,2:string,3:string}>
     */
    public static function schemaCases(): iterable
    {
        yield 'governor profile' => [
            EvidenceKind::GovernorProfile,
            'governor-profile/1',
            'governor-profile-v1',
            'RecordGovernorProfileEvidence',
        ];
        yield 'hero roster' => [
            EvidenceKind::GovernorHeroRoster,
            'governor-hero-roster/1',
            'governor-hero-roster-v1',
            'RecordHeroRosterEvidence',
        ];
        yield 'hero detail' => [
            EvidenceKind::GovernorHeroDetail,
            'governor-hero-detail/1',
            'governor-hero-detail-v1',
            'RecordHeroDetailEvidence',
        ];
        yield 'hero gear' => [
            EvidenceKind::GovernorHeroGear,
            'governor-hero-gear/1',
            'governor-hero-gear-v1',
            'RecordHeroGearEvidence',
        ];
        yield 'governor gear' => [
            EvidenceKind::GovernorGear,
            'governor-gear/1',
            'governor-gear-v1',
            'RecordGovernorGearEvidence',
        ];
        yield 'governor charms' => [
            EvidenceKind::GovernorCharms,
            'governor-charms/1',
            'governor-charms-v1',
            'RecordGovernorCharmsEvidence',
        ];
    }

    #[DataProvider('classificationCases')]
    public function test_classifier_identifies_each_supported_governor_progression_schema(
        EvidenceKind $kind,
        array $lines,
    ): void {
        $decision = (new GovernorProgressionEvidenceClassifier)->classify($kind, $this->document($lines));

        self::assertSame($kind, $decision->kind);
        self::assertGreaterThanOrEqual(0.60, $decision->confidence);
    }

    /**
     * @return iterable<string,array{0:EvidenceKind,1:list<list<string>>}>
     */
    public static function classificationCases(): iterable
    {
        yield 'governor profile' => [
            EvidenceKind::GovernorProfile,
            [['Governor', 'Profile'], ['Governor', 'Power', '45,000,000'], ['Kingdom', '#123']],
        ];
        yield 'hero roster' => [
            EvidenceKind::GovernorHeroRoster,
            [['Hero', 'Roster'], ['Amadeus', 'Level', '80'], ['Helga', 'Level', '79']],
        ];
        yield 'hero detail' => [
            EvidenceKind::GovernorHeroDetail,
            [['Hero', 'Detail'], ['Amadeus'], ['Widget', '6'], ['Stars', '5']],
        ];
        yield 'hero gear' => [
            EvidenceKind::GovernorHeroGear,
            [['Hero', 'Gear'], ['Amadeus'], ['Mastery', '10'], ['Helmet']],
        ];
        yield 'governor gear' => [
            EvidenceKind::GovernorGear,
            [['Governor', 'Gear'], ['Gear', 'Power', '1,000,000']],
        ];
        yield 'governor charms' => [
            EvidenceKind::GovernorCharms,
            [['Governor', 'Charms'], ['Charm', 'Level', '8'], ['Charm', 'Guide']],
        ];
    }

    public function test_selected_expected_class_is_not_trusted_when_classifier_detects_another_class(): void
    {
        $decision = (new GovernorProgressionEvidenceClassifier)->classify(
            EvidenceKind::GovernorHeroRoster,
            $this->document([['Governor', 'Gear'], ['Gear', 'Power', '1,000,000']]),
        );

        self::assertSame(EvidenceKind::GovernorGear, $decision->kind);
    }

    public function test_ambiguous_or_unstructured_screenshot_fails_closed(): void
    {
        $decision = (new GovernorProgressionEvidenceClassifier)->classify(
            EvidenceKind::GovernorHeroDetail,
            $this->document([['KingShot'], ['Governor'], ['12345']]),
        );

        self::assertSame(EvidenceKind::Unknown, $decision->kind);
    }

    /**
     * @param  list<list<string>>  $lines
     */
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
