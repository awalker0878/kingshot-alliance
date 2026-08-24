<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Operations\Rallies\Models\PlayerFormation;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SavePlayerFormation
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private ProgressionDatasetQuery $progression,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $heroes */
    public function handle(
        string $actorPlayerId,
        string $name,
        FormationComposition $composition,
        array $heroes = [],
        ?string $notes = null,
        bool $isDefault = false,
        ?string $formationId = null,
        ?string $progressionDatasetId = null,
        ?string $progressionDatasetChecksum = null,
    ): void {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Formation name is required and must be 120 characters or fewer.']);
        }

        $dataset = $progressionDatasetId !== null && trim($progressionDatasetId) !== ''
            ? $this->progression->require(trim($progressionDatasetId), $progressionDatasetChecksum)
            : $this->progression->latest();

        $canonicalHeroes = [];
        foreach (array_values(array_slice($heroes, 0, 5)) as $index => $hero) {
            $candidate = trim((string) $hero);
            if ($candidate === '') {
                continue;
            }
            $heroId = $this->progression->canonicalHeroId($candidate, $dataset);
            if ($heroId === null) {
                throw ValidationException::withMessages(["heroes.$index" => 'Hero must exist in the pinned factual progression dataset.']);
            }
            if (! in_array($heroId, $canonicalHeroes, true)) {
                $canonicalHeroes[] = $heroId;
            }
        }

        DB::transaction(function () use ($actorPlayerId, $name, $composition, $canonicalHeroes, $notes, $isDefault, $formationId, $dataset): void {
            $actor = $this->players->lockCurrent($actorPlayerId);
            $record = $formationId !== null
                ? PlayerFormation::query()->whereKey($formationId)->where('player_id', $actor->playerId)->lockForUpdate()->firstOrFail()
                : new PlayerFormation(['player_id' => $actor->playerId]);

            if ($isDefault) {
                PlayerFormation::query()->where('player_id', $actor->playerId)->where('is_default', true)
                    ->when($record->exists, static fn ($query) => $query->where('id', '!=', $record->id))
                    ->update(['is_default' => false]);
            }

            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $actor->playerId;
            }
            $record->forceFill([
                'name' => $name,
                ...$composition->toArray(),
                'heroes' => $canonicalHeroes,
                'progression_dataset_id' => $dataset->id,
                'progression_dataset_checksum' => $dataset->checksum,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'is_default' => $isDefault,
                'updated_by_player_id' => $actor->playerId,
            ])->save();

            $eventName = $created ? 'rally.player_formation.created' : 'rally.player_formation.updated';
            $metadata = [
                'player_id' => $actor->playerId,
                'formation_id' => (string) $record->id,
                'is_default' => $isDefault,
                'progression_dataset_id' => $dataset->id,
                'progression_dataset_checksum' => $dataset->checksum,
                'hero_count' => count($canonicalHeroes),
            ];
            $this->audit->record($eventName, $actor, $record, null, $metadata);
            $this->outbox->record($eventName, null, $record, $metadata, partitionKey: 'player:'.$actor->playerId);
        });
    }
}
