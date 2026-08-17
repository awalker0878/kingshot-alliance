<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Actions\ResolveTransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Actions\TransitionTransferReadiness;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferReadinessTransition;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ): Response {
        $scope = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);

        if (! $authorization->allows($scope->playerId, $scope->allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }

        $plan = $plans->currentForAlliance($scope->allianceId);

        return Inertia::render('Alliance/TransferReadinessManage', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => $this->alliance($alliance, $kingdom),
            'plan' => $plan === null ? null : [
                'id' => (string) $plan->id,
                'label' => (string) $plan->label,
                'homeKingdom' => (string) $plan->homeKingdom->number,
                'state' => $plan->state->value,
                'mutable' => in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true),
            ],
            'participants' => $plan === null
                ? []
                : $participants->forPlan($scope->allianceId, (string) $plan->id, true)
                    ->map(fn (TransferParticipant $participant): array => $this->participant($participant))
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
        /** @var array{readiness: string} $validated */
        $validated = $request->validate([
            'readiness' => ['required', Rule::in(array_column(TransferReadinessState::cases(), 'value'))],
        ]);
        $scope = $context->scope();

        $transition->handle(
            $scope->allianceId,
            $scope->playerId,
            $plan,
            $participant,
            TransferReadinessState::from($validated['readiness']),
        );

        return back()->with('status', 'transfer-readiness-updated');
    }

    public function storeBlocker(
        Request $request,
        AllianceContext $context,
        CreateTransferBlocker $create,
        string $plan,
        string $participant,
    ): RedirectResponse {
        /** @var array{summary: string, details?: string|null} $validated */
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

        return back()->with('status', 'transfer-blocker-created');
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
        $resolve->handle($scope->allianceId, $scope->playerId, $plan, $participant, $blocker);

        return back()->with('status', 'transfer-blocker-resolved');
    }

    /** @return array{id: string, name: string, kingdom: string} */
    private function alliance(AllianceReference $alliance, KingdomReference $kingdom): array
    {
        return ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number];
    }

    /** @return array<string, mixed> */
    private function participant(TransferParticipant $participant): array
    {
        return [
            'id' => (string) $participant->id,
            'name' => (string) $participant->observed_name,
            'direction' => $participant->direction->value,
            'readiness' => $participant->readiness_state->value,
            'groupName' => $participant->group?->name,
            'destinationKingdom' => $participant->destinationKingdom === null
                ? null
                : (string) $participant->destinationKingdom->number,
            'withdrawnAt' => $participant->withdrawn_at?->toIso8601String(),
            'completedAt' => $participant->completion?->completed_at->toIso8601String(),
            'blockers' => $participant->blockers
                ->sortByDesc(static fn (TransferBlocker $blocker): string => $blocker->created_at?->toIso8601String() ?? '')
                ->values()
                ->map(static fn (TransferBlocker $blocker): array => [
                    'id' => (string) $blocker->id,
                    'state' => $blocker->state->value,
                    'summary' => (string) $blocker->summary,
                    'details' => $blocker->details,
                    'createdAt' => $blocker->created_at?->toIso8601String(),
                    'resolvedAt' => $blocker->resolved_at?->toIso8601String(),
                    'createdBy' => $blocker->createdBy === null
                        ? null
                        : ['name' => (string) $blocker->createdBy->current_name],
                    'resolvedBy' => $blocker->resolvedBy === null
                        ? null
                        : ['name' => (string) $blocker->resolvedBy->current_name],
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
                        : ['name' => (string) $transition->actor->current_name],
                ])
                ->all(),
        ];
    }

    private function account(Request $request, AccountIdentityQuery $accounts): AccountIdentity
    {
        $userId = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($userId), 401);

        return $accounts->require((int) $userId);
    }
}
