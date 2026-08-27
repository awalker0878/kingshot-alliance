<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Roster;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\Services\GovernorProgressionObservationValidator;
use Illuminate\Validation\ValidationException;
use Tests\v3\TestCase;

final class GovernorProgressionCharmIdentityBoundaryV3Test extends TestCase
{
    public function test_current_charm_schema_accepts_only_catalogue_provable_slot_and_level_meaning(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        $payload = app(GovernorProgressionObservationValidator::class)->validate(
            EvidenceKind::GovernorCharms,
            [
                'charms' => [
                    ['slot_id' => 'weapon', 'level' => 5],
                    ['slot_id' => 'armor', 'level' => 4],
                ],
            ],
            $dataset->id,
            $dataset->checksum,
        );

        self::assertSame([
            'charms' => [
                ['slot_id' => 'armor', 'level' => 4],
                ['slot_id' => 'weapon', 'level' => 5],
            ],
        ], $payload);
    }

    public function test_current_charm_schema_rejects_a_synthetic_canonical_identity(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unsupported reviewed field(s): charm_id.');

        app(GovernorProgressionObservationValidator::class)->validate(
            EvidenceKind::GovernorCharms,
            [
                'charms' => [
                    [
                        'slot_id' => 'weapon',
                        'charm_id' => 'glory-seal',
                        'level' => 5,
                    ],
                ],
            ],
            $dataset->id,
            $dataset->checksum,
        );
    }
}
