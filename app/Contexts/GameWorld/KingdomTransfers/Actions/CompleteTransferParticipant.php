<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Actions\LeaveAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Roster\Actions\MarkRosterEntryLeft;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferMutationAuthority;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCompletion;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CompleteTransferParticipant
{
    public function __construct(
        private TransferMutationAuthority $authority,
        private SaveRosterEntry $saveRoster,
        private MarkRosterEntryLeft $markRosterLeft,
        private LeaveAlliance $leaveAlliance,
        private PersistPlayerIdentity $playerIdentity,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $planId,
        string $participantId,
    ): TransferCompletion {
        return DB::transaction(function () use ($alliance, $actor, $planId, $participantId): TransferCompletion {
            $context = $this->authority->require($actor, $alliance, TransferPermission::Manage);

            // Completion is child work after a plan is Locked. Shared plan lifecycle
            // permits independent participant completions while close/other plan state
            // transitions retain an exclusive plan lock.
            $plan = TransferPlan::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($planId)
                ->sharedLock()
                ->firstOrFail();

            Kingdom::query()->whereKey($plan->home_kingdom_id)->sharedLock()->firstOrFail();

            if ($plan->state !== TransferPlanState::Locked) {
                throw ValidationException::withMessages([
                    'completion' => 'Transfer participants can only be completed after the cycle is Locked.',
                ]);
            }

            if ($context->alliance->kingdom_id !== $plan->home_kingdom_id) {
                throw ValidationException::withMessages([
                    'completion' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
                ]);
            }

            $participant = TransferParticipant::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->whereKey($participantId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = TransferCompletion::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->where('transfer_participant_id', $participant->id)
                ->first();

            if ($existing instanceof TransferCompletion) {
                return $existing->load(['rosterEntry.player', 'completedBy']);
            }

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

            // Player is the durable Kingdom-movement anchor. All membership/role/
            // roster compatibility checks occur while this Player row is locked.
            $participantPlayer = Player::query()
                ->whereKey($participant->player_id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertPlayerCanMoveKingdom($context->alliance, $participantPlayer, $participant->direction);

            if ($participant->direction === TransferDirection::Incoming) {
                $participantPlayer = $this->playerIdentity->handle(
                    (string) $plan->home_kingdom_id,
                    (string) $participant->observed_name,
                    $participantPlayer->game_player_id,
                    (string) $participantPlayer->id,
                );
            }

            $rosterEntry = match ($participant->direction) {
                TransferDirection::Incoming => $this->completeIncoming(
                    $context->alliance,
                    $context->actor,
                    $participant,
                ),
                TransferDirection::Outgoing => $this->completeOutgoing($context->alliance, $context->actor, $participant),
                TransferDirection::Staying => $this->completeStaying($context->alliance, $participant),
            };

            $player = $rosterEntry->player()->lockForUpdate()->firstOrFail();
            if ($participant->direction === TransferDirection::Outgoing) {
                $this->endTargetAllianceMembership($context->alliance, $player);
                $player = $this->playerIdentity->handle(
                    (string) $participant->destination_kingdom_id,
                    (string) $rosterEntry->observed_name,
                    $player->game_player_id,
                    (string) $player->id,
                );
            }

            $completion = TransferCompletion::query()->create([
                'alliance_id' => $context->alliance->id,
                'transfer_plan_id' => $plan->id,
                'transfer_participant_id' => $participant->id,
                'roster_entry_id' => $rosterEntry->id,
                'direction' => $participant->direction,
                'completed_by_player_id' => $context->actor->id,
                'completed_at' => now(),
            ]);

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'transfer_completion_id' => (string) $completion->id,
                'direction' => $participant->direction->value,
                'roster_entry_id' => (string) $rosterEntry->id,
                'player_id' => (string) $player->id,
                'player_current_kingdom_id' => (string) $player->current_kingdom_id,
            ];

            $this->audit->record('kingdoms.transfer_participant_completed', $context->actor, $completion, $context->alliance, $metadata);
            $this->outbox->record('kingdoms.transfer_participant_completed', (string) $context->alliance->id, $completion, $metadata);

            return $completion->load(['rosterEntry.player', 'completedBy']);
        });
    }

    private function completeIncoming(
        Alliance $alliance,
        Player $actor,
        TransferParticipant $participant,
    ): AllianceRosterEntry {
        $existing = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $participant->player_id)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof AllianceRosterEntry) {
            return $this->saveRoster->handle($alliance, $actor, [
                'name' => (string) $participant->observed_name,
                'game_player_id' => $existing->player->game_player_id,
                'game_role' => $existing->game_role,
                'state' => RosterState::Active,
                'joined_at' => $existing->joined_at?->toDateString(),
                'manager_notes' => $existing->manager_notes,
            ], (string) $existing->id, (string) $existing->source);
        }

        return $this->saveRoster->handle(
            $alliance,
            $actor,
            [
                'name' => (string) $participant->observed_name,
                'game_player_id' => $participant->game_player_id,
                'state' => RosterState::Active,
            ],
            expectedPlayerId: (string) $participant->player_id,
        );
    }

    private function completeOutgoing(
        Alliance $alliance,
        Player $actor,
        TransferParticipant $participant,
    ): AllianceRosterEntry {
        $entry = $this->rosterBoundEntry($alliance, $participant, false);

        return $this->markRosterLeft->handle($alliance, $actor, (string) $entry->id);
    }

    private function completeStaying(
        Alliance $alliance,
        TransferParticipant $participant,
    ): AllianceRosterEntry {
        return $this->rosterBoundEntry($alliance, $participant, true);
    }

    private function assertPlayerCanMoveKingdom(Alliance $alliance, Player $player, TransferDirection $direction): void
    {
        $activeMembership = AllianceMembership::query()
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($activeMembership instanceof AllianceMembership) {
            if ($direction === TransferDirection::Outgoing && (string) $activeMembership->alliance_id === (string) $alliance->id) {
                if ($activeMembership->rank === AllianceRank::R5) {
                    throw ValidationException::withMessages([
                        'completion' => 'Transfer Alliance leadership before completing an outgoing R5 Player.',
                    ]);
                }
            } elseif ($direction !== TransferDirection::Staying || (string) $activeMembership->alliance_id !== (string) $alliance->id) {
                throw ValidationException::withMessages([
                    'completion' => 'The Player still has an active Alliance membership that must be ended before changing Kingdoms.',
                ]);
            }
        }

        if ($direction === TransferDirection::Staying) {
            return;
        }

        if (KingdomRoleAssignment::query()
            ->where('player_id', $player->id)
            ->where('kingdom_id', $player->current_kingdom_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'completion' => 'Remove or transfer the Player Kingdom roles before changing Kingdoms.',
            ]);
        }

        $rosterQuery = AllianceRosterEntry::query()
            ->where('player_id', $player->id)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value]);

        if ($direction === TransferDirection::Outgoing) {
            $rosterQuery->where('alliance_id', '!=', $alliance->id);
        } else {
            $rosterQuery->whereHas('alliance', fn ($query) => $query->where('kingdom_id', '!=', $alliance->kingdom_id));
        }

        if ($rosterQuery->exists()) {
            throw ValidationException::withMessages([
                'completion' => "Resolve the Player's other active or tracked Alliance roster entries before changing Kingdoms.",
            ]);
        }
    }

    private function endTargetAllianceMembership(Alliance $alliance, Player $player): void
    {
        $hasActiveMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->exists();

        if (! $hasActiveMembership) {
            return;
        }

        $this->leaveAlliance->handle($alliance, $player);
    }

    private function rosterBoundEntry(
        Alliance $alliance,
        TransferParticipant $participant,
        bool $mustStillBePresent,
    ): AllianceRosterEntry {
        if ($participant->roster_entry_id === null) {
            throw ValidationException::withMessages([
                'completion' => 'This roster-bound participant no longer has a roster entry to hand off.',
            ]);
        }

        $query = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->with('player')
            ->lockForUpdate();

        if ($mustStillBePresent) {
            $query->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value]);
        }

        $entry = $query->findOrFail($participant->roster_entry_id);

        if ($participant->player_id !== null
            && (string) $entry->player_id !== (string) $participant->player_id) {
            throw ValidationException::withMessages([
                'completion' => 'The participant roster binding no longer matches its captured game identity.',
            ]);
        }

        return $entry;
    }
}
