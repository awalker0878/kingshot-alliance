<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CancelTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CloseTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\LockTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\OpenTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferCohortQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferGroupQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferKingdomConditionQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferWindowQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\Http\Controller;
use App\Shared\Infrastructure\Pagination\PageSlice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransferPlanController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AccountIdentityQuery $accounts,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        TransferAuthorization $transferAuthorization,
        TransferPlanQuery $plans,
        TransferParticipantQuery $participants,
        TransferCohortQuery $cohorts,
    ): Response {
        $s = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($s->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        if (! $transferAuthorization->allows($s->playerId, $s->allianceId, TransferPermission::View)) {
            throw new AuthorizationException;
        }
        /** @var array{participant_cursor?:string|null} $input */
        $input = $request->validate(['participant_cursor' => ['nullable', 'string', 'max:4096']]);
        $current = $plans->currentForAlliance($s->allianceId);
        $participantPage = $current === null ? new PageSlice([], null, TransferParticipantQuery::PAGE_SIZE) : $participants->page($s->playerId, $s->allianceId, (string) $current->id, false, $input['participant_cursor'] ?? null);

        return Inertia::render('Kingdom/Transfer/Index', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number],
            'canManage' => $transferAuthorization->allows($s->playerId, $s->allianceId, TransferPermission::Manage),
            'plan' => $current === null ? null : $this->plan($current),
            'cohorts' => $current === null ? [] : $cohorts->forPlan($s->allianceId, (string) $current->id)->map(fn (TransferCohort $c): array => $this->cohort($c, false))->all(),
            'participantSummary' => $current === null ? null : $participants->summary($s->playerId, $s->allianceId, (string) $current->id),
            'participants' => [...$participantPage->toArray(), 'items' => array_map(fn (TransferParticipant $p): array => $this->participant($p, false), $participantPage->items)],
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AccountIdentityQuery $accounts,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        TransferAuthorization $authorization,
        TransferPlanQuery $plans,
        TransferParticipantQuery $participants,
        TransferCohortQuery $cohorts,
        TransferWindowQuery $windows,
        TransferGroupQuery $groups,
        TransferKingdomConditionQuery $conditions,
        RosterEntryQuery $roster,
        PlayerMembershipQuery $memberships,
        PlayerReferenceQuery $players,
    ): Response {
        $s = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($s->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        if (! $authorization->allows($s->playerId, $s->allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }
        /** @var array{participant_cursor?:string|null} $input */
        $input = $request->validate(['participant_cursor' => ['nullable', 'string', 'max:4096']]);
        $mutable = $plans->mutableForAlliance($s->allianceId);
        $participantPage = $mutable === null ? new PageSlice([], null, TransferParticipantQuery::PAGE_SIZE) : $participants->page($s->playerId, $s->allianceId, (string) $mutable->id, true, $input['participant_cursor'] ?? null, TransferPermission::Manage);
        $rosterOptions = $roster->activeOrTracked($s->allianceId);
        $memberIds = $memberships->activePlayerIds($s->allianceId);
        $refs = $players->byIds(array_values(array_unique(array_merge(
            $memberIds,
            array_map(static fn (RosterEntryReference $e): string => $e->playerId, $rosterOptions),
        ))));
        $windowRows = $windows->forAlliance($s->allianceId);
        $selectedWindow = $mutable?->window;
        $capacityRows = $selectedWindow === null
            ? collect()
            : TransferKingdomCapacityObservation::query()
                ->where('alliance_id', $s->allianceId)
                ->where('transfer_window_id', $selectedWindow->id)
                ->with('kingdom:id,number')
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->get();

        return Inertia::render('Kingdom/Transfer/Manage', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number],
            'plans' => $plans->forAlliance($s->allianceId)->map(fn (TransferPlan $p): array => $this->plan($p))->all(),
            'mutablePlan' => $mutable === null ? null : $this->plan($mutable),
            'windows' => $windowRows->map(fn (TransferWindow $w): array => $this->window($w))->all(),
            'officialGroups' => $selectedWindow === null ? [] : $groups->historyForWindow($s->allianceId, (string) $selectedWindow->id)->map(fn (TransferGroup $g): array => $this->officialGroup($g))->all(),
            'conditions' => $selectedWindow === null ? [] : $conditions->forWindow($s->allianceId, (string) $selectedWindow->id)->map(fn (TransferKingdomConditionObservation $c): array => $this->condition($c))->all(),
            'capacities' => $capacityRows->map(fn (TransferKingdomCapacityObservation $c): array => $this->capacity($c))->all(),
            'cohorts' => $mutable === null ? [] : $cohorts->forPlan($s->allianceId, (string) $mutable->id, true)->map(fn (TransferCohort $c): array => $this->cohort($c, true))->all(),
            'participantSummary' => $mutable === null ? null : $participants->summary($s->playerId, $s->allianceId, (string) $mutable->id, true, TransferPermission::Manage),
            'participants' => [...$participantPage->toArray(), 'items' => array_map(fn (TransferParticipant $p): array => $this->participant($p, true), $participantPage->items)],
            'rosterOptions' => array_values(array_map(fn (RosterEntryReference $e): array => [
                'id' => $e->rosterEntryId,
                'name' => $e->observedName,
                'gamePlayerId' => $refs[$e->playerId]->gamePlayerId ?? null,
                'playerId' => $e->playerId,
            ], $rosterOptions)),
            'players' => array_values(array_map(static fn (string $id): array => [
                'id' => $id,
                'name' => $refs[$id]->currentName ?? $id,
            ], $memberIds)),
        ]);
    }

    public function store(Request $request, AllianceContext $context, CreateTransferPlan $create): RedirectResponse
    {
        /** @var array{label:string,transfer_window_id:string} $v */
        $v = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'transfer_window_id' => ['required', 'string', 'ulid'],
        ]);
        $s = $context->scope();
        $create->handle($s->allianceId, $s->playerId, $v);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-created'));
    }

    public function open(Request $request, AllianceContext $context, OpenTransferPlan $action, string $plan): RedirectResponse
    {
        $s = $context->scope();
        $action->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-opened'));
    }

    public function lock(Request $request, AllianceContext $context, LockTransferPlan $action, string $plan): RedirectResponse
    {
        $s = $context->scope();
        $action->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-locked'));
    }

    public function close(Request $request, AllianceContext $context, CloseTransferPlan $action, string $plan): RedirectResponse
    {
        $s = $context->scope();
        $action->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-closed'));
    }

    public function cancel(Request $request, AllianceContext $context, CancelTransferPlan $action, string $plan): RedirectResponse
    {
        $s = $context->scope();
        $action->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-cancelled'));
    }

    /** @return array<string,mixed> */
    private function plan(TransferPlan $plan): array
    {
        return [
            'id' => (string) $plan->id,
            'label' => (string) $plan->label,
            'homeKingdom' => (string) $plan->homeKingdom->number,
            'state' => $plan->state->value,
            'createdAt' => $plan->created_at?->toIso8601String(),
            'window' => $this->window($plan->window),
        ];
    }

    /** @return array<string,mixed> */
    private function window(TransferWindow $window): array
    {
        return [
            'id' => (string) $window->id,
            'label' => $window->label,
            'phase' => $window->phaseAt(now('UTC'))->value,
            'preTransferStartsAt' => $window->pre_transfer_starts_at->toIso8601String(),
            'invitationalStartsAt' => $window->invitational_starts_at->toIso8601String(),
            'transferOpensAt' => $window->transfer_opens_at->toIso8601String(),
            'endsAt' => $window->ends_at->toIso8601String(),
            'sourceType' => $window->source_type->value,
            'sourceReference' => $window->source_reference,
            'observedAt' => $window->observed_at->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function cohort(TransferCohort $cohort, bool $private): array
    {
        $row = [
            'name' => $cohort->name,
            'direction' => $cohort->direction->value,
            'destinationKingdom' => $cohort->destinationKingdom === null ? null : (string) $cohort->destinationKingdom->number,
            'coordinator' => $cohort->coordinator === null ? null : ['name' => $cohort->coordinator->current_name],
        ];
        if ($private) {
            $row['id'] = (string) $cohort->id;
            $row['state'] = $cohort->state->value;
            $row['coordinatorPlayerId'] = $cohort->coordinator_player_id;
            $row['managerNotes'] = $cohort->manager_notes;
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private function officialGroup(TransferGroup $group): array
    {
        return [
            'id' => (string) $group->id,
            'officialLabel' => $group->official_label,
            'revision' => $group->revision,
            'kingdoms' => $group->kingdoms->map(static fn ($kingdom): array => [
                'id' => (string) $kingdom->id,
                'number' => (string) $kingdom->number,
            ])->all(),
            'sourceType' => $group->source_type->value,
            'sourceReference' => $group->source_reference,
            'observedAt' => $group->observed_at->toIso8601String(),
            'supersededAt' => $group->superseded_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function condition(TransferKingdomConditionObservation $condition): array
    {
        return [
            'id' => (string) $condition->id,
            'kingdom' => (string) $condition->kingdom->number,
            'powerCap' => $condition->power_cap,
            'classification' => $condition->classification?->value,
            'heroGeneration' => $condition->hero_generation,
            'truegoldLevel' => $condition->truegold_level,
            'characterAgeThresholdDays' => $condition->character_age_threshold_days,
            'sourceType' => $condition->source_type->value,
            'sourceReference' => $condition->source_reference,
            'observedAt' => $condition->observed_at->toIso8601String(),
            'isCorrection' => $condition->is_correction,
        ];
    }

    /** @return array<string,mixed> */
    private function capacity(TransferKingdomCapacityObservation $capacity): array
    {
        return [
            'id' => (string) $capacity->id,
            'kingdom' => (string) $capacity->kingdom->number,
            'ordinaryInvitesUsed' => $capacity->ordinary_invites_used,
            'transferOpensUsed' => $capacity->transfer_opens_used,
            'specialInvitesAvailable' => $capacity->special_invites_available,
            'sourceType' => $capacity->source_type->value,
            'sourceReference' => $capacity->source_reference,
            'observedAt' => $capacity->observed_at->toIso8601String(),
            'isCorrection' => $capacity->is_correction,
        ];
    }

    /** @return array<string,mixed> */
    private function participant(TransferParticipant $participant, bool $private): array
    {
        $row = [
            'id' => (string) $participant->id,
            'direction' => $participant->direction->value,
            'readiness' => $participant->readiness_state->value,
            'name' => $participant->observed_name,
            'gamePlayerId' => $participant->game_player_id,
            'sourceKingdom' => $participant->sourceKingdom === null ? null : (string) $participant->sourceKingdom->number,
            'destinationKingdom' => $participant->destinationKingdom === null ? null : (string) $participant->destinationKingdom->number,
            'player' => ['id' => (string) $participant->player_id, 'name' => $participant->player->current_name],
            'cohort' => $participant->cohort === null ? null : $this->cohort($participant->cohort, false),
            'withdrawnAt' => $participant->withdrawn_at?->toIso8601String(),
            'completedAt' => $participant->completion?->completed_at->toIso8601String(),
        ];
        if ($private) {
            $row['rosterEntryId'] = $participant->roster_entry_id;
            $row['transferCohortId'] = $participant->transfer_cohort_id;
            $row['managerNotes'] = $participant->manager_notes;
        }

        return $row;
    }

    private function account(Request $request, AccountIdentityQuery $accounts): AccountIdentity
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($id), 401);

        return $accounts->require((int) $id);
    }
}
