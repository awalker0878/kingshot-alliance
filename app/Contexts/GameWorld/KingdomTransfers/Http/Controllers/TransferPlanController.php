<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CancelTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CloseTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\LockTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\OpenTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferReadinessTransition;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferGroupQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\Http\Controller;
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
        AllianceAuthorization $authorization,
        TransferAuthorization $transferAuthorization,
        TransferPlanQuery $plans,
        TransferParticipantQuery $participants,
        TransferGroupQuery $groups,
    ): Response {
        $scope = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);

        if (! $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::View)) {
            throw new AuthorizationException;
        }

        $current = $plans->currentForAlliance($scope->allianceId);

        return Inertia::render('Alliance/TransferPlans', [
            'user' => $this->user($account),
            'alliance' => $this->alliance($alliance, $kingdom),
            'canManage' => $transferAuthorization->allows(
                $scope->playerId,
                $scope->allianceId,
                TransferPermission::Manage,
            ),
            'plan' => $current === null ? null : $this->plan($current),
            'groups' => $current === null
                ? []
                : $groups->forPlan($scope->allianceId, (string) $current->id)
                    ->map(fn (TransferGroup $group): array => $this->group($group, false))
                    ->all(),
            'participants' => $current === null
                ? []
                : $participants->forPlan($scope->allianceId, (string) $current->id)
                    ->map(fn (TransferParticipant $participant): array => $this->participant($participant, false))
                    ->all(),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AccountIdentityQuery $accounts,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        TransferAuthorization $transferAuthorization,
        TransferPlanQuery $plans,
        TransferParticipantQuery $participants,
        TransferGroupQuery $groups,
        RosterEntryQuery $roster,
        PlayerMembershipQuery $memberships,
        PlayerReferenceQuery $players,
    ): Response {
        $scope = $context->scope();
        $account = $this->account($request, $accounts);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);

        if (! $transferAuthorization->allows($scope->playerId, $scope->allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }

        $mutable = $plans->mutableForAlliance($scope->allianceId);
        $participantRows = $mutable === null
            ? collect()
            : $participants->forPlan($scope->allianceId, (string) $mutable->id, true);
        $rosterOptions = $roster->activeOrTracked($scope->allianceId);
        $completionRosterIds = $participantRows
            ->map(static fn (TransferParticipant $participant): ?string => $participant->completion?->roster_entry_id === null
                ? null
                : (string) $participant->completion->roster_entry_id)
            ->filter()
            ->values()
            ->all();
        $completionRoster = $roster->byIds($scope->allianceId, $completionRosterIds);
        $memberPlayerIds = $memberships->activePlayerIds($scope->allianceId);
        $playerReferences = $players->byIds(array_values(array_unique(array_merge(
            $memberPlayerIds,
            array_map(static fn (RosterEntryReference $entry): string => $entry->playerId, $rosterOptions),
            array_map(static fn (RosterEntryReference $entry): string => $entry->playerId, array_values($completionRoster)),
        ))));

        return Inertia::render('Alliance/TransferPlansManage', [
            'user' => $this->user($account),
            'alliance' => $this->alliance($alliance, $kingdom),
            'plans' => $plans->forAlliance($scope->allianceId)
                ->map(fn (TransferPlan $plan): array => $this->plan($plan))
                ->all(),
            'mutablePlan' => $mutable === null ? null : $this->plan($mutable),
            'groups' => $mutable === null
                ? []
                : $groups->forPlan($scope->allianceId, (string) $mutable->id, true)
                    ->map(fn (TransferGroup $group): array => $this->group($group, true))
                    ->all(),
            'participants' => $participantRows
                ->map(fn (TransferParticipant $participant): array => $this->participant(
                    $participant,
                    true,
                    $completionRoster,
                    $playerReferences,
                ))
                ->all(),
            'rosterOptions' => array_values(array_map(
                fn (RosterEntryReference $entry): array => [
                    'id' => $entry->rosterEntryId,
                    'name' => $entry->observedName,
                    'gamePlayerId' => $playerReferences[$entry->playerId]->gamePlayerId ?? null,
                    'playerId' => $entry->playerId,
                ],
                $rosterOptions,
            )),
            'players' => array_values(array_map(
                static fn (string $playerId): array => [
                    'id' => $playerId,
                    'name' => $playerReferences[$playerId]->currentName ?? $playerId,
                ],
                $memberPlayerIds,
            )),
        ]);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        CreateTransferPlan $create,
    ): RedirectResponse {
        /** @var array{label: string, starts_on?: string|null, ends_on?: string|null} $validated */
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);
        $scope = $context->scope();

        $create->handle($scope->allianceId, $scope->playerId, $validated);

        return back()->with('status', 'transfer-plan-created');
    }

    public function open(
        Request $request,
        AllianceContext $context,
        OpenTransferPlan $open,
        string $plan,
    ): RedirectResponse {
        $scope = $context->scope();
        $open->handle($scope->allianceId, $scope->playerId, $plan);

        return back()->with('status', 'transfer-plan-opened');
    }

    public function lock(
        Request $request,
        AllianceContext $context,
        LockTransferPlan $lock,
        string $plan,
    ): RedirectResponse {
        $scope = $context->scope();
        $lock->handle($scope->allianceId, $scope->playerId, $plan);

        return back()->with('status', 'transfer-plan-locked');
    }

    public function close(
        Request $request,
        AllianceContext $context,
        CloseTransferPlan $close,
        string $plan,
    ): RedirectResponse {
        $scope = $context->scope();
        $close->handle($scope->allianceId, $scope->playerId, $plan);

        return back()->with('status', 'transfer-plan-closed');
    }

    public function cancel(
        Request $request,
        AllianceContext $context,
        CancelTransferPlan $cancel,
        string $plan,
    ): RedirectResponse {
        $scope = $context->scope();
        $cancel->handle($scope->allianceId, $scope->playerId, $plan);

        return back()->with('status', 'transfer-plan-cancelled');
    }

    /** @return array{name: string, email: string} */
    private function user(AccountIdentity $account): array
    {
        return ['name' => $account->name, 'email' => $account->email];
    }

    /** @return array{id: string, name: string, kingdom: string} */
    private function alliance(AllianceReference $alliance, KingdomReference $kingdom): array
    {
        return [
            'id' => $alliance->allianceId,
            'name' => $alliance->name,
            'kingdom' => (string) $kingdom->number,
        ];
    }

    /** @return array<string, string|null> */
    private function plan(TransferPlan $plan): array
    {
        return [
            'id' => (string) $plan->id,
            'label' => (string) $plan->label,
            'homeKingdom' => (string) $plan->homeKingdom->number,
            'startsOn' => $plan->starts_on?->toDateString(),
            'endsOn' => $plan->ends_on?->toDateString(),
            'state' => $plan->state->value,
            'createdAt' => $plan->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param array<string, RosterEntryReference> $rosterById
     * @param array<string, PlayerReference> $playersById
     * @return array<string, mixed>
     */
    private function participant(
        TransferParticipant $participant,
        bool $includePrivate,
        array $rosterById = [],
        array $playersById = [],
    ): array {
        $row = [
            'id' => (string) $participant->id,
            'direction' => $participant->direction->value,
            'readiness' => $participant->readiness_state->value,
            'name' => (string) $participant->observed_name,
            'gamePlayerId' => $participant->game_player_id,
            'sourceKingdom' => $participant->sourceKingdom === null
                ? null
                : (string) $participant->sourceKingdom->number,
            'destinationKingdom' => $participant->destinationKingdom === null
                ? null
                : (string) $participant->destinationKingdom->number,
            'player' => [
                'id' => (string) $participant->player_id,
                'name' => (string) $participant->player->current_name,
            ],
            'group' => $participant->group === null
                ? null
                : $this->group($participant->group, false),
            'withdrawnAt' => $participant->withdrawn_at?->toIso8601String(),
            'completedAt' => $participant->completion?->completed_at->toIso8601String(),
        ];

        if (! $includePrivate) {
            return $row;
        }

        $row['rosterEntryId'] = $participant->roster_entry_id;
        $row['transferGroupId'] = $participant->transfer_group_id;
        $row['managerNotes'] = $participant->manager_notes;
        $row['blockers'] = $participant->blockers
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
            ->all();
        $row['readinessHistory'] = $participant->readinessTransitions
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
            ->all();

        $completion = $participant->completion;
        $completionRoster = $completion?->roster_entry_id === null
            ? null
            : ($rosterById[(string) $completion->roster_entry_id] ?? null);
        $completionRosterPlayer = $completionRoster instanceof RosterEntryReference
            ? ($playersById[$completionRoster->playerId] ?? null)
            : null;
        $row['completion'] = $completion === null
            ? null
            : [
                'completedAt' => $completion->completed_at->toIso8601String(),
                'completedBy' => $completion->completedBy === null
                    ? null
                    : ['name' => (string) $completion->completedBy->current_name],
                'rosterEntry' => ! $completionRoster instanceof RosterEntryReference
                    ? null
                    : [
                        'id' => $completionRoster->rosterEntryId,
                        'name' => $completionRoster->observedName,
                        'state' => $completionRoster->stateObservedAtRead->value,
                        'gamePlayerId' => $completionRosterPlayer?->gamePlayerId,
                    ],
            ];

        return $row;
    }

    /** @return array<string, mixed> */
    private function group(TransferGroup $group, bool $includePrivate): array
    {
        $row = [
            'name' => (string) $group->name,
            'direction' => $group->direction->value,
            'destinationKingdom' => $group->destinationKingdom === null
                ? null
                : (string) $group->destinationKingdom->number,
            'coordinator' => $group->coordinator === null
                ? null
                : ['name' => (string) $group->coordinator->current_name],
        ];

        if ($includePrivate) {
            $row['id'] = (string) $group->id;
            $row['state'] = $group->state->value;
            $row['coordinatorPlayerId'] = $group->coordinator_player_id;
            $row['managerNotes'] = $group->manager_notes;
        }

        return $row;
    }

    private function account(Request $request, AccountIdentityQuery $accounts): AccountIdentity
    {
        $userId = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($userId), 401);

        return $accounts->require((int) $userId);
    }
}
