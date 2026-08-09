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
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferParticipant
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ResolveKingdom $kingdoms,
        private ResolveTransferKingdomPlayer $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{
     *   direction: TransferDirection,
     *   roster_entry_id?: string|null,
     *   name?: string|null,
     *   game_player_id?: string|null,
     *   membership_id?: string|null,
     *   source_kingdom?: int|string|null,
     *   destination_kingdom?: int|string|null,
     *   manager_notes?: string|null
     * }  $attributes
     */
    public function handle(
        Alliance $alliance,
        User $actor,
        string $planId,
        array $attributes,
        ?string $participantId = null,
    ): TransferParticipant {
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $planId, $attributes, $participantId): TransferParticipant {
            $currentAlliance = Alliance::query()
                ->lockForUpdate()
                ->findOrFail($alliance->id);

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
            $newRosterBound = $direction->isRosterBound();

            if ($participant->exists && (($participant->roster_entry_id !== null) !== $newRosterBound)) {
                throw ValidationException::withMessages([
                    'direction' => 'Withdraw and recreate the participant to change between incoming and roster-bound planning.',
                ]);
            }

            if ($newRosterBound) {
                $values = $this->rosterBoundValues($currentAlliance, $plan, $direction, $attributes, $participant);
            } else {
                $values = $this->incomingValues($currentAlliance, $plan, $attributes, $participant);
            }

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
                'direction' => $participant->direction->value,
                'roster_entry_id' => $participant->roster_entry_id,
                'kingdom_player_id' => $participant->kingdom_player_id,
                'membership_id' => $participant->membership_id,
                'source_kingdom_id' => $participant->source_kingdom_id,
                'destination_kingdom_id' => $participant->destination_kingdom_id,
            ];

            $this->audit->record($event, $actor, $participant, $currentAlliance, $metadata);
            $this->outbox->record($event, (string) $currentAlliance->id, $participant, $metadata);

            return $participant->refresh()->load([
                'rosterEntry.player',
                'membership.user',
                'sourceKingdom',
                'destinationKingdom',
            ]);
        });
    }

    private function assertMutable(Alliance $alliance, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages([
                'participant' => 'Participants can only be changed while the transfer cycle is Draft or Open.',
            ]);
        }

        if ($alliance->kingdom_id !== $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'participant' => 'The alliance Kingdom changed after this transfer cycle was created. Cancel the stale cycle before changing participants.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
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
                'roster_entry_id' => 'Staying and outgoing participants must use an active alliance roster entry.',
            ]);
        }

        $roster = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->with('player')
            ->find($rosterId);

        if (! $roster instanceof AllianceRosterEntry) {
            throw ValidationException::withMessages([
                'roster_entry_id' => 'The selected roster entry must be active or tracked in this alliance.',
            ]);
        }

        if ($participant->exists
            && $participant->roster_entry_id !== null
            && $participant->roster_entry_id !== (string) $roster->id) {
            throw ValidationException::withMessages([
                'roster_entry_id' => 'Withdraw and recreate the participant to change the roster identity.',
            ]);
        }

        $duplicate = TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('roster_entry_id', $roster->id)
            ->whereNull('withdrawn_at');

        if ($participant->exists) {
            $duplicate->where('id', '<>', $participant->id);
        }

        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'roster_entry_id' => 'That roster player already has an active entry in this transfer cycle.',
            ]);
        }

        $destination = null;
        if ($direction === TransferDirection::Outgoing) {
            $destination = $this->kingdom($attributes['destination_kingdom'] ?? null, 'destination_kingdom');
            if ($destination?->id === $plan->home_kingdom_id) {
                throw ValidationException::withMessages([
                    'destination_kingdom' => 'An outgoing destination must differ from the plan home Kingdom.',
                ]);
            }
        }

        return [
            'roster_entry_id' => (string) $roster->id,
            'kingdom_player_id' => (string) $roster->kingdom_player_id,
            'membership_id' => $roster->membership_id,
            'observed_name' => (string) $roster->observed_name,
            'game_player_id' => $roster->player->game_player_id,
            'source_kingdom_id' => (string) $plan->home_kingdom_id,
            'destination_kingdom_id' => $destination === null ? null : (string) $destination->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    private function incomingValues(
        Alliance $alliance,
        TransferPlan $plan,
        array $attributes,
        TransferParticipant $participant,
    ): array {
        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'An incoming participant name is required.',
            ]);
        }

        $membership = $this->membership($alliance, $attributes['membership_id'] ?? null);
        $source = $this->kingdom($attributes['source_kingdom'] ?? null, 'source_kingdom');
        if ($source?->id === $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'source_kingdom' => 'An incoming source Kingdom must differ from the plan home Kingdom.',
            ]);
        }

        $gamePlayerId = $this->nullableLine($attributes['game_player_id'] ?? null);

        if ($participant->exists) {
            if ($participant->game_player_id !== null
                && $gamePlayerId !== $participant->game_player_id) {
                throw ValidationException::withMessages([
                    'game_player_id' => 'Withdraw and recreate the participant to change a known game-player identifier.',
                ]);
            }

            if ($participant->source_kingdom_id !== null
                && $source?->id !== $participant->source_kingdom_id) {
                throw ValidationException::withMessages([
                    'source_kingdom' => 'Withdraw and recreate the participant to change a known source Kingdom.',
                ]);
            }
        }

        $player = $source === null
            ? null
            : $this->players->handle($source, $name, $gamePlayerId);

        if ($participant->exists
            && $participant->kingdom_player_id !== null
            && $player?->id !== $participant->kingdom_player_id) {
            throw ValidationException::withMessages([
                'game_player_id' => 'Withdraw and recreate the participant to change the resolved game identity.',
            ]);
        }

        if ($player !== null) {
            $duplicate = TransferParticipant::query()
                ->where('transfer_plan_id', $plan->id)
                ->whereNull('roster_entry_id')
                ->where('kingdom_player_id', $player->id)
                ->whereNull('withdrawn_at');

            if ($participant->exists) {
                $duplicate->where('id', '<>', $participant->id);
            }

            if ($duplicate->exists()) {
                throw ValidationException::withMessages([
                    'game_player_id' => 'That known game player already has an active incoming entry in this transfer cycle.',
                ]);
            }
        }

        return [
            'roster_entry_id' => null,
            'kingdom_player_id' => $player === null ? null : (string) $player->id,
            'membership_id' => $membership === null ? null : (string) $membership->id,
            'observed_name' => $name,
            'game_player_id' => $gamePlayerId,
            'source_kingdom_id' => $source === null ? null : (string) $source->id,
            'destination_kingdom_id' => (string) $plan->home_kingdom_id,
        ];
    }

    private function membership(Alliance $alliance, mixed $membershipId): ?AllianceMembership
    {
        $membershipId = is_string($membershipId) ? trim($membershipId) : '';
        if ($membershipId === '') {
            return null;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->find($membershipId);

        if (! $membership instanceof AllianceMembership) {
            throw ValidationException::withMessages([
                'membership_id' => 'The linked membership must be active in this alliance.',
            ]);
        }

        return $membership;
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
