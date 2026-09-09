<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\Intelligence\Roster;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\Services\GovernorProgressionObservationValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class StructuredProgressionEvidenceContractV3Test extends TestCase
{
    #[DataProvider('structuredKinds')]
    public function test_reviewed_names_resolve_only_to_existing_pinned_states(EvidenceKind $kind, string $label, string $id): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $validator = app(GovernorProgressionObservationValidator::class);
        self::assertSame(
            ['states' => [['subject_id' => $id, 'state_id' => 'level:1', 'level' => 1]]],
            $validator->validate($kind, ['states' => [['subject_id' => $label, 'level' => 1]]], $dataset->id, $dataset->checksum),
        );

        foreach ([
            'missing level' => [['subject_id' => $id]],
            'invalid level' => [['subject_id' => $id, 'level' => 999]],
            'mismatched state' => [['subject_id' => $id, 'level' => 1, 'state_id' => 'level:2']],
            'invented identity' => [['subject_id' => 'not-a-canonical-subject', 'level' => 1]],
            'duplicate identity' => [['subject_id' => $id, 'level' => 1], ['subject_id' => $label, 'level' => 2]],
        ] as $case => $rows) {
            try {
                $validator->validate($kind, ['states' => $rows], $dataset->id, $dataset->checksum);
                self::fail($case.' must not create a reviewed factual state.');
            } catch (ValidationException $exception) {
                self::assertNotEmpty($exception->errors(), $case);
            }
        }
    }

    public static function structuredKinds(): iterable
    {
        yield 'buildings' => [EvidenceKind::GovernorBuildings, 'Academy', 'academy'];
        yield 'academy' => [EvidenceKind::GovernorAcademyResearch, 'Tool Enhancement I', 'development-tool-enhancement-i'];
        yield 'war academy' => [EvidenceKind::GovernorWarAcademyResearch, 'Truegold Battalion', 'truegold-battalion'];
    }
}
