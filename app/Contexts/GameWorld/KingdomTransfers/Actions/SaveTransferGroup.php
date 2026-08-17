<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferGroupState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferGroup
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private ResolveKingdom $kingdoms,
        private PlayerMembershipQuery $memberships,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{name:string,direction:TransferDirection,destination_kingdom?:int|string|null,coordinator_player_id?:string|null,manager_notes?:string|null} $attributes
     */
    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $planId,
        array $attributes,
        ?string $groupId = null,
    ): void {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $attributes, $groupId): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->sharedLock()->firstOrFail();
            $this->assertMutable($context->kingdomId(), $plan);

            $group = $groupId === null
                ? new TransferGroup(['alliance_id' => $allianceId, 'transfer_plan_id' => $plan->id])
                : TransferGroup::query()
                    ->where('alliance_id', $allianceId)
                    ->where('transfer_plan_id', $plan->id)
                    ->whereKey($groupId)
                    ->lockForUpdate()
                    ->firstOrFail();

            if ($group->exists && $group->state === TransferGroupState::Archived) {
                throw ValidationException::withMessages(['group' => 'Archived transfer groups cannot be edited.']);
            }

            $name = trim($attributes['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'A transfer group name is required.']);
            }
            $direction = $attributes['direction'];
            if (! in_array($direction, [TransferDirection::Incoming, TransferDirection::Outgoing], true)) {
                throw ValidationException::withMessages(['direction' => 'Transfer groups can only coordinate incoming or outgoing participants.']);
            }

            $duplicate = TransferGroup::query()
                ->where('transfer_plan_id', $plan->id)
                ->where('state', TransferGroupState::Active->value)
                ->whereRaw('lower(name) = lower(?)', [$name]);
            if ($group->exists) {
                $duplicate->where('id', '<>', $group->id);
            }
            if ($duplicate->exists()) {
                throw ValidationException::withMessages(['name' => 'An active transfer group with that name already exists in this cycle.']);
            }

            $destination = $direction === TransferDirection::Incoming
                ? Kingdom::query()->findOrFail($plan->home_kingdom_id)
                : $this->kingdom($attributes['destination_kingdom'] ?? null);
            if ($direction === TransferDirection::Outgoing && $destination?->kingdomId === $plan->home_kingdom_id) {
                throw ValidationException::withMessages(['destination_kingdom' => 'An outgoing group destination must differ from the plan home Kingdom.']);
            }

            $coordinatorId = $this->coordinatorId($allianceId, $attributes['coordinator_player_id'] ?? null);
            $managerNotes = $this->nullableText($attributes['manager_notes'] ?? null);
            $destinationId = $destination === null
                ? null
                : ($destination instanceof Kingdom ? (string) $destination->id : $destination->kingdomId);
            $this->assertAssignedParticipantsCompatible($allianceId, $plan, $group, $direction, $destinationId);

            $isNew = ! $group->exists;
            if (! $isNew
                && $group->name === $name
                && $group->direction === $direction
                && $group->destination_kingdom_id === $destinationId
                && $group->coordinator_player_id === $coordinatorId
                && $group->manager_notes === $managerNotes) {
                return;
            }

            $group->forceFill([
                'name' => $name,
                'direction' => $direction,
                'destination_kingdom_id' => $destinationId,
                'state' => TransferGroupState::Active,
                'coordinator_player_id' => $coordinatorId,
                'manager_notes' => $managerNotes,
            ])->save();

            $event = $isNew ? 'kingdoms.transfer_group_created' : 'kingdoms.transfer_group_updated';
            $metadata = [
                'alliance_id' => $allianceId,
                'transfer_plan_id' => (string) $plan->id,
                'transfer_group_id' => (string) $group->id,
                'direction' => $direction->value,
                'destination_kingdom_id' => $destinationId,
                'coordinator_player_id' => $coordinatorId,
            ];
            $this->audit->record($event, $context->actor, $group, null, $metadata);
            $this->outbox->record($event, $allianceId, $group, $metadata);
        });
    }

    private function assertAssignedParticipantsCompatible(string $allianceId, TransferPlan $plan, TransferGroup $group, TransferDirection $direction, ?string $destinationId): void
    {
        if (! $group->exists) {
            return;
        }
        $participants = TransferParticipant::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $plan->id)
            ->where('transfer_group_id', $group->id)
            ->whereNull('withdrawn_at')
            ->orderBy('id')
            ->sharedLock()
            ->get();
        foreach ($participants as $participant) {
            if ($participant->direction === TransferDirection::Staying || $participant->direction !== $direction) {
                throw ValidationException::withMessages(['direction' => 'The group direction is incompatible with one or more assigned participants. Move them first.']);
            }
            if ($direction === TransferDirection::Outgoing && $destinationId !== null && $participant->destination_kingdom_id !== $destinationId) {
                throw ValidationException::withMessages(['destination_kingdom' => 'The group destination is incompatible with one or more assigned outgoing participants. Move them first.']);
            }
        }
    }

    private function coordinatorId(string $allianceId, mixed $playerId): ?string
    {
        $playerId = is_string($playerId) ? trim($playerId) : '';
        if ($playerId === '') {
            return null;
        }
        if (! $this->memberships->isActiveMember($allianceId, $playerId)) {
            throw ValidationException::withMessages(['coordinator_player_id' => 'The coordinator must be an active Player in this Alliance.']);
        }
        $this->players->require($playerId);

        return $playerId;
    }

    private function kingdom(mixed $number): ?KingdomReference
    {
        try {
            return $this->kingdoms->handle(is_int($number) || is_string($number) ? $number : null);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            throw ValidationException::withMessages(['destination_kingdom' => is_string($message) ? $message : 'The selected Kingdom is invalid.']);
        }
    }

    private function nullableText(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? null : $value;
    }

    private function assertMutable(string $allianceKingdomId, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages(['group' => 'Transfer groups can only be changed while the transfer cycle is Draft or Open.']);
        }
        if ($allianceKingdomId !== (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages(['group' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.']);
        }
    }
}
