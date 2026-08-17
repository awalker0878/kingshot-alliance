<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class MarkRosterEntryLeft
{
    public function __construct(
        private AllianceWriteState $writeState,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $roster,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, string $rosterEntryId): RosterEntryReference
    {
        return DB::transaction(function () use ($actorPlayerId, $allianceId, $rosterEntryId): RosterEntryReference {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            if (! in_array($context->membership->rank, [AllianceRank::R4, AllianceRank::R5], true)) {
                throw new AuthorizationException;
            }
            $routing = AllianceRosterEntry::query()->where('alliance_id', $allianceId)->whereKey($rosterEntryId)->firstOrFail();
            $this->players->lockCurrent((string) $routing->player_id);
            $entry = AllianceRosterEntry::query()->where('alliance_id', $allianceId)->whereKey($rosterEntryId)->lockForUpdate()->firstOrFail();
            if ($entry->state !== RosterState::Left) {
                $entry->forceFill(['state' => RosterState::Left, 'left_at' => now(), 'last_observed_at' => now(), 'source' => 'manual'])->save();
                $metadata = ['roster_entry_id' => (string) $entry->id, 'player_id' => (string) $entry->player_id];
                $this->audit->record('membership.roster_entry_left', $context->actor, $entry, $context->alliance, $metadata);
                $this->outbox->record('membership.roster_entry_left', $allianceId, $entry, $metadata);
            }
            return $this->roster->find($allianceId, (string) $entry->id) ?? throw new \RuntimeException('Roster entry disappeared after leave.');
        });
    }
}
