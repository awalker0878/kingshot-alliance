<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class UpsertRosterEntry
{
    public function __construct(
        private AllianceWriteState $writeState,
        private PersistPlayerIdentity $playerIdentity,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $roster,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{name:string,game_player_id?:string|null,game_role?:string|null,state?:RosterState,joined_at?:string|null,manager_notes?:string|null}  $attributes
     */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        array $attributes,
        ?string $rosterEntryId = null,
        string $source = 'manual',
        ?string $importId = null,
        ?string $expectedPlayerId = null,
    ): RosterEntryReference {
        if (! in_array($source, ['manual', 'csv'], true)) {
            throw new InvalidArgumentException('Unsupported roster source.');
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $attributes, $rosterEntryId, $source, $importId, $expectedPlayerId): RosterEntryReference {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            if (! in_array($context->membership->rank, [AllianceRank::R4, AllianceRank::R5], true)) {
                throw new AuthorizationException;
            }

            $name = trim($attributes['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Player name is required.']);
            }
            $state = $attributes['state'] ?? RosterState::Active;

            if ($rosterEntryId === null) {
                $player = $this->playerIdentity->handle(
                    (string) $context->alliance->kingdom_id,
                    $name,
                    $attributes['game_player_id'] ?? null,
                    $expectedPlayerId,
                );
                if (AllianceRosterEntry::query()->where('alliance_id', $allianceId)->where('player_id', $player->playerId)->exists()) {
                    throw ValidationException::withMessages(['game_player_id' => 'That game player is already on this Alliance roster.']);
                }
                $entry = new AllianceRosterEntry(['alliance_id' => $allianceId, 'player_id' => $player->playerId]);
                $event = 'membership.roster_entry_created';
            } else {
                $entry = AllianceRosterEntry::query()
                    ->where('alliance_id', $allianceId)
                    ->whereKey($rosterEntryId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $player = $this->players->lockCurrent((string) $entry->player_id);
                if ($expectedPlayerId !== null && $player->playerId !== $expectedPlayerId) {
                    throw ValidationException::withMessages(['player_id' => 'The roster entry no longer identifies the expected Player.']);
                }
                $event = 'membership.roster_entry_updated';
            }

            if ($player->kingdomId !== (string) $context->alliance->kingdom_id) {
                throw ValidationException::withMessages(['game_player_id' => 'The Player must currently belong to the Alliance Kingdom.']);
            }

            $entry->forceFill([
                'observed_name' => $name,
                'game_role' => $this->nullable($attributes['game_role'] ?? null),
                'state' => $state,
                'joined_at' => isset($attributes['joined_at']) && $attributes['joined_at'] !== '' ? Carbon::parse($attributes['joined_at'])->toDateString() : null,
                'left_at' => $state === RosterState::Left ? ($entry->left_at ?? now()) : null,
                'manager_notes' => $this->nullable($attributes['manager_notes'] ?? null),
                'last_observed_at' => now(),
                'source' => $source,
            ])->save();

            $metadata = ['roster_entry_id' => (string) $entry->id, 'player_id' => $player->playerId, 'state' => $state->value, 'source' => $source, 'import_id' => $importId];
            $this->audit->record($event, $context->actor, $entry, $context->alliance, $metadata);
            $this->outbox->record($event, $allianceId, $entry, $metadata);

            return $this->roster->find($allianceId, (string) $entry->id) ?? throw new \RuntimeException('Roster entry disappeared after save.');
        });
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
