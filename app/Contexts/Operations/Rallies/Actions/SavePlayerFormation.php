<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
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
    ): void {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Formation name is required and must be 120 characters or fewer.']);
        }
        $heroes = array_values(array_slice(array_filter(
            array_map(static fn ($hero): string => trim((string) $hero), $heroes),
            static fn (string $hero): bool => $hero !== '',
        ), 0, 5));

        DB::transaction(function () use ($actorPlayerId, $name, $composition, $heroes, $notes, $isDefault, $formationId): void {
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
                'heroes' => $heroes,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'is_default' => $isDefault,
                'updated_by_player_id' => $actor->playerId,
            ])->save();

            $eventName = $created ? 'rally.player_formation.created' : 'rally.player_formation.updated';
            $metadata = ['player_id' => $actor->playerId, 'formation_id' => (string) $record->id, 'is_default' => $isDefault];
            $this->audit->record($eventName, $actor, $record, null, $metadata);
            $this->outbox->record($eventName, null, $record, $metadata, partitionKey: 'player:'.$actor->playerId);
        });
    }
}
