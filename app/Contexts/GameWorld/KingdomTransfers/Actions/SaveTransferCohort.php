<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCohortState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferCohort
{
    public function __construct(private TransferWriteState $writeState, private TransferAuthorization $authority, private ResolveKingdom $kingdoms, private PlayerMembershipQuery $memberships, private PlayerReferenceQuery $players, private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    /** @param array{name:string,direction:TransferDirection,destination_kingdom?:int|string|null,coordinator_player_id?:string|null,manager_notes?:string|null} $attributes */
    public function handle(string $allianceId, string $actorPlayerId, string $planId, array $attributes, ?string $cohortId = null): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $attributes, $cohortId): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->sharedLock()->firstOrFail();
            $this->assertMutable($context->kingdomId(), $plan);
            $cohort = $cohortId === null ? new TransferCohort(['alliance_id' => $allianceId, 'transfer_plan_id' => $plan->id]) : TransferCohort::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $plan->id)->whereKey($cohortId)->lockForUpdate()->firstOrFail();
            if ($cohort->exists && $cohort->state === TransferCohortState::Archived) {
                throw ValidationException::withMessages(['cohort' => 'Archived transfer cohorts cannot be edited.']);
            }
            $name = trim($attributes['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'A transfer cohort name is required.']);
            }
            $direction = $attributes['direction'];
            if (! in_array($direction, [TransferDirection::Incoming, TransferDirection::Outgoing], true)) {
                throw ValidationException::withMessages(['direction' => 'Transfer cohorts coordinate incoming or outgoing participants.']);
            }
            $duplicate = TransferCohort::query()->where('transfer_plan_id', $plan->id)->where('state', TransferCohortState::Active->value)->whereRaw('lower(name) = lower(?)', [$name]);
            if ($cohort->exists) {
                $duplicate->where('id', '<>', $cohort->id);
            } if ($duplicate->exists()) {
                throw ValidationException::withMessages(['name' => 'An active transfer cohort with that name already exists in this plan.']);
            }
            $destination = $direction === TransferDirection::Incoming ? Kingdom::query()->findOrFail($plan->home_kingdom_id) : $this->kingdom($attributes['destination_kingdom'] ?? null);
            $destinationId = $destination instanceof Kingdom ? (string) $destination->id : $destination?->kingdomId;
            if ($direction === TransferDirection::Outgoing && $destinationId === (string) $plan->home_kingdom_id) {
                throw ValidationException::withMessages(['destination_kingdom' => 'An outgoing cohort destination must differ from the plan home Kingdom.']);
            }
            $coordinatorId = $this->coordinatorId($allianceId, $attributes['coordinator_player_id'] ?? null);
            $notes = $this->nullableText($attributes['manager_notes'] ?? null);
            $this->assertAssignedCompatible($allianceId, $plan, $cohort, $direction, $destinationId);
            $isNew = ! $cohort->exists;
            if (! $isNew && $cohort->name === $name && $cohort->direction === $direction && $cohort->destination_kingdom_id === $destinationId && $cohort->coordinator_player_id === $coordinatorId && $cohort->manager_notes === $notes) {
                return;
            }
            $cohort->forceFill(['name' => $name, 'direction' => $direction, 'destination_kingdom_id' => $destinationId, 'state' => TransferCohortState::Active, 'coordinator_player_id' => $coordinatorId, 'manager_notes' => $notes])->save();
            $event = $isNew ? 'kingdoms.transfer_cohort_created' : 'kingdoms.transfer_cohort_updated';
            $metadata = ['alliance_id' => $allianceId, 'transfer_plan_id' => (string) $plan->id, 'transfer_cohort_id' => (string) $cohort->id, 'direction' => $direction->value, 'destination_kingdom_id' => $destinationId, 'coordinator_player_id' => $coordinatorId];
            $this->audit->record($event, $context->actor, $cohort, null, $metadata);
            $this->outbox->record($event, $allianceId, $cohort, $metadata);
        });
    }

    private function assertAssignedCompatible(string $allianceId, TransferPlan $plan, TransferCohort $cohort, TransferDirection $direction, ?string $destinationId): void
    {
        if (! $cohort->exists) {
            return;
        } $rows = TransferParticipant::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $plan->id)->where('transfer_cohort_id', $cohort->id)->whereNull('withdrawn_at')->sharedLock()->get();
        foreach ($rows as $row) {
            if ($row->direction === TransferDirection::Staying || $row->direction !== $direction) {
                throw ValidationException::withMessages(['direction' => 'The cohort direction is incompatible with an assigned Governor.']);
            } if ($direction === TransferDirection::Outgoing && $destinationId !== null && $row->destination_kingdom_id !== $destinationId) {
                throw ValidationException::withMessages(['destination_kingdom' => 'The cohort destination is incompatible with an assigned Governor.']);
            }
        }
    }

    private function coordinatorId(string $allianceId, mixed $playerId): ?string
    {
        $id = is_string($playerId) ? trim($playerId) : '';
        if ($id === '') {
            return null;
        } if (! $this->memberships->isActiveMember($allianceId, $id)) {
            throw ValidationException::withMessages(['coordinator_player_id' => 'The coordinator must be an active Player in this Alliance.']);
        } $this->players->require($id);

        return $id;
    }

    private function kingdom(mixed $number): ?KingdomReference
    {
        try {
            return $this->kingdoms->handle(is_int($number) || is_string($number) ? $number : null);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
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
            throw ValidationException::withMessages(['cohort' => 'Transfer cohorts can only be changed while the plan is Draft or Open.']);
        } if ($allianceKingdomId !== (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages(['cohort' => 'The transfer plan home Kingdom does not match the Alliance Kingdom.']);
        }
    }
}
