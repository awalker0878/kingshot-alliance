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
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ActivateRosterEntryForTransfer
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authorization,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $roster,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $targetPlayerId, string $observedName): RosterEntryReference
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $targetPlayerId, $observedName): RosterEntryReference {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, AlliancePermission::MembershipManage);
            $player = $this->players->require($targetPlayerId);
            if ($player->kingdomId !== (string) $context->alliance->kingdom_id) {
                throw ValidationException::withMessages(['completion' => 'The incoming Player must belong to the Alliance Kingdom before activating its roster entry.']);
            }

            $entry = AllianceRosterEntry::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $targetPlayerId)
                ->lockForUpdate()
                ->first();
            $entry ??= new AllianceRosterEntry(['alliance_id' => $allianceId, 'player_id' => $targetPlayerId]);
            $entry->forceFill([
                'observed_name' => trim($observedName),
                'state' => RosterState::Active,
                'joined_at' => $entry->joined_at ?? now(),
                'left_at' => null,
                'last_observed_at' => now(),
                'source' => $entry->exists ? $entry->source : 'transfer',
            ])->save();

            $metadata = ['roster_entry_id' => (string) $entry->id, 'player_id' => $targetPlayerId, 'source' => 'transfer'];
            $this->audit->record('membership.roster_transfer_activated', $context->actor, $entry, $context->alliance, $metadata);
            $this->outbox->record('membership.roster_transfer_activated', $allianceId, $entry, $metadata);

            return $this->roster->find($allianceId, (string) $entry->id)
                ?? throw new \RuntimeException('Roster entry disappeared after transfer activation.');
        });
    }
}
