<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Kingdoms\Enums\TransferGroupState;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferGroup
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ResolveKingdom $kingdoms,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{
     *   name: string,
     *   direction: TransferDirection,
     *   destination_kingdom?: int|string|null,
     *   coordinator_player_id?: string|null,
     *   manager_notes?: string|null
     * } $attributes
     */
    public function handle(
        Alliance $alliance,
        Player $actor,
        string $planId,
        array $attributes,
        ?string $groupId = null,
    ): TransferGroup {
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $planId, $attributes, $groupId): TransferGroup {
            $currentAlliance = Alliance::query()
                ->lockForUpdate()
                ->findOrFail($alliance->id);

            $plan = TransferPlan::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($planId);

            $this->assertMutable($currentAlliance, $plan);

            $group = $groupId === null
                ? new TransferGroup([
                    'alliance_id' => $currentAlliance->id,
                    'transfer_plan_id' => $plan->id,
                ])
                : TransferGroup::query()
                    ->where('alliance_id', $currentAlliance->id)
                    ->where('transfer_plan_id', $plan->id)
                    ->lockForUpdate()
                    ->findOrFail($groupId);

            if ($group->exists && $group->state === TransferGroupState::Archived) {
                throw ValidationException::withMessages([
                    'group' => 'Archived transfer groups cannot be edited.',
                ]);
            }

            $name = trim($attributes['name']);
            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => 'A transfer group name is required.',
                ]);
            }

            $direction = $attributes['direction'];
            if (! in_array($direction, [TransferDirection::Incoming, TransferDirection::Outgoing], true)) {
                throw ValidationException::withMessages([
                    'direction' => 'Transfer groups can only coordinate incoming or outgoing participants.',
                ]);
            }

            $duplicate = TransferGroup::query()
                ->where('transfer_plan_id', $plan->id)
                ->where('state', TransferGroupState::Active->value)
                ->whereRaw('lower(name) = lower(?)', [$name]);

            if ($group->exists) {
                $duplicate->where('id', '<>', $group->id);
            }

            if ($duplicate->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'An active transfer group with that name already exists in this cycle.',
                ]);
            }

            $destination = $direction === TransferDirection::Incoming
                ? $plan->homeKingdom
                : $this->kingdom($attributes['destination_kingdom'] ?? null);

            if ($direction === TransferDirection::Outgoing && $destination?->id === $plan->home_kingdom_id) {
                throw ValidationException::withMessages([
                    'destination_kingdom' => 'An outgoing group destination must differ from the plan home Kingdom.',
                ]);
            }

            $coordinator = $this->coordinator(
                $currentAlliance,
                $attributes['coordinator_player_id'] ?? null,
            );
            $managerNotes = $this->nullableText($attributes['manager_notes'] ?? null);
            $destinationId = $destination === null ? null : (string) $destination->id;
            $coordinatorId = $coordinator === null ? null : (string) $coordinator->id;

            $this->assertAssignedParticipantsCompatible(
                $currentAlliance,
                $plan,
                $group,
                $direction,
                $destinationId,
            );

            $isNew = ! $group->exists;
            if (! $isNew
                && $group->name === $name
                && $group->direction === $direction
                && $group->destination_kingdom_id === $destinationId
                && $group->coordinator_player_id === $coordinatorId
                && $group->manager_notes === $managerNotes) {
                return $group->load(['coordinator:id,current_name', 'destinationKingdom:id,number']);
            }

            $group->forceFill([
                'name' => $name,
                'direction' => $direction,
                'destination_kingdom_id' => $destinationId,
                'state' => TransferGroupState::Active,
                'coordinator_player_id' => $coordinatorId,
                'manager_notes' => $managerNotes,
            ])->save();

            $event = $isNew
                ? 'kingdoms.transfer_group_created'
                : 'kingdoms.transfer_group_updated';
            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_group_id' => (string) $group->id,
                'direction' => $direction->value,
                'destination_kingdom_id' => $destinationId,
                'coordinator_player_id' => $coordinatorId,
            ];

            $this->audit->record($event, $actor, $group, $currentAlliance, $metadata);
            $this->outbox->record($event, (string) $currentAlliance->id, $group, $metadata);

            return $group->refresh()->load([
                'coordinator:id,current_name',
                'destinationKingdom:id,number',
            ]);
        });
    }

    private function assertAssignedParticipantsCompatible(
        Alliance $alliance,
        TransferPlan $plan,
        TransferGroup $group,
        TransferDirection $direction,
        ?string $destinationId,
    ): void {
        if (! $group->exists) {
            return;
        }

        $participants = TransferParticipant::query()
            ->where('alliance_id', $alliance->id)
            ->where('transfer_plan_id', $plan->id)
            ->where('transfer_group_id', $group->id)
            ->whereNull('withdrawn_at')
            ->lockForUpdate()
            ->get();

        foreach ($participants as $participant) {
            if ($participant->direction === TransferDirection::Staying || $participant->direction !== $direction) {
                throw ValidationException::withMessages([
                    'direction' => 'The group direction is incompatible with one or more assigned participants. Move them first.',
                ]);
            }

            if ($direction === TransferDirection::Outgoing
                && $destinationId !== null
                && $participant->destination_kingdom_id !== $destinationId) {
                throw ValidationException::withMessages([
                    'destination_kingdom' => 'The group destination is incompatible with one or more assigned outgoing participants. Move them first.',
                ]);
            }
        }
    }

    private function coordinator(Alliance $alliance, mixed $playerId): ?Player
    {
        $playerId = is_string($playerId) ? trim($playerId) : '';
        if ($playerId === '') {
            return null;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $playerId)
            ->where('status', MembershipStatus::Active->value)
            ->lockForUpdate()
            ->first();

        if (! $membership instanceof AllianceMembership) {
            throw ValidationException::withMessages([
                'coordinator_player_id' => 'The coordinator must be an active Player in this Alliance.',
            ]);
        }

        return Player::query()->findOrFail($playerId);
    }

    private function kingdom(mixed $number): ?Kingdom
    {
        try {
            return $this->kingdoms->handle(is_int($number) || is_string($number) ? $number : null);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            throw ValidationException::withMessages([
                'destination_kingdom' => is_string($message) ? $message : 'The selected Kingdom is invalid.',
            ]);
        }
    }

    private function nullableText(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function assertMutable(Alliance $alliance, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages([
                'group' => 'Transfer groups can only be changed while the transfer cycle is Draft or Open.',
            ]);
        }

        if ($alliance->kingdom_id !== $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'group' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
            ]);
        }
    }
}
