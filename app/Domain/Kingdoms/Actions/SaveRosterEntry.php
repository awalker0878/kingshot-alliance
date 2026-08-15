<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class SaveRosterEntry
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private ResolvePlayer $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{
     *   name: string,
     *   game_player_id?: string|null,
     *   game_role?: string|null,
     *   state?: RosterState,
     *   joined_at?: string|null,
     *   manager_notes?: string|null
     * } $attributes
     */
    public function handle(
        Alliance $alliance,
        Player $actor,
        array $attributes,
        ?string $entryId = null,
        string $source = 'manual',
        ?string $importId = null,
        ?string $expectedPlayerId = null,
    ): AllianceRosterEntry {
        if (! in_array($source, ['manual', 'csv'], true)) {
            throw new InvalidArgumentException('Unsupported roster source.');
        }

        return DB::transaction(function () use ($alliance, $actor, $attributes, $entryId, $source, $importId, $expectedPlayerId): AllianceRosterEntry {
            $context = $this->authority->require($actor, $alliance, PermissionKey::KingdomManage);
            $name = trim($attributes['name']);
            $state = $attributes['state'] ?? RosterState::Active;

            if ($entryId === null) {
                // ResolvePlayer locks the durable Player identity before any roster row
                // is created, matching the Player -> roster order used by transfer flows.
                $player = $this->players->handle(
                    $context->alliance,
                    $name,
                    $attributes['game_player_id'] ?? null,
                    $expectedPlayerId,
                );

                if (AllianceRosterEntry::query()
                    ->where('alliance_id', $context->alliance->id)
                    ->where('player_id', $player->id)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'game_player_id' => 'That game player is already on this Alliance roster.',
                    ]);
                }

                $entry = new AllianceRosterEntry([
                    'alliance_id' => $context->alliance->id,
                    'player_id' => $player->id,
                ]);
                $event = 'kingdoms.roster_entry_created';
            } else {
                // Resolve immutable routing identity without a row lock, then acquire
                // Player before roster to avoid reversing Kingdom-transfer lock order.
                $routing = AllianceRosterEntry::query()
                    ->select(['id', 'player_id'])
                    ->where('alliance_id', $context->alliance->id)
                    ->whereKey($entryId)
                    ->firstOrFail();

                $player = Player::query()
                    ->whereKey($routing->player_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $entry = AllianceRosterEntry::query()
                    ->where('alliance_id', $context->alliance->id)
                    ->where('player_id', $player->id)
                    ->whereKey($entryId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $event = 'kingdoms.roster_entry_updated';
            }

            if (! isset($player)) {
                $player = Player::query()->whereKey($entry->player_id)->lockForUpdate()->firstOrFail();
            }

            if ((string) $player->current_kingdom_id !== (string) $context->alliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'game_player_id' => 'The Player must currently belong to the Alliance Kingdom.',
                ]);
            }

            $entry->forceFill([
                'observed_name' => $name,
                'game_role' => $this->nullableLine($attributes['game_role'] ?? null),
                'state' => $state,
                'joined_at' => isset($attributes['joined_at']) && $attributes['joined_at'] !== ''
                    ? Carbon::parse($attributes['joined_at'])->toDateString()
                    : null,
                'left_at' => $state === RosterState::Left ? ($entry->left_at ?? now()) : null,
                'manager_notes' => $this->nullableText($attributes['manager_notes'] ?? null),
                'last_observed_at' => now(),
                'source' => $source,
            ])->save();

            $metadata = [
                'roster_entry_id' => (string) $entry->id,
                'player_id' => (string) $entry->player_id,
                'state' => $entry->state->value,
                'source' => $source,
                'import_id' => $importId,
            ];

            $this->audit->record($event, $context->actor, $entry, $context->alliance, $metadata);
            $this->outbox->record($event, (string) $context->alliance->id, $entry, $metadata);

            return $entry->refresh()->load('player');
        });
    }

    private function nullableLine(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
