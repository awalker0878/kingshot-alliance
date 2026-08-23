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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransferPlanController extends Controller
{
    public function index(Request $request, AllianceContext $context, AccountIdentityQuery $accounts, AllianceReferenceQuery $alliances, KingdomReferenceQuery $kingdoms, TransferAuthorization $transferAuthorization, TransferPlanQuery $plans, TransferParticipantQuery $participants, TransferCohortQuery $cohorts): Response
    {
        $s = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($s->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        if (! $transferAuthorization->allows($s->playerId, $s->allianceId, TransferPermission::View)) {
            throw new AuthorizationException;
        }$current = $plans->currentForAlliance($s->allianceId);

        return Inertia::render('Kingdom/Transfer/Index', ['user' => ['name' => $account->name, 'email' => $account->email], 'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number], 'canManage' => $transferAuthorization->allows($s->playerId, $s->allianceId, TransferPermission::Manage), 'plan' => $current === null ? null : $this->plan($current), 'cohorts' => $current === null ? [] : $cohorts->forPlan($s->allianceId, (string) $current->id)->map(fn (TransferCohort $c): array => $this->cohort($c, false))->all(), 'participants' => $current === null ? [] : $participants->forPlan($s->allianceId, (string) $current->id)->map(fn (TransferParticipant $p): array => $this->participant($p, false))->all()]);
    }

    public function manage(Request $request, AllianceContext $context, AccountIdentityQuery $accounts, AllianceReferenceQuery $alliances, KingdomReferenceQuery $kingdoms, TransferAuthorization $authorization, TransferPlanQuery $plans, TransferParticipantQuery $participants, TransferCohortQuery $cohorts, TransferWindowQuery $windows, TransferGroupQuery $groups, TransferKingdomConditionQuery $conditions, RosterEntryQuery $roster, PlayerMembershipQuery $memberships, PlayerReferenceQuery $players): Response
    {
        $s = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($s->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        if (! $authorization->allows($s->playerId, $s->allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }$mutable = $plans->mutableForAlliance($s->allianceId);
        $participantRows = $mutable === null ? collect() : $participants->forPlan($s->allianceId, (string) $mutable->id, true);
        $rosterOptions = $roster->activeOrTracked($s->allianceId);
        $memberIds = $memberships->activePlayerIds($s->allianceId);
        $refs = $players->byIds(array_values(array_unique(array_merge($memberIds, array_map(static fn (RosterEntryReference $e): string => $e->playerId, $rosterOptions)))));
        $windowRows = $windows->forAlliance($s->allianceId);
        $selectedWindow = $mutable?->window;

        return Inertia::render('Kingdom/Transfer/Manage', ['user' => ['name' => $account->name, 'email' => $account->email], 'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number], 'plans' => $plans->forAlliance($s->allianceId)->map(fn (TransferPlan $p): array => $this->plan($p))->all(), 'mutablePlan' => $mutable === null ? null : $this->plan($mutable), 'windows' => $windowRows->map(fn (TransferWindow $w): array => $this->window($w))->all(), 'officialGroups' => $selectedWindow === null ? [] : $groups->historyForWindow($s->allianceId, (string) $selectedWindow->id)->map(fn (TransferGroup $g): array => $this->officialGroup($g))->all(), 'conditions' => $selectedWindow === null ? [] : $conditions->forWindow($s->allianceId, (string) $selectedWindow->id)->map(fn (TransferKingdomConditionObservation $c): array => $this->condition($c))->all(), 'cohorts' => $mutable === null ? [] : $cohorts->forPlan($s->allianceId, (string) $mutable->id, true)->map(fn (TransferCohort $c): array => $this->cohort($c, true))->all(), 'participants' => $participantRows->map(fn (TransferParticipant $p): array => $this->participant($p, true))->all(), 'rosterOptions' => array_values(array_map(fn (RosterEntryReference $e): array => ['id' => $e->rosterEntryId, 'name' => $e->observedName, 'gamePlayerId' => $refs[$e->playerId]->gamePlayerId ?? null, 'playerId' => $e->playerId], $rosterOptions)), 'players' => array_values(array_map(static fn (string $id): array => ['id' => $id, 'name' => $refs[$id]->currentName ?? $id], $memberIds))]);
    }

    public function store(Request $request, AllianceContext $context, CreateTransferPlan $create): RedirectResponse
    {/** @var array{label:string,transfer_window_id:string} $v */ $v = $request->validate(['label' => ['required', 'string', 'max:160'], 'transfer_window_id' => ['required', 'string', 'ulid']]);
        $s = $context->scope();
        $create->handle($s->allianceId, $s->playerId, $v);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-created'));
    }

    public function open(Request $r, AllianceContext $c, OpenTransferPlan $a, string $plan): RedirectResponse
    {
        $s = $c->scope();
        $a->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-opened'));
    }

    public function lock(Request $r, AllianceContext $c, LockTransferPlan $a, string $plan): RedirectResponse
    {
        $s = $c->scope();
        $a->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-locked'));
    }

    public function close(Request $r, AllianceContext $c, CloseTransferPlan $a, string $plan): RedirectResponse
    {
        $s = $c->scope();
        $a->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-closed'));
    }

    public function cancel(Request $r, AllianceContext $c, CancelTransferPlan $a, string $plan): RedirectResponse
    {
        $s = $c->scope();
        $a->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-cancelled'));
    }

    /** @return array<string,mixed> */
    private function plan(TransferPlan $p): array
    {
        return ['id' => (string) $p->id, 'label' => (string) $p->label, 'homeKingdom' => (string) $p->homeKingdom->number, 'state' => $p->state->value, 'createdAt' => $p->created_at?->toIso8601String(), 'window' => $this->window($p->window)];
    }

    /** @return array<string,mixed> */
    private function window(TransferWindow $w): array
    {
        return ['id' => (string) $w->id, 'label' => $w->label, 'phase' => $w->phaseAt(now('UTC'))->value, 'preTransferStartsAt' => $w->pre_transfer_starts_at->toIso8601String(), 'invitationalStartsAt' => $w->invitational_starts_at->toIso8601String(), 'transferOpensAt' => $w->transfer_opens_at->toIso8601String(), 'endsAt' => $w->ends_at->toIso8601String(), 'sourceType' => $w->source_type->value, 'sourceReference' => $w->source_reference, 'observedAt' => $w->observed_at->toIso8601String()];
    }

    /** @return array<string,mixed> */
    private function cohort(TransferCohort $c, bool $private): array
    {
        $row = ['name' => $c->name, 'direction' => $c->direction->value, 'destinationKingdom' => $c->destinationKingdom === null ? null : (string) $c->destinationKingdom->number, 'coordinator' => $c->coordinator === null ? null : ['name' => $c->coordinator->current_name]];
        if ($private) {
            $row['id'] = (string) $c->id;
            $row['state'] = $c->state->value;
            $row['coordinatorPlayerId'] = $c->coordinator_player_id;
            $row['managerNotes'] = $c->manager_notes;
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private function officialGroup(TransferGroup $g): array
    {
        return ['id' => (string) $g->id, 'officialLabel' => $g->official_label, 'revision' => $g->revision, 'kingdoms' => $g->kingdoms->map(static fn ($k): array => ['id' => (string) $k->id, 'number' => (string) $k->number])->all(), 'sourceType' => $g->source_type->value, 'sourceReference' => $g->source_reference, 'observedAt' => $g->observed_at->toIso8601String(), 'supersededAt' => $g->superseded_at?->toIso8601String()];
    }

    /** @return array<string,mixed> */
    private function condition(TransferKingdomConditionObservation $c): array
    {
        return ['id' => (string) $c->id, 'kingdom' => (string) $c->kingdom->number, 'powerCap' => $c->power_cap, 'classification' => $c->classification->value, 'sourceType' => $c->source_type->value, 'sourceReference' => $c->source_reference, 'observedAt' => $c->observed_at->toIso8601String(), 'isCorrection' => $c->is_correction];
    }

    /** @return array<string,mixed> */
    private function participant(TransferParticipant $p, bool $private): array
    {
        $row = ['id' => (string) $p->id, 'direction' => $p->direction->value, 'readiness' => $p->readiness_state->value, 'name' => $p->observed_name, 'gamePlayerId' => $p->game_player_id, 'sourceKingdom' => $p->sourceKingdom === null ? null : (string) $p->sourceKingdom->number, 'destinationKingdom' => $p->destinationKingdom === null ? null : (string) $p->destinationKingdom->number, 'player' => ['id' => (string) $p->player_id, 'name' => $p->player->current_name], 'cohort' => $p->cohort === null ? null : $this->cohort($p->cohort, false), 'withdrawnAt' => $p->withdrawn_at?->toIso8601String(), 'completedAt' => $p->completion?->completed_at->toIso8601String()];
        if ($private) {
            $row['rosterEntryId'] = $p->roster_entry_id;
            $row['transferCohortId'] = $p->transfer_cohort_id;
            $row['managerNotes'] = $p->manager_notes;
        }

        return $row;
    }

    private function account(Request $r, AccountIdentityQuery $q): AccountIdentity
    {
        $id = $r->user()?->getAuthIdentifier();
        abort_unless(is_numeric($id), 401);

        return $q->require((int) $id);
    }
}
