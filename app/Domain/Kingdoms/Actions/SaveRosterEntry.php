<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class SaveRosterEntry
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ResolveKingdomPlayer $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{
     *   name: string,
     *   game_player_id?: string|null,
     *   membership_id?: string|null,
     *   game_role?: string|null,
     *   state?: RosterState,
     *   joined_at?: string|null,
     *   manager_notes?: string|null
     * } $attributes
     */
    public function handle(
        Alliance $alliance,
        User $actor,
        array $attributes,
        ?string $entryId = null,
        string $source = 'manual',
        ?string $importId = null,
    ): AllianceRosterEntry {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        if (! in_array($source, ['manual', 'csv'], true)) {
            throw new InvalidArgumentException('Unsupported roster source.');
        }

        return DB::transaction(function () use ($alliance, $actor, $attributes, $entryId, $source, $importId): AllianceRosterEntry {
            $membership = $this->membership($alliance, $attributes['membership_id'] ?? null, $entryId);
            $name = trim($attributes['name']);
            $state = $attributes['state'] ?? RosterState::Active;

            if ($entryId === null) {
                $player = $this->players->handle($alliance, $name, $attributes['game_player_id'] ?? null);

                if (AllianceRosterEntry::query()
                    ->where('alliance_id', $alliance->id)
                    ->where('kingdom_player_id', $player->id)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'game_player_id' => 'That game player is already on this alliance roster.',
                    ]);
                }

                $entry = new AllianceRosterEntry([
                    'alliance_id' => $alliance->id,
                    'kingdom_player_id' => $player->id,
                ]);
                $event = 'kingdoms.roster_entry_created';
            } else {
                $entry = AllianceRosterEntry::query()
                    ->where('alliance_id', $alliance->id)
                    ->lockForUpdate()
                    ->findOrFail($entryId);
                $event = 'kingdoms.roster_entry_updated';
            }

            $entry->forceFill([
                'membership_id' => $membership?->id,
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
                'kingdom_player_id' => (string) $entry->kingdom_player_id,
                'membership_id' => $entry->membership_id,
                'state' => $entry->state->value,
                'source' => $source,
                'import_id' => $importId,
            ];

            $this->audit->record($event, $actor, $entry, $alliance, $metadata);
            $this->outbox->record($event, (string) $alliance->id, $entry, $metadata);

            return $entry->refresh()->load(['player', 'membership.user']);
        });
    }

    private function membership(
        Alliance $alliance,
        ?string $membershipId,
        ?string $exceptRosterEntryId,
    ): ?AllianceMembership {
        if ($membershipId === null || trim($membershipId) === '') {
            return null;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->find($membershipId);

        if (! $membership instanceof AllianceMembership) {
            throw ValidationException::withMessages([
                'membership_id' => 'The linked membership must be active in this alliance.',
            ]);
        }

        $linkedQuery = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('membership_id', $membership->id);

        if ($exceptRosterEntryId !== null) {
            $linkedQuery->where('id', '<>', $exceptRosterEntryId);
        }

        if ($linkedQuery->exists()) {
            throw ValidationException::withMessages([
                'membership_id' => 'That membership is already linked to a roster entry.',
            ]);
        }

        return $membership;
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
