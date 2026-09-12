<?php

declare(strict_types=1);

namespace Tests\Contexts\Intelligence\Evidence\Feature;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class RoutedEvidenceExtractorV3Test extends TestCase
{
    public function test_every_kind_has_an_explicit_routing_and_support_contract(): void
    {
        $extractor = app(EvidenceExtractor::class);
        foreach (EvidenceKind::cases() as $kind) {
            $supported = ! in_array($kind, [EvidenceKind::Unknown, EvidenceKind::AllianceRoster], true);
            self::assertSame($supported, $extractor->supports($kind), $kind->value);
            if ($supported) {
                self::assertNotSame('', $extractor->key($kind));
                self::assertNotSame('', $extractor->version($kind));
                self::assertNotSame('', $extractor->schemaVersion($kind));
                self::assertIsArray($extractor->extract($kind, new OcrDocument('fixture', '1', 'en', [])));
            }
        }
    }

    #[DataProvider('unsupportedKinds')]
    public function test_unsupported_kinds_raise_the_explicit_domain_failure(EvidenceKind $kind): void
    {
        $this->expectException(RuntimeException::class);
        app(EvidenceExtractor::class)->extract($kind, new OcrDocument('fixture', '1', 'en', []));
    }

    public static function unsupportedKinds(): iterable
    {
        yield [EvidenceKind::Unknown];
        yield [EvidenceKind::AllianceRoster];
    }

    #[DataProvider('structuredScreens')]
    public function test_structured_screens_classify_independently_and_route_observed_fields(
        EvidenceKind $kind, string $heading, string $label, string $nameField, string $levelField,
    ): void {
        $document = $this->document([$heading, $label.': First Subject Level 2 Power 900', $label.': Second Subject']);
        $decision = app(EvidenceClassifier::class)->classify(EvidenceKind::GovernorProfile, $document);
        self::assertSame($kind, $decision->kind);

        $fields = app(EvidenceExtractor::class)->extract($decision->kind, $document);
        self::assertSame([$nameField, $levelField, $nameField], array_column($fields, 'fieldKey'));
        self::assertSame(['First Subject', '2', 'Second Subject'], array_column($fields, 'normalizedValue'));
        self::assertSame([0, 0, 1], array_column($fields, 'rowOrdinal'));
        self::assertSame('integer', $fields[1]->dataType);
        self::assertGreaterThan(0, $fields[0]->boundingBox['height']);
    }

    public static function structuredScreens(): iterable
    {
        yield 'buildings' => [EvidenceKind::GovernorBuildings, 'Governor Buildings', 'Building', 'building_name', 'building_level'];
        yield 'academy' => [EvidenceKind::GovernorAcademyResearch, 'Academy Research', 'Technology', 'technology_name', 'research_level'];
        yield 'war academy' => [EvidenceKind::GovernorWarAcademyResearch, 'War Academy Research', 'Technology', 'technology_name', 'research_level'];
    }

    public function test_two_research_headings_are_ambiguous_and_selected_kind_does_not_break_the_tie(): void
    {
        $decision = app(EvidenceClassifier::class)->classify(
            EvidenceKind::GovernorAcademyResearch,
            $this->document(['Academy Research', 'War Academy Research', 'Technology: Unknown Level 1']),
        );
        self::assertSame(EvidenceKind::Unknown, $decision->kind);
    }

    /** @param list<string> $lines */
    private function document(array $lines): OcrDocument
    {
        $tokens = [];
        foreach ($lines as $line => $text) {
            $tokens[] = new OcrToken($text, 0.95, 1, 1, 1, $line + 1, 1, 0, $line * 30, 300, 20);
        }

        return new OcrDocument('synthetic-contract-fixture', '1', 'en', $tokens);
    }
}
