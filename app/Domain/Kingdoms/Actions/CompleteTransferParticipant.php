<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\TransferCompletion;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CompleteTransferParticipant
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private SaveRosterEntry $saveRoster,
        private MarkRosterEntryLeft $markRosterLeft,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        User $actor,
        string $planId,
        string $participantId,
        ?string $existingRosterEntryId = null,
    ): TransferCompletion {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use (
            $alliance,
            $actor,
            $planId,
            $participantId,
            $existingRosterEntryId,
        ): TransferCompletion {
            $currentAlliance = Alliance::query()
                ->lockForUpdate()
                ->findOrFail($alliance->id);

            $plan = TransferPlan::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($planId);

            Kingdom::query()->findOrFail($plan->home_kingdom_id);

            if ($plan->state !== TransferPlanState::Locked) {
                throw ValidationException::withMessages([
                    'completion' => 'Transfer participants can only be completed after the cycle is Locked.',
                ]);
            }

            if ($currentAlliance->kingdom_id !== $plan->home_kingdom_id) {
                throw ValidationException::withMessages([
                    'completion' => 'The alliance Kingdom changed after this transfer cycle was created. Cancel the stale cycle instead of completing roster handoff.',
                ]);
            }

            $participant = TransferParticipant::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->with(['membership', 'rosterEntry.player'])
                ->lockForUpdate()
                ->findOrFail($participantId);

            $existing = TransferCompletion::query()
                ->where('alliance_id', $currentAlliance->id)
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

            if ($participant->direction !== TransferDirection::Incoming && $existingRosterEntryId !== null) {
                throw ValidationException::withMessages([
                    'roster_entry_id' => 'An existing roster result can only be selected for an incoming completion.',
                ]);
            }

            $rosterEntry = match ($participant->direction) {
                TransferDirection::Incoming => $this->completeIncoming(
                    $currentAlliance,
                    $actor,
                    $participant,
                    $existingRosterEntryId,
                ),
                TransferDirection::Outgoing => $this->completeOutgoing($currentAlliance, $actor, $participant),
                TransferDirection::Staying => $this->completeStaying($currentAlliance, $participant),
            };

            $completion = TransferCompletion::query()->create([
                'alliance_id' => $currentAlliance->id,
                'transfer_plan_id' => $plan->id,
                'transfer_participant_id' => $participant->id,
                'roster_entry_id' => $rosterEntry->id,
                'direction' => $participant->direction,
                'completed_by_user_id' => $actor->id,
                'completed_at' => now(),
            ]);

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'transfer_completion_id' => (string) $completion->id,
                'direction' => $participant->direction->value,
                'roster_entry_id' => (string) $rosterEntry->id,
            ];

            $this->audit->record(
                'kingdoms.transfer_participant_completed',
                $actor,
                $completion,
                $currentAlliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_participant_completed',
                (string) $currentAlliance->id,
                $completion,
                $metadata,
            );

            return $completion->load(['rosterEntry.player', 'completedBy']);
        });
    }

    private function completeIncoming(
        Alliance $alliance,
        User $actor,
        TransferParticipant $participant,
        ?string $existingRosterEntryId,
    ): AllianceRosterEntry {
        if ($existingRosterEntryId === null || trim($existingRosterEntryId) === '') {
            return $this->saveRoster->handle($alliance, $actor, [
                'name' => (string) $participant->observed_name,
                'game_player_id' => $participant->game_player_id,
                'membership_id' => $participant->membership_id,
                'state' => RosterState::Active,
            ]);
        }

        $entry = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->with('player')
            ->lockForUpdate()
            ->findOrFail($existingRosterEntryId);

        if ($participant->game_player_id !== null
            && $entry->player->game_player_id !== $participant->game_player_id) {
            throw ValidationException::withMessages([
                'roster_entry_id' => 'The selected roster entry does not match the participant stable game-player identifier.',
            ]);
        }

        if ($participant->membership_id !== null
            && $entry->membership_id !== null
            && $entry->membership_id !== $participant->membership_id) {
            throw ValidationException::withMessages([
                'roster_entry_id' => 'The selected roster entry is already linked to a different alliance membership.',
            ]);
        }

        return $this->saveRoster->handle($alliance, $actor, [
            'name' => (string) $entry->observed_name,
            'game_player_id' => $entry->player->game_player_id,
            'membership_id' => $entry->membership_id ?? $participant->membership_id,
            'game_role' => $entry->game_role,
            'state' => $entry->state,
            'joined_at' => $entry->joined_at?->toDateString(),
            'manager_notes' => $entry->manager_notes,
        ], (string) $entry->id);
    }

    private function completeOutgoing(
        Alliance $alliance,
        User $actor,
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

        if ($participant->kingdom_player_id !== null
            && (string) $entry->kingdom_player_id !== (string) $participant->kingdom_player_id) {
            throw ValidationException::withMessages([
                'completion' => 'The participant roster binding no longer matches its captured game identity.',
            ]);
        }

        return $entry;
    }
}
