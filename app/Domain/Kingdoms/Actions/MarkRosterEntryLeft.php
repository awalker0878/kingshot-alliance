<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class MarkRosterEntryLeft
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $entryId): AllianceRosterEntry
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $entryId): AllianceRosterEntry {
            $entry = AllianceRosterEntry::query()
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->findOrFail($entryId);

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

            $this->audit->record('kingdoms.roster_entry_left', $actor, $entry, $alliance, $metadata);
            $this->outbox->record('kingdoms.roster_entry_left', (string) $alliance->id, $entry, $metadata);

            return $entry->refresh()->load('player');
        });
    }
}
