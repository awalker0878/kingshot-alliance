<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Contexts\GameWorld\Governance\Services\PlayerMutationAuthority;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Rallies\Models\PlayerFormation;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SavePlayerFormation
{
    public function __construct(
        private PlayerMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $heroes */
    public function handle(
        Player $actor,
        Player $player,
        string $name,
        FormationComposition $composition,
        array $heroes = [],
        ?string $notes = null,
        bool $isDefault = false,
        ?PlayerFormation $formation = null,
    ): PlayerFormation {
        if ((string) $actor->id !== (string) $player->id) {
            throw new AuthorizationException;
        }

        if ($formation instanceof PlayerFormation && (string) $formation->player_id !== (string) $player->id) {
            throw new AuthorizationException;
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Formation name is required and must be 120 characters or fewer.']);
        }

        $heroes = array_values(array_slice(array_filter(
            array_map(static fn ($hero): string => trim((string) $hero), $heroes),
            static fn (string $hero): bool => $hero !== '',
        ), 0, 5));

        return DB::transaction(function () use ($actor, $name, $composition, $heroes, $notes, $isDefault, $formation): PlayerFormation {
            // Player is the natural configuration anchor. It serializes default
            // selection across all of this Player's formations without any Alliance lock.
            $context = $this->authority->require($actor);

            $record = $formation instanceof PlayerFormation
                ? PlayerFormation::query()
                    ->whereKey($formation->id)
                    ->where('player_id', $context->actor->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : new PlayerFormation(['player_id' => $context->actor->id]);

            if ($isDefault) {
                PlayerFormation::query()
                    ->where('player_id', $context->actor->id)
                    ->where('is_default', true)
                    ->when($record->exists, static fn ($query) => $query->where('id', '!=', $record->id))
                    ->update(['is_default' => false]);
            }

            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $context->actor->id;
            }

            $record->forceFill([
                'name' => $name,
                ...$composition->toArray(),
                'heroes' => $heroes,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'is_default' => $isDefault,
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            $event = $created ? 'rally.player_formation.created' : 'rally.player_formation.updated';
            $metadata = [
                'player_id' => (string) $context->actor->id,
                'formation_id' => (string) $record->id,
                'is_default' => $isDefault,
            ];
            $this->audit->record($event, $context->actor, $record, null, $metadata);
            $this->outbox->record($event, null, $record, $metadata, partitionKey: 'player:'.$context->actor->id);

            return $record->refresh();
        });
    }
}
