<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
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
            $context = $this->authority->require($actor, $alliance, IntelligencePermission::KingdomManage);

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
