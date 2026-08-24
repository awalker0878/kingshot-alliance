<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Roster;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Roster\Actions\RecordPlayerSnapshot;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlayerSnapshotProgressionPinV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_observation_pins_release_normalizes_heroes_and_is_idempotent(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 62201);
        $alliance = $scenario->alliance($actor);
        $entry = $scenario->roster($actor, $alliance);
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $action = app(RecordPlayerSnapshot::class);
        $capturedAt = now()->utc()->startOfSecond()->toIso8601String();
        $attributes = [
            'observed_name' => $actor->currentName,
            'power' => '123456789',
            'progression_level' => 'TC30',
            'captured_at' => $capturedAt,
            'progression_dataset_id' => $dataset->id,
            'progression_dataset_checksum' => $dataset->checksum,
            'hero_observations' => [
                ['hero_id' => 'Ava', 'level' => 80, 'star' => 5, 'widget_level' => 10],
                ['hero_id' => 'Charles', 'level' => null, 'star' => 4, 'widget_level' => null],
            ],
        ];

        $first = $action->handle($alliance->allianceId, $actor->playerId, $entry->rosterEntryId, $attributes);
        $second = $action->handle($alliance->allianceId, $actor->playerId, $entry->rosterEntryId, $attributes);

        self::assertTrue($first->created);
        self::assertFalse($second->created);
        self::assertSame($first->snapshotId, $second->snapshotId);
        self::assertSame(1, PlayerSnapshot::query()->count());

        $snapshot = PlayerSnapshot::query()->sole();
        self::assertSame($dataset->id, $snapshot->progression_dataset_id);
        self::assertSame($dataset->checksum, $snapshot->progression_dataset_checksum);
        self::assertSame([
            [
                'hero_id' => 'ava',
                'level' => 80,
                'star' => 5,
                'widget_level' => 10,
                'complete_roster_capture' => false,
            ],
            [
                'hero_id' => 'charles',
                'level' => null,
                'star' => 4,
                'widget_level' => null,
                'complete_roster_capture' => false,
            ],
        ], $snapshot->hero_observations);
    }

    public function test_observation_rejects_unknown_duplicate_and_out_of_bounds_hero_facts(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 62202);
        $alliance = $scenario->alliance($actor);
        $entry = $scenario->roster($actor, $alliance);
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $action = app(RecordPlayerSnapshot::class);
        $base = [
            'observed_name' => $actor->currentName,
            'power' => '123',
            'captured_at' => now()->utc()->toIso8601String(),
            'progression_dataset_id' => $dataset->id,
            'progression_dataset_checksum' => $dataset->checksum,
        ];

        foreach ([
            [['hero_id' => 'Imaginary Hero']],
            [['hero_id' => 'Ava'], ['hero_id' => 'ava']],
            [['hero_id' => 'Ava', 'level' => 81]],
        ] as $heroObservations) {
            try {
                $action->handle(
                    $alliance->allianceId,
                    $actor->playerId,
                    $entry->rosterEntryId,
                    [...$base, 'hero_observations' => $heroObservations],
                );
                self::fail('Expected invalid Hero observation data to be rejected.');
            } catch (ValidationException) {
                self::assertSame(0, PlayerSnapshot::query()->count());
            }
        }
    }
}
