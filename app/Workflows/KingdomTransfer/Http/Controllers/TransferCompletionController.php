<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Shared\Http\Controller;
use App\Workflows\KingdomTransfer\Access\Enums\TransferPermission;
use App\Workflows\KingdomTransfer\Access\Services\TransferAuthorization;
use App\Workflows\KingdomTransfer\Actions\CompleteTransferParticipant;
use App\Workflows\KingdomTransfer\Enums\TransferPlanState;
use App\Workflows\KingdomTransfer\Models\TransferCompletion;
use App\Workflows\KingdomTransfer\Models\TransferParticipant;
use App\Workflows\KingdomTransfer\Queries\TransferParticipantQuery;
use App\Workflows\KingdomTransfer\Queries\TransferPlanQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransferCompletionController extends Controller
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

        return Inertia::render('Alliance/TransferCompletionManage', [
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
                'completable' => $plan->state === TransferPlanState::Locked,
            ],
            'participants' => $plan === null
                ? []
                : $participants->forPlan($alliance, $plan, true)
                    ->map(fn (TransferParticipant $participant): array => $this->participant($participant))
                    ->all(),
        ]);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        CompleteTransferParticipant $complete,
        string $plan,
        string $participant,
    ): RedirectResponse {
        $complete->handle(
            $context->alliance(),
            $context->player(),
            $plan,
            $participant,
        );

        return back()->with('status', 'transfer-participant-completed');
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
        $completion = $participant->completion;

        return [
            'id' => (string) $participant->id,
            'name' => (string) $participant->observed_name,
            'direction' => $participant->direction->value,
            'readiness' => $participant->readiness_state->value,
            'gamePlayerId' => $participant->game_player_id,
            'destinationKingdom' => $participant->destinationKingdom === null
                ? null
                : (string) $participant->destinationKingdom->number,
            'withdrawnAt' => $participant->withdrawn_at?->toIso8601String(),
            'completion' => $completion instanceof TransferCompletion
                ? [
                    'completedAt' => $completion->completed_at->toIso8601String(),
                    'completedBy' => $completion->completedBy === null
                        ? null
                        : ['name' => (string) $completion->completedBy->current_name],
                    'rosterEntry' => $completion->rosterEntry === null
                        ? null
                        : [
                            'id' => (string) $completion->rosterEntry->id,
                            'name' => (string) $completion->rosterEntry->observed_name,
                            'state' => $completion->rosterEntry->state->value,
                            'gamePlayerId' => $completion->rosterEntry->player->game_player_id,
                        ],
                ]
                : null,
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
