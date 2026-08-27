<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Roster;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\Services\GovernorProgressionObservationValidator;
use Illuminate\Validation\ValidationException;
use Tests\v3\TestCase;

final class GovernorProgressionPinnedCatalogueValidationV3Test extends TestCase
{
    public function test_hero_gear_uses_pinned_enhancement_and_mastery_bounds(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $heroId = (string) ($dataset->heroes[0]['id'] ?? '');
        self::assertNotSame('', $heroId);

        $payload = app(GovernorProgressionObservationValidator::class)->validate(
            EvidenceKind::GovernorHeroGear,
            [
                'hero_id' => $heroId,
                'gear' => [[
                    'slot_id' => 'helmet',
                    'level' => 200,
                    'mastery_level' => 20,
                ]],
            ],
            $dataset->id,
            $dataset->checksum,
        );

        self::assertSame(200, $payload['gear'][0]['level']);
        self::assertSame(20, $payload['gear'][0]['mastery_level']);
    }

    public function test_hero_gear_rejects_values_beyond_the_pinned_ladders(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $heroId = (string) ($dataset->heroes[0]['id'] ?? '');

        try {
            app(GovernorProgressionObservationValidator::class)->validate(
                EvidenceKind::GovernorHeroGear,
                [
                    'hero_id' => $heroId,
                    'gear' => [['slot_id' => 'helmet', 'level' => 201]],
                ],
                $dataset->id,
                $dataset->checksum,
            );
            self::fail('Hero Gear level beyond the pinned catalogue must be rejected.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('between 0 and 200', $exception->getMessage());
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('between 0 and 20');

        app(GovernorProgressionObservationValidator::class)->validate(
            EvidenceKind::GovernorHeroGear,
            [
                'hero_id' => $heroId,
                'gear' => [['slot_id' => 'helmet', 'mastery_level' => 21]],
            ],
            $dataset->id,
            $dataset->checksum,
        );
    }

    public function test_governor_gear_tier_and_star_are_reconciled_to_the_pinned_steps(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        $payload = app(GovernorProgressionObservationValidator::class)->validate(
            EvidenceKind::GovernorGear,
            [
                'gear' => [[
                    'slot_id' => 'helmet',
                    'quality' => 'green',
                    'star' => 0,
                ]],
            ],
            $dataset->id,
            $dataset->checksum,
        );

        self::assertSame('Green', $payload['gear'][0]['quality']);
        self::assertSame(0, $payload['gear'][0]['star']);
    }

    public function test_governor_gear_rejects_a_non_catalogue_tier(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must exist in the pinned factual Progression dataset');

        app(GovernorProgressionObservationValidator::class)->validate(
            EvidenceKind::GovernorGear,
            [
                'gear' => [[
                    'slot_id' => 'helmet',
                    'quality' => 'Mythic',
                    'star' => 3,
                ]],
            ],
            $dataset->id,
            $dataset->checksum,
        );
    }
}
