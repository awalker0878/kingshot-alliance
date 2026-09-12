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
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferObservationHistoryQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferKingdomCapacityProjection;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferRequirement;
use App\Shared\Infrastructure\Http\Controller;
use App\Shared\Infrastructure\Pagination\PageSlice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
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
            TransferPermission::View,
        )) {
            throw new AuthorizationException;
        }

        $canManage = $authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            TransferPermission::Manage,
        );
        /** @var array{participant_cursor?:string|null} $input */
        $input = $request->validate(['participant_cursor' => ['nullable', 'string', 'max:4096']]);
        $plan = $plans->currentForAlliance($scope->allianceId);
        $participantPage = $plan === null ? new PageSlice([], null, TransferParticipantQuery::PAGE_SIZE)
            : $participants->page($scope->playerId, $scope->allianceId, (string) $plan->id, true, $input['participant_cursor'] ?? null);
        $rows = collect($participantPage->items);
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
                'mutable' => $canManage && in_array(
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
            'participantSummary' => $plan === null ? null : $participants->summary($scope->playerId, $scope->allianceId, (string) $plan->id, true),
            'participants' => [
                ...$participantPage->toArray(),
                'items' => $rows->map(fn (TransferParticipant $participant): array => $this->participant(
                    $participant, $planning[(string) $participant->id] ?? null,
                ))->all(),
            ],
        ]);
    }

    public function history(Request $request, AllianceContext $context, TransferObservationHistoryQuery $history, string $plan, string $participant): JsonResponse
    {
        /** @var array{cursor?:string|null} $validated */
        $validated = $request->validate(['cursor' => ['nullable', 'string', 'max:4096']]);
        $scope = $context->scope();
        $page = $history->forParticipant($scope->playerId, $scope->allianceId, $plan, $participant, $validated['cursor'] ?? null);

        return response()->json([
            ...$page->toArray(),
            'items' => array_map(fn (TransferObservation $row): array => $this->observation($row), $page->items),
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
     *     officialGroup: TransferGroup|null,
     *     targetCondition: TransferKingdomConditionObservation|null,
     *     capacityProjection: TransferKingdomCapacityProjection|null
     * }|null $planning
     * @return array<string, mixed>
     */
    private function participant(TransferParticipant $participant, ?array $planning): array
    {
        $assessment = $planning['assessment'] ?? null;
        $score = $planning['transferScore'] ?? TransferObservedValue::unknown();
        $targetCondition = $planning['targetCondition'] ?? null;
        $capacity = $planning['capacityProjection'] ?? null;
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
            'officialGroup' => ($planning['officialGroup'] ?? null) instanceof TransferGroup
                ? [
                    'label' => $planning['officialGroup']->official_label,
                    'sourceType' => $planning['officialGroup']->source_type->value,
                    'sourceReference' => $planning['officialGroup']->source_reference,
                    'observedAt' => $planning['officialGroup']->observed_at->toIso8601String(),
                ]
                : null,
            'targetCondition' => $targetCondition instanceof TransferKingdomConditionObservation
                ? [
                    'powerCap' => $targetCondition->power_cap,
                    'classification' => $targetCondition->classification?->value,
                    'heroGeneration' => $targetCondition->hero_generation,
                    'truegoldLevel' => $targetCondition->truegold_level,
                    'characterAgeThresholdDays' => $targetCondition->character_age_threshold_days,
                    'sourceType' => $targetCondition->source_type->value,
                    'sourceReference' => $targetCondition->source_reference,
                    'observedAt' => $targetCondition->observed_at->toIso8601String(),
                ]
                : null,
            'capacity' => $capacity instanceof TransferKingdomCapacityProjection
                ? [
                    'state' => $capacity->state->value,
                    'officialTotalCapacity' => $capacity->officialTotalCapacity,
                    'officialOrdinaryInviteCapacity' => $capacity->officialOrdinaryInviteCapacity,
                    'officialTransferOpenCapacity' => $capacity->officialTransferOpenCapacity,
                    'ordinaryInvitesUsed' => $capacity->ordinaryInvitesUsed,
                    'transferOpensUsed' => $capacity->transferOpensUsed,
                    'specialInvitesAvailable' => $capacity->specialInvitesAvailable,
                    'plannedOrdinaryInviteReservations' => $capacity->plannedOrdinaryInviteReservations,
                    'plannedTransferOpenReservations' => $capacity->plannedTransferOpenReservations,
                    'plannedSpecialInviteAllocations' => $capacity->plannedSpecialInviteAllocations,
                    'observedTotalRemaining' => $capacity->totalRemaining()->value,
                    'projectedTotalRemaining' => $capacity->totalRemaining(true)->value,
                    'observedOrdinaryInviteRemaining' => $capacity->ordinaryInviteRemaining()->value,
                    'projectedOrdinaryInviteRemaining' => $capacity->ordinaryInviteRemaining(true)->value,
                    'observedTransferOpenRemaining' => $capacity->transferOpenRemaining()->value,
                    'projectedTransferOpenRemaining' => $capacity->transferOpenRemaining(true)->value,
                    'observedSpecialInvitesAvailable' => $capacity->specialInviteRemaining()->value,
                    'projectedSpecialInvitesAvailable' => $capacity->specialInviteRemaining(true)->value,
                    'sourceType' => $capacity->sourceType?->value,
                    'sourceReference' => $capacity->sourceReference,
                    'observedAt' => $capacity->observedAt?->toIso8601String(),
                ]
                : null,
            'capacityReservation' => $participant->capacityReservation === null
                ? null
                : [
                    'bucket' => $participant->capacityReservation->bucket->value,
                    'state' => $participant->capacityReservation->state->value,
                    'notes' => $participant->capacityReservation->notes,
                ],
            'invitationAllocation' => $participant->invitationAllocation === null
                ? null
                : [
                    'kind' => $participant->invitationAllocation->kind->value,
                    'state' => $participant->invitationAllocation->state->value,
                    'notes' => $participant->invitationAllocation->notes,
                ],
            'transferScore' => $this->observed($score),
            'eligibility' => $assessment instanceof TransferEligibilityAssessment
                ? $this->assessment($assessment)
                : null,
            'observations' => $observations
                ->map(fn (TransferObservation $observation): array => $this->observation($observation))
                ->all(),
            'activeBlockerCount' => (int) $participant->getAttribute('active_blocker_count'),
            'resolvedBlockerCount' => (int) $participant->getAttribute('resolved_blocker_count'),
            'readinessTransitionCount' => (int) $participant->getAttribute('readiness_transition_count'),
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
                : ($observation->kind->usesBooleanValue()
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
