<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class MarkRosterEntryLeftForTransfer
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authorization,
        private RosterEntryQuery $roster,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $rosterEntryId, string $expectedPlayerId): RosterEntryReference
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $rosterEntryId, $expectedPlayerId): RosterEntryReference {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, AlliancePermission::MembershipManage);
            $entry = AllianceRosterEntry::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $expectedPlayerId)
                ->whereKey($rosterEntryId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->state !== RosterState::Left) {
                $entry->forceFill([
                    'state' => RosterState::Left,
                    'left_at' => now(),
                    'last_observed_at' => now(),
                    'source' => 'transfer',
                ])->save();
                $metadata = ['roster_entry_id' => (string) $entry->id, 'player_id' => $expectedPlayerId, 'source' => 'transfer'];
                $this->audit->record('membership.roster_transfer_left', $context->actor, $entry, $context->alliance, $metadata);
                $this->outbox->record('membership.roster_transfer_left', $allianceId, $entry, $metadata);
            }

            return $this->roster->find($allianceId, (string) $entry->id) ?? throw new \RuntimeException('Roster entry disappeared after transfer handoff.');
        });
    }
}
