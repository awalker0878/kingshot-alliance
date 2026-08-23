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
    public function index(
        Request $request,
        AllianceContext $context,
        AccountIdentityQuery $accounts,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        TransferAuthorization $authorization,
        TransferPlanQuery $plans,
        TransferParticipantQuery $participants,
        TransferEligibilityQuery $eligibility,
    ): Response {
        $scope = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);

        if (! $authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            TransferPermission::Manage,
        )) {
            throw new AuthorizationException;
        }

        $plan = $plans->currentForAlliance($scope->allianceId);
        $rows = $plan === null
            ? collect()
            : $participants->forPlan($scope->allianceId, (string) $plan->id, true);
        $planning = $plan === null
            ? []
            : $eligibility->forPlan($scope->allianceId, $plan, $rows);

        return Inertia::render('Kingdom/Transfer/Readiness', [
            'user' => [
                'name' => $account->name,
                'email' => $account->email,
            ],
            'alliance' => $this->alliance($alliance, $kingdom),
            'plan' => $plan === null ? null : [
                'id' => (string) $plan->id,
                'label' => $plan->label,
                'homeKingdom' => (string) $plan->homeKingdom->number,
                'state' => $plan->state->value,
                'mutable' => in_array(
                    $plan->state,
                    [TransferPlanState::Draft, TransferPlanState::Open],
                    true,
                ),
                'window' => [
                    'id' => (string) $plan->window->id,
                    'label' => $plan->window->label,
                    'phase' => $plan->window->phaseAt(now('UTC'))->value,
                    'preTransferStartsAt' => $plan->window->pre_transfer_starts_at->toIso8601String(),
                    'invitationalStartsAt' => $plan->window->invitational_starts_at->toIso8601String(),
                    'transferOpensAt' => $plan->window->transfer_opens_at->toIso8601String(),
                    'endsAt' => $plan->window->ends_at->toIso8601String(),
                    'sourceType' => $plan->window->source_type->value,
                    'sourceReference' => $plan->window->source_reference,
                    'observedAt' => $plan->window->observed_at->toIso8601String(),
                ],
            ],
            'participants' => $rows
                ->map(fn (TransferParticipant $participant): array => $this->participant(
                    $participant,
                    $planning[(string) $participant->id] ?? null,
                ))
                ->all(),
        ]);
    }

    public function transition(
        Request $request,
        AllianceContext $context,
        TransitionTransferReadiness $transition,
        string $plan,
        string $participant,
    ): RedirectResponse {
        /** @var array{readiness:string} $validated */
        $validated = $request->validate([
            'readiness' => [
                'required',
                Rule::in(array_column(TransferReadinessState::cases(), 'value')),
            ],
        ]);
        $scope = $context->scope();
        $transition->handle(
            $scope->allianceId,
            $scope->playerId,
            $plan,
            $participant,
            TransferReadinessState::from($validated['readiness']),
        );

        return back()->with(
            'actionReceipt',
            $this->receipt('transfer-readiness-updated'),
        );
    }

    public function storeBlocker(
        Request $request,
        AllianceContext $context,
        CreateTransferBlocker $create,
        string $plan,
        string $participant,
    ): RedirectResponse {
        /** @var array{summary:string,details?:string|null} $validated */
        $validated = $request->validate([
            'summary' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
        ]);
        $scope = $context->scope();
        $create->handle(
            $scope->allianceId,
            $scope->playerId,
            $plan,
            $participant,
            $validated['summary'],
            $validated['details'] ?? null,
        );

        return back()->with(
            'actionReceipt',
            $this->receipt('transfer-blocker-created'),
        );
    }

    public function resolveBlocker(
        Request $request,
        AllianceContext $context,
        ResolveTransferBlocker $resolve,
        string $plan,
        string $participant,
        string $blocker,
    ): RedirectResponse {
        $scope = $context->scope();
        $resolve->handle(
            $scope->allianceId,
            $scope->playerId,
            $plan,
            $participant,
            $blocker,
        );

        return back()->with(
            'actionReceipt',
            $this->receipt('transfer-blocker-resolved'),
        );
    }

    /** @return array{id:string,name:string,kingdom:string} */
    private function alliance(AllianceReference $alliance, KingdomReference $kingdom): array
    {
        return [
            'id' => $alliance->allianceId,
            'name' => $alliance->name,
            'kingdom' => (string) $kingdom->number,
        ];
    }

    /**
     * @param array{
     *     assessment: TransferEligibilityAssessment,
     *     transferScore: TransferObservedValue,
     *     observations: Collection<int, TransferObservation>,
     *     officialGroup: string|null,
     *     targetCondition: TransferKingdomConditionObservation|null
     * }|null $planning
     * @return array<string, mixed>
     */
    private function participant(TransferParticipant $participant, ?array $planning): array
    {
        $assessment = $planning['assessment'] ?? null;
        $score = $planning['transferScore'] ?? TransferObservedValue::unknown();
        $targetCondition = $planning['targetCondition'] ?? null;
        $observations = $planning['observations'] ?? collect();

        return [
            'id' => (string) $participant->id,
            'name' => $participant->observed_name,
            'direction' => $participant->direction->value,
            'readiness' => $participant->readiness_state->value,
            'cohortName' => $participant->cohort?->name,
            'destinationKingdom' => $participant->destinationKingdom === null
                ? null
                : (string) $participant->destinationKingdom->number,
            'sourceKingdom' => $participant->sourceKingdom === null
                ? null
                : (string) $participant->sourceKingdom->number,
            'withdrawnAt' => $participant->withdrawn_at?->toIso8601String(),
            'completedAt' => $participant->completion?->completed_at->toIso8601String(),
            'officialGroup' => $planning['officialGroup'] ?? null,
            'targetCondition' => $targetCondition instanceof TransferKingdomConditionObservation
                ? [
                    'powerCap' => $targetCondition->power_cap,
                    'classification' => $targetCondition->classification->value,
                    'sourceType' => $targetCondition->source_type->value,
                    'sourceReference' => $targetCondition->source_reference,
                    'observedAt' => $targetCondition->observed_at->toIso8601String(),
                ]
                : null,
            'transferScore' => $this->observed($score),
            'eligibility' => $assessment instanceof TransferEligibilityAssessment
                ? $this->assessment($assessment)
                : null,
            'observations' => $observations
                ->map(fn (TransferObservation $observation): array => $this->observation($observation))
                ->all(),
            'blockers' => $participant->blockers
                ->sortByDesc(static fn (TransferBlocker $blocker): string => $blocker->created_at?->toIso8601String() ?? '')
                ->values()
                ->map(static fn (TransferBlocker $blocker): array => [
                    'id' => (string) $blocker->id,
                    'state' => $blocker->state->value,
                    'summary' => $blocker->summary,
                    'details' => $blocker->details,
                    'createdAt' => $blocker->created_at?->toIso8601String(),
                    'resolvedAt' => $blocker->resolved_at?->toIso8601String(),
                    'createdBy' => $blocker->createdBy === null
                        ? null
                        : ['name' => $blocker->createdBy->current_name],
                    'resolvedBy' => $blocker->resolvedBy === null
                        ? null
                        : ['name' => $blocker->resolvedBy->current_name],
                ])
                ->all(),
            'readinessHistory' => $participant->readinessTransitions
                ->sortByDesc(static fn (TransferReadinessTransition $transition): string => $transition->created_at->toIso8601String())
                ->values()
                ->map(static fn (TransferReadinessTransition $transition): array => [
                    'from' => $transition->from_state?->value,
                    'to' => $transition->to_state->value,
                    'changedAt' => $transition->created_at->toIso8601String(),
                    'actor' => $transition->actor === null
                        ? null
                        : ['name' => $transition->actor->current_name],
                ])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function assessment(TransferEligibilityAssessment $assessment): array
    {
        return [
            'outcome' => $assessment->outcome->value,
            'primaryAction' => $assessment->primaryAction,
            'evaluatedAt' => $assessment->evaluatedAt->toIso8601String(),
            'requirements' => array_map(
                fn (TransferRequirement $requirement): array => [
                    'key' => $requirement->key->value,
                    'state' => $requirement->state->value,
                    'explanation' => $requirement->explanation,
                    'actual' => $requirement->actual,
                    'required' => $requirement->required,
                    'nextAction' => $requirement->nextAction,
                    'sourceType' => $requirement->sourceType?->value,
                    'sourceReference' => $requirement->sourceReference,
                    'observedAt' => $requirement->observedAt?->toIso8601String(),
                    'validUntil' => $requirement->validUntil?->toIso8601String(),
                ],
                $assessment->requirements,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function observed(TransferObservedValue $value): array
    {
        return [
            'state' => $value->state->value,
            'value' => $value->value,
            'sourceType' => $value->sourceType?->value,
            'sourceReference' => $value->sourceReference,
            'observedAt' => $value->observedAt?->toIso8601String(),
            'validUntil' => $value->validUntil?->toIso8601String(),
            'details' => $value->details,
        ];
    }

    /** @return array<string, mixed> */
    private function observation(TransferObservation $observation): array
    {
        return [
            'id' => (string) $observation->id,
            'kind' => $observation->kind->value,
            'value' => $observation->kind->usesNumericValue()
                ? $observation->numeric_value
                : ($observation->kind->value === 'in_game_rules_verified'
                    ? $observation->boolean_value
                    : $observation->text_value),
            'details' => $observation->details,
            'targetKingdom' => $observation->targetKingdom === null
                ? null
                : (string) $observation->targetKingdom->number,
            'sourceType' => $observation->source_type->value,
            'sourceReference' => $observation->source_reference,
            'observedAt' => $observation->observed_at->toIso8601String(),
            'validUntil' => $observation->valid_until?->toIso8601String(),
        ];
    }

    private function account(Request $request, AccountIdentityQuery $accounts): AccountIdentity
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($id), 401);

        return $accounts->require((int) $id);
    }
}
