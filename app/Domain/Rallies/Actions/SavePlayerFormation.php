<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Models\PlayerFormation;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SavePlayerFormation
{
    public function __construct(private PlayerContext $context, private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    /** @param list<string> $heroes */
    public function handle(Player $actor, Player $player, string $name, FormationComposition $composition, array $heroes = [], ?string $notes = null, bool $isDefault = false, ?PlayerFormation $formation = null): PlayerFormation
    {
        $active = $this->context->playerOrNull();
        if (! $active instanceof Player
            || (string) $active->id !== (string) $actor->id
            || (string) $actor->id !== (string) $player->id) {
            throw new AuthorizationException;
        }
        if ($formation instanceof PlayerFormation && (string) $formation->player_id !== (string) $player->id) {
            throw new AuthorizationException;
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Formation name is required and must be 120 characters or fewer.']);
        }
        $heroes = array_values(array_slice(array_filter(array_map(static fn ($hero): string => trim((string) $hero)), static fn (string $hero): bool => $hero !== ''), 0, 5));

        return DB::transaction(function () use ($actor, $player, $name, $composition, $heroes, $notes, $isDefault, $formation): PlayerFormation {
            Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();
            if ($isDefault) {
                PlayerFormation::query()->where('player_id', $player->id)->where('is_default', true)->when($formation instanceof PlayerFormation, static fn ($query) => $query->where('id', '!=', $formation->id))->update(['is_default' => false]);
            }
            $record = $formation instanceof PlayerFormation ? PlayerFormation::query()->whereKey($formation->id)->where('player_id', $player->id)->lockForUpdate()->firstOrFail() : new PlayerFormation(['player_id' => $player->id]);
            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $player->id;
            }
            $record->forceFill([
                'name' => $name,
                ...$composition->toArray(),
                'heroes' => $heroes,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'is_default' => $isDefault,
                'updated_by_player_id' => $player->id,
            ])->save();
            $event = $created ? 'rally.player_formation.created' : 'rally.player_formation.updated';
            $metadata = ['player_id' => (string) $player->id, 'formation_id' => (string) $record->id, 'is_default' => $isDefault];
            $this->audit->record($event, $actor, $record, null, $metadata);
            $this->outbox->record($event, null, $record, $metadata, partitionKey: 'player:'.$player->id);

            return $record->refresh();
        });
    }
}
