<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Kingdoms\Enums\TransferGroupState;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferParticipant
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ResolveKingdom $kingdoms,
        private ResolveTransferPlayer $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{
     *   direction: TransferDirection,
     *   roster_entry_id?: string|null,
     *   name?: string|null,
     *   game_player_id?: string|null,
     *   source_kingdom?: int|string|null,
     *   destination_kingdom?: int|string|null,
     *   manager_notes?: string|null
     * }  $attributes
     */
    public function handle(
        Alliance $alliance,
        Player $actor,
        string $planId,
        array $attributes,
        ?string $participantId = null,
    ): TransferParticipant {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $planId, $attributes, $participantId): TransferParticipant {
            $currentAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $plan = TransferPlan::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($planId);

            $this->assertMutable($currentAlliance, $plan);

            $participant = $participantId === null
                ? new TransferParticipant([
                    'alliance_id' => $currentAlliance->id,
                    'transfer_plan_id' => $plan->id,
                ])
                : TransferParticipant::query()
                    ->where('alliance_id', $currentAlliance->id)
                    ->where('transfer_plan_id', $plan->id)
                    ->lockForUpdate()
                    ->findOrFail($participantId);

            if ($participant->exists && $participant->withdrawn_at !== null) {
                throw ValidationException::withMessages([
                    'participant' => 'Withdrawn transfer participants cannot be edited.',
                ]);
            }

            $direction = $attributes['direction'];
            $values = $direction->isRosterBound()
                ? $this->rosterBoundValues($currentAlliance, $plan, $direction, $attributes, $participant)
                : $this->incomingValues($plan, $attributes, $participant);

            $this->assertGroupCompatibility(
                $currentAlliance,
                $plan,
                $participant,
                $direction,
                $values['destination_kingdom_id'],
            );
            $this->assertUniquePlayer($plan, $values['player_id'], $participant);

            $event = $participant->exists
                ? 'kingdoms.transfer_participant_updated'
                : 'kingdoms.transfer_participant_created';

            $participant->forceFill([
                ...$values,
                'direction' => $direction,
                'manager_notes' => $this->nullableText($attributes['manager_notes'] ?? null),
                'withdrawn_at' => null,
            ])->save();

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'transfer_group_id' => $participant->transfer_group_id,
                'direction' => $participant->direction->value,
                'roster_entry_id' => $participant->roster_entry_id,
                'player_id' => $participant->player_id,
                'source_kingdom_id' => $participant->source_kingdom_id,
                'destination_kingdom_id' => $participant->destination_kingdom_id,
            ];

            $this->audit->record($event, $actor, $participant, $currentAlliance, $metadata);
            $this->outbox->record($event, (string) $currentAlliance->id, $participant, $metadata);

            return $participant->refresh()->load([
                'rosterEntry.player',
                'player',
                'sourceKingdom',
                'destinationKingdom',
                'group.coordinator',
                'group.destinationKingdom',
            ]);
        });
    }

    private function assertUniquePlayer(TransferPlan $plan, string $playerId, TransferParticipant $participant): void
    {
        $duplicate = TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('player_id', $playerId)
            ->whereNull('withdrawn_at');

        if ($participant->exists) {
            $duplicate->where('id', '<>', $participant->id);
        }

        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'player_id' => 'That Player already has an active entry in this transfer cycle.',
            ]);
        }
    }

    private function assertGroupCompatibility(
        Alliance $alliance,
        TransferPlan $plan,
        TransferParticipant $participant,
        TransferDirection $direction,
        ?string $destinationKingdomId,
    ): void {
        if (! $participant->exists || $participant->transfer_group_id === null) {
            return;
        }

        $group = TransferGroup::query()
            ->where('alliance_id', $alliance->id)
            ->where('transfer_plan_id', $plan->id)
            ->where('state', TransferGroupState::Active->value)
            ->lockForUpdate()
            ->find($participant->transfer_group_id);

        if (! $group instanceof TransferGroup) {
            throw ValidationException::withMessages([
                'participant' => 'The assigned transfer group is no longer active. Unassign it before editing this participant.',
            ]);
        }

        if ($direction === TransferDirection::Staying || $group->direction !== $direction) {
            throw ValidationException::withMessages([
                'direction' => 'The participant direction must remain compatible with the assigned transfer group.',
            ]);
        }

        if ($direction === TransferDirection::Outgoing
            && $group->destination_kingdom_id !== null
            && $group->destination_kingdom_id !== $destinationKingdomId) {
            throw ValidationException::withMessages([
                'destination_kingdom' => 'The outgoing participant destination must remain compatible with the assigned transfer group.',
            ]);
        }
    }

    private function assertMutable(Alliance $alliance, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages([
                'participant' => 'Participants can only be changed while the transfer cycle is Draft or Open.',
            ]);
        }

        if ((string) $alliance->kingdom_id !== (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'participant' => 'The transfer cycle must belong to the Alliance Kingdom.',
            ]);
        }
    }

    /** @param array<string, mixed> $attributes @return array<string, string|null> */
    private function rosterBoundValues(
        Alliance $alliance,
        TransferPlan $plan,
        TransferDirection $direction,
        array $attributes,
        TransferParticipant $participant,
    ): array {
        $rosterId = trim((string) ($attributes['roster_entry_id'] ?? ''));
        if ($rosterId === '') {
            throw ValidationException::withMessages([
                'roster_entry_id' => 'Staying and outgoing participants must use an active Alliance roster Player.',
            ]);
        }

        $roster = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->with('player')
            ->find($rosterId);

        if (! $roster instanceof AllianceRosterEntry) {
            throw ValidationException::withMessages([
                'roster_entry_id' => 'The selected roster entry must be active or tracked in this Alliance.',
            ]);
        }

        if ($participant->exists && (string) $participant->player_id !== (string) $roster->player_id) {
            throw ValidationException::withMessages([
                'roster_entry_id' => 'Withdraw and recreate the participant to change the Player identity.',
            ]);
        }

        $destination = null;
        if ($direction === TransferDirection::Outgoing) {
            $destination = $this->kingdom($attributes['destination_kingdom'] ?? null, 'destination_kingdom');
            if ($destination !== null && (string) $destination->id === (string) $plan->home_kingdom_id) {
                throw ValidationException::withMessages([
                    'destination_kingdom' => 'An outgoing destination must be a different Kingdom.',
                ]);
            }
        }

        return [
            'roster_entry_id' => (string) $roster->id,
            'player_id' => (string) $roster->player_id,
            'observed_name' => (string) $roster->observed_name,
            'game_player_id' => $roster->player->game_player_id,
            'source_kingdom_id' => (string) $plan->home_kingdom_id,
            'destination_kingdom_id' => $destination === null ? null : (string) $destination->id,
        ];
    }

    /** @param array<string, mixed> $attributes @return array<string, string|null> */
    private function incomingValues(
        TransferPlan $plan,
        array $attributes,
        TransferParticipant $participant,
    ): array {
        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'An incoming participant name is required.']);
        }

        $source = $this->kingdom($attributes['source_kingdom'] ?? null, 'source_kingdom');
        if ($source === null) {
            throw ValidationException::withMessages(['source_kingdom' => 'An incoming source Kingdom is required.']);
        }
        if ((string) $source->id === (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'source_kingdom' => 'An incoming source Kingdom must differ from the plan home Kingdom.',
            ]);
        }

        $gamePlayerId = $this->nullableLine($attributes['game_player_id'] ?? null);
        $player = $this->players->handle(
            $source,
            $name,
            $gamePlayerId,
            $participant->exists ? (string) $participant->player_id : null,
        );

        if ($participant->exists && (string) $participant->player_id !== (string) $player->id) {
            throw ValidationException::withMessages([
                'player_id' => 'Withdraw and recreate the participant to change the Player identity.',
            ]);
        }

        return [
            'roster_entry_id' => null,
            'player_id' => (string) $player->id,
            'observed_name' => $name,
            'game_player_id' => $player->game_player_id,
            'source_kingdom_id' => (string) $source->id,
            'destination_kingdom_id' => (string) $plan->home_kingdom_id,
        ];
    }

    private function kingdom(mixed $number, string $field): ?Kingdom
    {
        try {
            return $this->kingdoms->handle(is_int($number) || is_string($number) ? $number : null);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            throw ValidationException::withMessages([
                $field => is_string($message) ? $message : 'The selected Kingdom is invalid.',
            ]);
        }
    }

    private function nullableLine(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
