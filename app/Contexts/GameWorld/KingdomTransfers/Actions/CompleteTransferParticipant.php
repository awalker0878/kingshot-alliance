<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Membership\Actions\ActivateRosterEntryForTransfer;
use App\Contexts\Alliance\Membership\Actions\EndMembershipForTransfer;
use App\Contexts\Alliance\Membership\Actions\MarkRosterEntryLeftForTransfer;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCompletion;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CompleteTransferParticipant
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private PlayerMembershipQuery $memberships,
        private RosterEntryQuery $roster,
        private ActivateRosterEntryForTransfer $activateRoster,
        private MarkRosterEntryLeftForTransfer $markRosterLeft,
        private EndMembershipForTransfer $endMembership,
        private PersistPlayerIdentity $playerIdentity,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $planId,
        string $participantId,
    ): void {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($planId)
                ->sharedLock()
                ->firstOrFail();

            if ($plan->state !== TransferPlanState::Locked) {
                throw ValidationException::withMessages([
                    'completion' => 'Transfer participants can only be completed after the cycle is Locked.',
                ]);
            }

            if ($context->kingdomId() !== (string) $plan->home_kingdom_id) {
                throw ValidationException::withMessages([
                    'completion' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
                ]);
            }

            $participant = TransferParticipant::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $plan->id)
                ->whereKey($participantId)
                ->lockForUpdate()
                ->firstOrFail();

            if (TransferCompletion::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $plan->id)
                ->where('transfer_participant_id', $participant->id)
                ->exists()) {
                return;
            }

            $this->assertCompletable($participant);

            // The mutable Player row is loaded only inside the owner operation. Cross-action
            // state is represented by PlayerReference, never by an Eloquent union.
            $player = Player::query()
                ->whereKey($participant->player_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPlayerCanMoveKingdom(
                allianceId: $allianceId,
                allianceKingdomId: $context->kingdomId(),
                player: $player,
                direction: $participant->direction,
            );

            $playerReference = $this->players->require((string) $player->id);

            if ($participant->direction === TransferDirection::Incoming) {
                $playerReference = $this->playerIdentity->handle(
                    (string) $plan->home_kingdom_id,
                    (string) $participant->observed_name,
                    $player->game_player_id,
                    (string) $player->id,
                );
            }

            $rosterEntry = match ($participant->direction) {
                TransferDirection::Incoming => $this->activateRoster->handle(
                    allianceId: $allianceId,
                    actorPlayerId: $actorPlayerId,
                    targetPlayerId: (string) $participant->player_id,
                    observedName: (string) $participant->observed_name,
                ),
                TransferDirection::Outgoing => $this->completeOutgoing(
                    $allianceId,
                    $actorPlayerId,
                    $participant,
                ),
                TransferDirection::Staying => $this->rosterBoundEntry(
                    $allianceId,
                    $participant,
                    true,
                ),
            };

            if ($participant->direction === TransferDirection::Outgoing) {
                $this->endMembership->handle(
                    allianceId: $allianceId,
                    actorPlayerId: $actorPlayerId,
                    targetPlayerId: (string) $participant->player_id,
                );

                $playerReference = $this->playerIdentity->handle(
                    (string) $participant->destination_kingdom_id,
                    $rosterEntry->observedName,
                    $player->game_player_id,
                    (string) $player->id,
                );
            }

            $completion = TransferCompletion::query()->create([
                'alliance_id' => $allianceId,
                'transfer_plan_id' => $plan->id,
                'transfer_participant_id' => $participant->id,
                'roster_entry_id' => $rosterEntry->rosterEntryId,
                'direction' => $participant->direction,
                'completed_by_player_id' => $context->actor->playerId,
                'completed_at' => now(),
            ]);

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'transfer_completion_id' => (string) $completion->id,
                'direction' => $participant->direction->value,
                'roster_entry_id' => $rosterEntry->rosterEntryId,
                'player_id' => $playerReference->playerId,
                'player_current_kingdom_id' => $playerReference->kingdomId,
            ];

            $this->audit->record(
                'kingdoms.transfer_participant_completed',
                $context->actor,
                $completion,
                null,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_participant_completed',
                $allianceId,
                $completion,
                $metadata,
            );
        });
    }

    private function assertCompletable(TransferParticipant $participant): void
    {
        if ($participant->withdrawn_at !== null || $participant->readiness_state === TransferReadinessState::Withdrawn) {
            throw ValidationException::withMessages([
                'completion' => 'Withdrawn transfer participants cannot be completed.',
            ]);
        }

        if ($participant->readiness_state !== TransferReadinessState::Confirmed) {
            throw ValidationException::withMessages([
                'completion' => 'The participant must be explicitly Confirmed before actual completion is recorded.',
            ]);
        }

        if ($participant->direction === TransferDirection::Outgoing && $participant->destination_kingdom_id === null) {
            throw ValidationException::withMessages([
                'completion' => 'Set an outgoing destination Kingdom before completing the transfer.',
            ]);
        }
    }

    private function completeOutgoing(
        string $allianceId,
        string $actorPlayerId,
        TransferParticipant $participant,
    ): RosterEntryReference {
        $entry = $this->rosterBoundEntry($allianceId, $participant, false);

        return $this->markRosterLeft->handle(
            allianceId: $allianceId,
            actorPlayerId: $actorPlayerId,
            rosterEntryId: $entry->rosterEntryId,
            expectedPlayerId: (string) $participant->player_id,
        );
    }

    private function rosterBoundEntry(
        string $allianceId,
        TransferParticipant $participant,
        bool $mustStillBePresent,
    ): RosterEntryReference {
        if ($participant->roster_entry_id === null) {
            throw ValidationException::withMessages([
                'completion' => 'This roster-bound participant no longer has a roster entry to hand off.',
            ]);
        }

        $entry = $mustStillBePresent
            ? $this->roster->requireActiveOrTracked($allianceId, (string) $participant->roster_entry_id)
            : $this->roster->find($allianceId, (string) $participant->roster_entry_id);

        if (! $entry instanceof RosterEntryReference) {
            throw ValidationException::withMessages([
                'completion' => 'This roster-bound participant no longer has a roster entry to hand off.',
            ]);
        }

        if ($entry->playerId !== (string) $participant->player_id) {
            throw ValidationException::withMessages([
                'completion' => 'The participant roster binding no longer matches its captured game identity.',
            ]);
        }

        return $entry;
    }

    private function assertPlayerCanMoveKingdom(
        string $allianceId,
        string $allianceKingdomId,
        Player $player,
        TransferDirection $direction,
    ): void {
        if ($direction === TransferDirection::Staying) {
            return;
        }

        $activeAllianceIds = $this->memberships->activeAllianceIds([(string) $player->id]);
        $disallowedMemberships = $direction === TransferDirection::Outgoing
            ? array_values(array_filter(
                $activeAllianceIds,
                static fn (string $activeAllianceId): bool => $activeAllianceId !== $allianceId,
            ))
            : $activeAllianceIds;

        if ($disallowedMemberships !== []) {
            throw ValidationException::withMessages([
                'completion' => 'The Player still has an active Alliance membership that must be ended before changing Kingdoms.',
            ]);
        }

        if (KingdomRoleAssignment::query()
            ->where('player_id', $player->id)
            ->where('kingdom_id', $player->current_kingdom_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'completion' => 'Remove or transfer the Player Kingdom roles before changing Kingdoms.',
            ]);
        }

        $hasConflictingRoster = $direction === TransferDirection::Outgoing
            ? $this->roster->hasActiveOrTrackedOutsideAlliance((string) $player->id, $allianceId)
            : $this->roster->hasActiveOrTrackedOutsideKingdom((string) $player->id, $allianceKingdomId);

        if ($hasConflictingRoster) {
            throw ValidationException::withMessages([
                'completion' => "Resolve the Player's other active or tracked Alliance roster entries before changing Kingdoms.",
            ]);
        }
    }
}
