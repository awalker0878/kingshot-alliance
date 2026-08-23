<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Actions\ResolveTransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Actions\TransitionTransferReadiness;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferReadinessTransition;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferRequirement;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class TransferReadinessController extends Controller
{
    public function index(Request $request, AllianceContext $context, AccountIdentityQuery $accounts, AllianceReferenceQuery $alliances, KingdomReferenceQuery $kingdoms, TransferAuthorization $authorization, TransferPlanQuery $plans, TransferParticipantQuery $participants, TransferEligibilityQuery $eligibility): Response
    {
        $scope = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        if (! $authorization->allows($scope->playerId, $scope->allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }$plan = $plans->currentForAlliance($scope->allianceId);
        $rows = $plan === null ? collect() : $participants->forPlan($scope->allianceId, (string) $plan->id, true);
        $planning = $plan === null ? [] : $eligibility->forPlan($scope->allianceId, $plan, $rows);

        return Inertia::render('Kingdom/Transfer/Readiness', ['user' => ['name' => $account->name, 'email' => $account->email], 'alliance' => $this->alliance($alliance, $kingdom), 'plan' => $plan === null ? null : ['id' => (string) $plan->id, 'label' => $plan->label, 'homeKingdom' => (string) $plan->homeKingdom->number, 'state' => $plan->state->value, 'mutable' => in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true), 'window' => ['id' => (string) $plan->window->id, 'label' => $plan->window->label, 'phase' => $plan->window->phaseAt(now('UTC'))->value, 'preTransferStartsAt' => $plan->window->pre_transfer_starts_at->toIso8601String(), 'invitationalStartsAt' => $plan->window->invitational_starts_at->toIso8601String(), 'transferOpensAt' => $plan->window->transfer_opens_at->toIso8601String(), 'endsAt' => $plan->window->ends_at->toIso8601String(), 'sourceType' => $plan->window->source_type->value, 'sourceReference' => $plan->window->source_reference, 'observedAt' => $plan->window->observed_at->toIso8601String()]], 'participants' => $rows->map(fn (TransferParticipant $p): array => $this->participant($p, $planning[(string) $p->id] ?? null))->all()]);
    }

    public function transition(Request $request, AllianceContext $context, TransitionTransferReadiness $transition, string $plan, string $participant): RedirectResponse
    {/** @var array{readiness:string} $v */ $v = $request->validate(['readiness' => ['required', Rule::in(array_column(TransferReadinessState::cases(), 'value'))]]);
        $s = $context->scope();
        $transition->handle($s->allianceId, $s->playerId, $plan, $participant, TransferReadinessState::from($v['readiness']));

        return back()->with('actionReceipt', $this->receipt('transfer-readiness-updated'));
    }

    public function storeBlocker(Request $request, AllianceContext $context, CreateTransferBlocker $create, string $plan, string $participant): RedirectResponse
    {/** @var array{summary:string,details?:string|null} $v */ $v = $request->validate(['summary' => ['required', 'string', 'max:255'], 'details' => ['nullable', 'string', 'max:5000']]);
        $s = $context->scope();
        $create->handle($s->allianceId, $s->playerId, $plan, $participant, $v['summary'], $v['details'] ?? null);

        return back()->with('actionReceipt', $this->receipt('transfer-blocker-created'));
    }

    public function resolveBlocker(Request $request, AllianceContext $context, ResolveTransferBlocker $resolve, string $plan, string $participant, string $blocker): RedirectResponse
    {
        $s = $context->scope();
        $resolve->handle($s->allianceId, $s->playerId, $plan, $participant, $blocker);

        return back()->with('actionReceipt', $this->receipt('transfer-blocker-resolved'));
    }

    /** @return array{id:string,name:string,kingdom:string} */
    private function alliance(AllianceReference $a, KingdomReference $k): array
    {
        return ['id' => $a->allianceId, 'name' => $a->name, 'kingdom' => (string) $k->number];
    }

    /** @param array{assessment:TransferEligibilityAssessment,transferScore:TransferObservedValue,observations:Collection<int,TransferObservation>,officialGroup:?string,targetCondition:?TransferKingdomConditionObservation}|null $planning @return array<string,mixed> */
    private function participant(TransferParticipant $p, ?array $planning): array
    {
        $assessment = $planning['assessment'] ?? null;
        $score = $planning['transferScore'] ?? TransferObservedValue::unknown();

        return ['id' => (string) $p->id, 'name' => $p->observed_name, 'direction' => $p->direction->value, 'readiness' => $p->readiness_state->value, 'cohortName' => $p->cohort?->name, 'destinationKingdom' => $p->destinationKingdom === null ? null : (string) $p->destinationKingdom->number, 'sourceKingdom' => $p->sourceKingdom === null ? null : (string) $p->sourceKingdom->number, 'withdrawnAt' => $p->withdrawn_at?->toIso8601String(), 'completedAt' => $p->completion?->completed_at->toIso8601String(), 'officialGroup' => $planning['officialGroup'] ?? null, 'targetCondition' => ! isset($planning['targetCondition']) || $planning['targetCondition'] === null ? null : ['powerCap' => $planning['targetCondition']->power_cap, 'classification' => $planning['targetCondition']->classification->value, 'sourceType' => $planning['targetCondition']->source_type->value, 'sourceReference' => $planning['targetCondition']->source_reference, 'observedAt' => $planning['targetCondition']->observed_at->toIso8601String()], 'transferScore' => $this->observed($score), 'eligibility' => $assessment instanceof TransferEligibilityAssessment ? $this->assessment($assessment) : null, 'observations' => ! isset($planning['observations']) ? [] : $planning['observations']->map(fn (TransferObservation $o): array => $this->observation($o))->all(), 'blockers' => $p->blockers->sortByDesc(static fn (TransferBlocker $b): string => $b->created_at?->toIso8601String() ?? '')->values()->map(static fn (TransferBlocker $b): array => ['id' => (string) $b->id, 'state' => $b->state->value, 'summary' => $b->summary, 'details' => $b->details, 'createdAt' => $b->created_at?->toIso8601String(), 'resolvedAt' => $b->resolved_at?->toIso8601String(), 'createdBy' => $b->createdBy === null ? null : ['name' => $b->createdBy->current_name], 'resolvedBy' => $b->resolvedBy === null ? null : ['name' => $b->resolvedBy->current_name]])->all(), 'readinessHistory' => $p->readinessTransitions->sortByDesc(static fn (TransferReadinessTransition $t): string => $t->created_at->toIso8601String())->values()->map(static fn (TransferReadinessTransition $t): array => ['from' => $t->from_state?->value, 'to' => $t->to_state->value, 'changedAt' => $t->created_at->toIso8601String(), 'actor' => $t->actor === null ? null : ['name' => $t->actor->current_name]])->all()];
    }

    /** @return array<string,mixed> */
    private function assessment(TransferEligibilityAssessment $a): array
    {
        return ['outcome' => $a->outcome->value, 'primaryAction' => $a->primaryAction, 'evaluatedAt' => $a->evaluatedAt->toIso8601String(), 'requirements' => array_map(fn (TransferRequirement $r): array => ['key' => $r->key->value, 'state' => $r->state->value, 'explanation' => $r->explanation, 'actual' => $r->actual, 'required' => $r->required, 'nextAction' => $r->nextAction, 'sourceType' => $r->sourceType?->value, 'sourceReference' => $r->sourceReference, 'observedAt' => $r->observedAt?->toIso8601String(), 'validUntil' => $r->validUntil?->toIso8601String()], $a->requirements)];
    }

    /** @return array<string,mixed> */
    private function observed(TransferObservedValue $v): array
    {
        return ['state' => $v->state->value, 'value' => $v->value, 'sourceType' => $v->sourceType?->value, 'sourceReference' => $v->sourceReference, 'observedAt' => $v->observedAt?->toIso8601String(), 'validUntil' => $v->validUntil?->toIso8601String(), 'details' => $v->details];
    }

    /** @return array<string,mixed> */
    private function observation(TransferObservation $o): array
    {
        return ['id' => (string) $o->id, 'kind' => $o->kind->value, 'value' => $o->kind->usesNumericValue() ? $o->numeric_value : ($o->kind->value === 'in_game_rules_verified' ? $o->boolean_value : $o->text_value), 'details' => $o->details, 'targetKingdom' => $o->targetKingdom === null ? null : (string) $o->targetKingdom->number, 'sourceType' => $o->source_type->value, 'sourceReference' => $o->source_reference, 'observedAt' => $o->observed_at->toIso8601String(), 'validUntil' => $o->valid_until?->toIso8601String()];
    }

    private function account(Request $r,AccountIdentityQuery $q): AccountIdentity
    {
        $id = $r->user()?->getAuthIdentifier();
        abort_unless(is_numeric($id),401);

        return $q->require((int) $id);
    }
}
