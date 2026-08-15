<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Kingdoms\Actions\CancelTransferPlan;
use App\Domain\Kingdoms\Actions\CloseTransferPlan;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Actions\LockTransferPlan;
use App\Domain\Kingdoms\Actions\OpenTransferPlan;
use App\Domain\Kingdoms\Models\TransferBlocker;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Kingdoms\Models\TransferReadinessTransition;
use App\Domain\Kingdoms\Queries\RosterQuery;
use App\Domain\Kingdoms\Queries\TransferGroupQuery;
use App\Domain\Kingdoms\Queries\TransferParticipantQuery;
use App\Domain\Kingdoms\Queries\TransferPlanQuery;
use App\Shared\Http\Controller;
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
        AllianceAuthorization $authorization,
        TransferPlanQuery $plans,
        TransferParticipantQuery $participants,
        TransferGroupQuery $groups,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($context->player(), $alliance, PermissionKey::AllianceView)) {
            throw new AuthorizationException;
        }

        $current = $plans->currentForAlliance($alliance);

        return Inertia::render('Alliance/TransferPlans', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => $this->alliance($alliance),
            'canManage' => $authorization->allows($context->player(), $alliance, PermissionKey::KingdomManage),
            'plan' => $current === null ? null : $this->plan($current),
            'groups' => $current === null
                ? []
                : $groups->forPlan($alliance, $current)
                    ->map(fn (TransferGroup $group): array => $this->group($group, false))
                    ->all(),
            'participants' => $current === null
                ? []
                : $participants->forPlan($alliance, $current)
                    ->map(fn (TransferParticipant $participant): array => $this->participant($participant, false))
                    ->all(),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        TransferPlanQuery $plans,
        TransferParticipantQuery $participants,
        TransferGroupQuery $groups,
        RosterQuery $roster,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($context->player(), $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        $mutable = $plans->mutableForAlliance($alliance);
        $rosterOptions = $roster->forAlliance($alliance)
            ->filter(static fn (AllianceRosterEntry $entry): bool => in_array(
                $entry->state,
                [RosterState::Active, RosterState::Tracked],
                true,
            ))
            ->map(static fn (AllianceRosterEntry $entry): array => [
                'id' => (string) $entry->id,
                'name' => (string) $entry->observed_name,
                'gamePlayerId' => $entry->player->game_player_id,
                'playerId' => (string) $entry->player_id,
            ])
            ->values()
            ->all();

        $players = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('player:id,current_name')
            ->get()
            ->map(static fn (AllianceMembership $membership): array => [
                'id' => (string) $membership->player_id,
                'name' => (string) $membership->player->current_name,
            ])
            ->values()
            ->all();

        return Inertia::render('Alliance/TransferPlansManage', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => $this->alliance($alliance),
            'plans' => $plans->forAlliance($alliance)
                ->map(fn (TransferPlan $plan): array => $this->plan($plan))
                ->all(),
            'mutablePlan' => $mutable === null ? null : $this->plan($mutable),
            'groups' => $mutable === null
                ? []
                : $groups->forPlan($alliance, $mutable, true)
                    ->map(fn (TransferGroup $group): array => $this->group($group, true))
                    ->all(),
            'participants' => $mutable === null
                ? []
                : $participants->forPlan($alliance, $mutable, true)
                    ->map(fn (TransferParticipant $participant): array => $this->participant($participant, true))
                    ->all(),
            'rosterOptions' => $rosterOptions,
            'players' => $players,
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

        $create->handle($context->alliance(), $context->player(), $validated);

        return back()->with('status', 'transfer-plan-created');
    }

    public function open(
        Request $request,
        AllianceContext $context,
        OpenTransferPlan $open,
        string $plan,
    ): RedirectResponse {
        $open->handle($context->alliance(), $context->player(), $plan);

        return back()->with('status', 'transfer-plan-opened');
    }

    public function lock(
        Request $request,
        AllianceContext $context,
        LockTransferPlan $lock,
        string $plan,
    ): RedirectResponse {
        $lock->handle($context->alliance(), $context->player(), $plan);

        return back()->with('status', 'transfer-plan-locked');
    }

    public function close(
        Request $request,
        AllianceContext $context,
        CloseTransferPlan $close,
        string $plan,
    ): RedirectResponse {
        $close->handle($context->alliance(), $context->player(), $plan);

        return back()->with('status', 'transfer-plan-closed');
    }

    public function cancel(
        Request $request,
        AllianceContext $context,
        CancelTransferPlan $cancel,
        string $plan,
    ): RedirectResponse {
        $cancel->handle($context->alliance(), $context->player(), $plan);

        return back()->with('status', 'transfer-plan-cancelled');
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

    /** @return array<string, mixed> */
    private function participant(TransferParticipant $participant, bool $includePrivate): array
    {
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

        if ($includePrivate) {
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
            $row['completion'] = $completion === null
                ? null
                : [
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
                ];
        }

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

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
