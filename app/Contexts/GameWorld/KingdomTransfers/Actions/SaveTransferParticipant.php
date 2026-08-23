<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
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

final readonly class SaveTransferParticipant
{
    public function __construct(private TransferWriteState $writeState, private TransferAuthorization $authority, private ResolveKingdom $kingdoms, private ResolveTransferPlayer $players, private RosterEntryQuery $roster, private PlayerReferenceQuery $playerReferences, private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    /** @param array{direction:TransferDirection,roster_entry_id?:string|null,name?:string|null,game_player_id?:string|null,source_kingdom?:int|string|null,destination_kingdom?:int|string|null,manager_notes?:string|null} $attributes */
    public function handle(string $allianceId, string $actorPlayerId, string $planId, array $attributes, ?string $participantId = null): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $attributes, $participantId): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->sharedLock()->firstOrFail();
            $this->assertMutable($context->kingdomId(), $plan);
            $routing = $participantId === null ? null : TransferParticipant::query()->select(['id', 'transfer_cohort_id'])->where('alliance_id', $allianceId)->where('transfer_plan_id', $plan->id)->whereKey($participantId)->firstOrFail();
            $cohort = $routing?->transfer_cohort_id === null ? null : TransferCohort::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $plan->id)->whereKey($routing->transfer_cohort_id)->sharedLock()->first();
            $participant = $participantId === null ? new TransferParticipant(['alliance_id' => $allianceId, 'transfer_plan_id' => $plan->id]) : TransferParticipant::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $plan->id)->whereKey($participantId)->lockForUpdate()->firstOrFail();
            if ($participant->exists && $participant->withdrawn_at !== null) {
                throw ValidationException::withMessages(['participant' => 'Withdrawn transfer participants cannot be edited.']);
            }if ($routing !== null && (string) ($participant->transfer_cohort_id ?? '') !== (string) ($routing->transfer_cohort_id ?? '')) {
                throw ValidationException::withMessages(['participant' => 'The participant cohort changed while this edit was prepared. Reload and try again.']);
            }if ($participant->exists && $participant->transfer_cohort_id !== null && ! $cohort instanceof TransferCohort) {
                throw ValidationException::withMessages(['participant' => 'The assigned transfer cohort no longer exists.']);
            }$direction = $attributes['direction'];
            $values = $direction->isRosterBound() ? $this->rosterBoundValues($allianceId, $context->kingdomId(), $plan, $direction, $attributes, $participant) : $this->incomingValues($plan, $attributes, $participant);
            $this->assertCohortCompatibility($cohort, $direction, $values['destination_kingdom_id']);
            $this->assertUniquePlayer($plan, $values['player_id'], $participant);
            $event = $participant->exists ? 'kingdoms.transfer_participant_updated' : 'kingdoms.transfer_participant_created';
            $participant->forceFill([...$values, 'direction' => $direction, 'manager_notes' => $this->nullableText($attributes['manager_notes'] ?? null), 'withdrawn_at' => null])->save();
            $metadata = ['alliance_id' => $allianceId, 'transfer_plan_id' => (string) $plan->id, 'transfer_participant_id' => (string) $participant->id, 'transfer_cohort_id' => $participant->transfer_cohort_id, 'direction' => $participant->direction->value, 'player_id' => $participant->player_id, 'source_kingdom_id' => $participant->source_kingdom_id, 'destination_kingdom_id' => $participant->destination_kingdom_id];
            $this->audit->record($event, $context->actor, $participant, null, $metadata);
            $this->outbox->record($event, $allianceId, $participant, $metadata);
        });
    }

    private function assertUniquePlayer(TransferPlan $plan, string $playerId, TransferParticipant $participant): void
    {
        $q = TransferParticipant::query()->where('transfer_plan_id', $plan->id)->where('player_id', $playerId)->whereNull('withdrawn_at');
        if ($participant->exists) {
            $q->where('id', '<>', $participant->id);
        }if ($q->exists()) {
            throw ValidationException::withMessages(['player_id' => 'That Player already has an active entry in this transfer plan.']);
        }
    }

    private function assertCohortCompatibility(?TransferCohort $cohort, TransferDirection $direction, ?string $destination): void
    {
        if (! $cohort instanceof TransferCohort) {
            return;
        }if ($cohort->state !== TransferCohortState::Active) {
            throw ValidationException::withMessages(['participant' => 'The assigned cohort is no longer active.']);
        }if ($direction === TransferDirection::Staying || $cohort->direction !== $direction) {
            throw ValidationException::withMessages(['direction' => 'The participant direction must remain compatible with the cohort.']);
        }if ($direction === TransferDirection::Outgoing && $cohort->destination_kingdom_id !== null && $cohort->destination_kingdom_id !== $destination) {
            throw ValidationException::withMessages(['destination_kingdom' => 'The destination must remain compatible with the cohort.']);
        }
    }

    private function assertMutable(string $allianceKingdomId, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages(['participant' => 'Participants can only be changed while the plan is Draft or Open.']);
        }if ($allianceKingdomId !== (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages(['participant' => 'The transfer plan must belong to the Alliance Kingdom.']);
        }
    }

    /** @param array<string,mixed> $a @return array{roster_entry_id:string,player_id:string,observed_name:string,game_player_id:?string,source_kingdom_id:string,destination_kingdom_id:?string} */
    private function rosterBoundValues(string $allianceId, string $allianceKingdomId, TransferPlan $plan, TransferDirection $direction, array $a, TransferParticipant $participant): array
    {
        $rosterId = trim((string) ($a['roster_entry_id'] ?? ''));
        if ($rosterId === '') {
            throw ValidationException::withMessages(['roster_entry_id' => 'Staying and outgoing participants must use an Alliance roster Governor.']);
        }$roster = $this->roster->requireActiveOrTracked($allianceId, $rosterId);
        $player = $this->playerReferences->require($roster->playerId);
        if ($player->kingdomId !== $allianceKingdomId || $player->kingdomId !== (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages(['roster_entry_id' => 'The selected Governor no longer belongs to the Alliance Kingdom.']);
        }if ($participant->exists && (string) $participant->player_id !== $player->playerId) {
            throw ValidationException::withMessages(['roster_entry_id' => 'Withdraw and recreate the participant to change Player identity.']);
        }$destination = null;
        if ($direction === TransferDirection::Outgoing) {
            $destination = $this->kingdom($a['destination_kingdom'] ?? null, 'destination_kingdom');
            if ($destination !== null && $destination->kingdomId === (string) $plan->home_kingdom_id) {
                throw ValidationException::withMessages(['destination_kingdom' => 'An outgoing destination must be a different Kingdom.']);
            }
        }

        return ['roster_entry_id' => $roster->rosterEntryId, 'player_id' => $player->playerId, 'observed_name' => $roster->observedName, 'game_player_id' => $player->gamePlayerId, 'source_kingdom_id' => (string) $plan->home_kingdom_id, 'destination_kingdom_id' => $destination?->kingdomId];
    }

    /** @param array<string,mixed> $a @return array{roster_entry_id:null,player_id:string,observed_name:string,game_player_id:?string,source_kingdom_id:string,destination_kingdom_id:string} */
    private function incomingValues(TransferPlan $plan, array $a, TransferParticipant $participant): array
    {
        $name = trim((string) ($a['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'An incoming Governor name is required.']);
        }$source = $this->kingdom($a['source_kingdom'] ?? null, 'source_kingdom');
        if ($source === null || $source->kingdomId === (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages(['source_kingdom' => 'An incoming source Kingdom different from the plan home Kingdom is required.']);
        }$player = $this->players->handle($source->kingdomId, $name, $this->nullableLine($a['game_player_id'] ?? null), $participant->exists ? (string) $participant->player_id : null);
        if ($participant->exists && (string) $participant->player_id !== $player->playerId) {
            throw ValidationException::withMessages(['player_id' => 'Withdraw and recreate the participant to change Player identity.']);
        }

        return ['roster_entry_id' => null, 'player_id' => $player->playerId, 'observed_name' => $name, 'game_player_id' => $player->gamePlayerId, 'source_kingdom_id' => $source->kingdomId, 'destination_kingdom_id' => (string) $plan->home_kingdom_id];
    }

    private function kingdom(mixed $number, string $field): ?KingdomReference
    {
        try {
            return $this->kingdoms->handle(is_int($number) || is_string($number) ? $number : null);
        } catch (ValidationException $e) {
            $m = collect($e->errors())->flatten()->first();
            throw ValidationException::withMessages([$field => is_string($m) ? $m : 'The selected Kingdom is invalid.']);
        }
    }

    private function nullableLine(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : '';

        return $v === '' ? null : $v;
    }

    private function nullableText(mixed $v): ?string
    {
        return $this->nullableLine($v);
    }
}
