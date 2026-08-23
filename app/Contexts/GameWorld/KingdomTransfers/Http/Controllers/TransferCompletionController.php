<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CompleteTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCompletion;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\Http\Controller;
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
        AccountIdentityQuery $accounts,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        TransferAuthorization $authorization,
        TransferPlanQuery $plans,
        TransferParticipantQuery $participants,
        RosterEntryQuery $roster,
        PlayerReferenceQuery $players,
    ): Response {
        $scope = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);

        if (! $authorization->allows($scope->playerId, $scope->allianceId, TransferPermission::View)) {
            throw new AuthorizationException;
        }

        $canManage = $authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            TransferPermission::Manage,
        );
        $plan = $plans->currentForAlliance($scope->allianceId);
        $participantRows = $plan === null
            ? collect()
            : $participants->forPlan($scope->allianceId, (string) $plan->id, true);
        $rosterIds = $participantRows
            ->map(static fn (TransferParticipant $participant): ?string => $participant->completion?->roster_entry_id === null
                ? null
                : (string) $participant->completion->roster_entry_id)
            ->filter()
            ->values()
            ->all();
        $rosterById = $roster->byIds($scope->allianceId, $rosterIds);
        $playersById = $players->byIds(array_values(array_unique(array_map(
            static fn (RosterEntryReference $entry): string => $entry->playerId,
            array_values($rosterById),
        ))));

        return Inertia::render('Kingdom/Transfer/Completion', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => $this->alliance($alliance, $kingdom),
            'plan' => $plan === null ? null : [
                'id' => (string) $plan->id,
                'label' => (string) $plan->label,
                'homeKingdom' => (string) $plan->homeKingdom->number,
                'state' => $plan->state->value,
                'completable' => $canManage && $plan->state === TransferPlanState::Locked,
            ],
            'participants' => $participantRows
                ->map(fn (TransferParticipant $participant): array => $this->participant($participant, $rosterById, $playersById))
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
        $scope = $context->scope();
        $complete->handle($scope->allianceId, $scope->playerId, $plan, $participant);

        return back()->with('actionReceipt', $this->receipt('transfer-participant-completed'));
    }

    /** @return array{id: string, name: string, kingdom: string} */
    private function alliance(AllianceReference $alliance, KingdomReference $kingdom): array
    {
        return ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number];
    }

    /**
     * @param  array<string, RosterEntryReference>  $rosterById
     * @param  array<string, PlayerReference>  $playersById
     * @return array<string, mixed>
     */
    private function participant(TransferParticipant $participant, array $rosterById, array $playersById): array
    {
        $completion = $participant->completion;
        $rosterEntry = $completion?->roster_entry_id === null
            ? null
            : ($rosterById[(string) $completion->roster_entry_id] ?? null);
        $rosterPlayer = $rosterEntry instanceof RosterEntryReference
            ? ($playersById[$rosterEntry->playerId] ?? null)
            : null;

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
            'completion' => ! $completion instanceof TransferCompletion
                ? null
                : [
                    'completedAt' => $completion->completed_at->toIso8601String(),
                    'completedBy' => $completion->completedBy === null
                        ? null
                        : ['name' => (string) $completion->completedBy->current_name],
                    'rosterEntry' => ! $rosterEntry instanceof RosterEntryReference
                        ? null
                        : [
                            'id' => $rosterEntry->rosterEntryId,
                            'name' => $rosterEntry->observedName,
                            'state' => $rosterEntry->stateObservedAtRead->value,
                            'gamePlayerId' => $rosterPlayer?->gamePlayerId,
                        ],
                ],
        ];
    }

    private function account(Request $request, AccountIdentityQuery $accounts): AccountIdentity
    {
        $userId = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($userId), 401);

        return $accounts->require((int) $userId);
    }
}
