<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class MarkRosterEntryLeft
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $entryId): AllianceRosterEntry
    {
        return DB::transaction(function () use ($alliance, $actor, $entryId): AllianceRosterEntry {
            $context = $this->authority->require($actor, $alliance, PermissionKey::KingdomManage);

            $routing = AllianceRosterEntry::query()
                ->select(['id', 'player_id'])
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($entryId)
                ->firstOrFail();

            // Player is the durable Kingdom/roster identity anchor. Lock it before
            // the roster row so leave/manual cleanup cannot race a Player transfer.
            Player::query()
                ->whereKey($routing->player_id)
                ->lockForUpdate()
                ->firstOrFail();

            $entry = AllianceRosterEntry::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('player_id', $routing->player_id)
                ->whereKey($entryId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->state === RosterState::Left) {
                return $entry->load('player');
            }

            $entry->forceFill([
                'state' => RosterState::Left,
                'left_at' => now(),
                'last_observed_at' => now(),
                'source' => 'manual',
            ])->save();

            $metadata = [
                'roster_entry_id' => (string) $entry->id,
                'player_id' => (string) $entry->player_id,
            ];

            $this->audit->record('kingdoms.roster_entry_left', $context->actor, $entry, $context->alliance, $metadata);
            $this->outbox->record('kingdoms.roster_entry_left', (string) $context->alliance->id, $entry, $metadata);

            return $entry->refresh()->load('player');
        });
    }
}
