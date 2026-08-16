<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Shared\Http\Controller;
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
        TransferAuthorization $authorization,
        TransferPlanQuery $plans,
        TransferParticipantQuery $participants,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($context->player(), $alliance, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }

        $plan = $plans->currentForAlliance($alliance);

        return Inertia::render('Alliance/TransferReadinessManage', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => $this->alliance($alliance),
            'plan' => $plan === null ? null : [
                'id' => (string) $plan->id,
                'label' => (string) $plan->label,
                'homeKingdom' => (string) $plan->homeKingdom->number,
                'state' => $plan->state->value,
                'mutable' => in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true),
            ],
            'participants' => $plan === null
                ? []
                : $participants->forPlan($alliance, $plan, true)
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

        $transition->handle(
            $context->alliance(),
            $context->player(),
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

        $create->handle(
            $context->alliance(),
            $context->player(),
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
        $resolve->handle(
            $context->alliance(),
            $context->player(),
            $plan,
            $participant,
            $blocker,
        );

        return back()->with('status', 'transfer-blocker-resolved');
    }

    /** @return array{id: string, name: string, kingdom: string|null} */
    private function alliance(Alliance $alliance): array
    {
        return [
            'id' => (string) $alliance->id,
            'name' => (string) $alliance->name,
            'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
        ];
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

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
