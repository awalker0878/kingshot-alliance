<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Rallies;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Operations\Rallies\Actions\SavePlayerFormation;
use App\Contexts\Operations\Rallies\Models\PlayerFormation;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlayerFormationProgressionPinV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_saved_loadout_pins_factual_release_and_canonical_hero_ids(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $player = $scenario->player((int) $account->id, 62101);
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        app(SavePlayerFormation::class)->handle(
            actorPlayerId: $player->playerId,
            name: 'Bear march',
            composition: new FormationComposition(10, 10, 80),
            heroes: ['Charles', 'Ava'],
            notes: 'Saved intent only',
            isDefault: true,
            progressionDatasetId: $dataset->id,
            progressionDatasetChecksum: $dataset->checksum,
        );

        $formation = PlayerFormation::query()->where('player_id', $player->playerId)->sole();

        self::assertSame(['charles', 'ava'], $formation->heroes);
        self::assertSame($dataset->id, $formation->progression_dataset_id);
        self::assertSame($dataset->checksum, $formation->progression_dataset_checksum);
        self::assertSame(10, $formation->infantry_percent);
        self::assertSame(10, $formation->cavalry_percent);
        self::assertSame(80, $formation->archer_percent);
        self::assertTrue($formation->is_default);
    }

    public function test_saved_loadout_rejects_unknown_hero_and_wrong_dataset_checksum(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $player = $scenario->player((int) $account->id, 62102);
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $action = app(SavePlayerFormation::class);

        try {
            $action->handle(
                actorPlayerId: $player->playerId,
                name: 'Unknown Hero',
                composition: new FormationComposition(50, 20, 30),
                heroes: ['Imaginary Hero'],
                progressionDatasetId: $dataset->id,
                progressionDatasetChecksum: $dataset->checksum,
            );
            self::fail('Expected an unknown Hero to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('heroes.0', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $action->handle(
            actorPlayerId: $player->playerId,
            name: 'Wrong checksum',
            composition: new FormationComposition(50, 20, 30),
            heroes: ['Charles'],
            progressionDatasetId: $dataset->id,
            progressionDatasetChecksum: str_repeat('0', 64),
        );
    }
}
