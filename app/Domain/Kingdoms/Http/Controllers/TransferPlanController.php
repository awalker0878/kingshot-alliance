<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CancelTransferPlan;
use App\Domain\Kingdoms\Actions\CloseTransferPlan;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Actions\LockTransferPlan;
use App\Domain\Kingdoms\Actions\OpenTransferPlan;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Kingdoms\Queries\RosterQuery;
use App\Domain\Kingdoms\Queries\TransferGroupQuery;
use App\Domain\Kingdoms\Queries\TransferParticipantQuery;
use App\Domain\Kingdoms\Queries\TransferPlanQuery;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Http\Controllers\Controller;
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

        if (! $authorization->allows($user, $alliance, PermissionKey::AllianceView)) {
            throw new AuthorizationException;
        }

        $current = $plans->currentForAlliance($alliance);

        return Inertia::render('Alliance/TransferPlans', [
            'alliance' => $this->alliance($alliance),
            'canManage' => $authorization->allows($user, $alliance, PermissionKey::KingdomManage),
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

        if (! $authorization->allows($user, $alliance, PermissionKey::KingdomManage)) {
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
                'membershipId' => $entry->membership_id,
            ])
            ->values()
            ->all();

        $memberships = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('user:id,name,email')
            ->orderBy('joined_at')
            ->get()
            ->map(static fn (AllianceMembership $membership): array => [
                'id' => (string) $membership->id,
                'name' => (string) $membership->user?->name,
                'email' => (string) $membership->user?->email,
            ])
            ->values()
            ->all();

        return Inertia::render('Alliance/TransferPlansManage', [
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
            'memberships' => $memberships,
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

        $create->handle($context->alliance(), $this->user($request), $validated);

        return back()->with('status', 'transfer-plan-created');
    }

    public function open(
        Request $request,
        AllianceContext $context,
        OpenTransferPlan $open,
        string $plan,
    ): RedirectResponse {
        $open->handle($context->alliance(), $this->user($request), $plan);

        return back()->with('status', 'transfer-plan-opened');
    }

    public function lock(
        Request $request,
        AllianceContext $context,
        LockTransferPlan $lock,
        string $plan,
    ): RedirectResponse {
        $lock->handle($context->alliance(), $this->user($request), $plan);

        return back()->with('status', 'transfer-plan-locked');
    }

    public function close(
        Request $request,
        AllianceContext $context,
        CloseTransferPlan $close,
        string $plan,
    ): RedirectResponse {
        $close->handle($context->alliance(), $this->user($request), $plan);

        return back()->with('status', 'transfer-plan-closed');
    }

    public function cancel(
        Request $request,
        AllianceContext $context,
        CancelTransferPlan $cancel,
        string $plan,
    ): RedirectResponse {
        $cancel->handle($context->alliance(), $this->user($request), $plan);

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
            'name' => (string) $participant->observed_name,
            'gamePlayerId' => $participant->game_player_id,
            'sourceKingdom' => $participant->sourceKingdom === null
                ? null
                : (string) $participant->sourceKingdom->number,
            'destinationKingdom' => $participant->destinationKingdom === null
                ? null
                : (string) $participant->destinationKingdom->number,
            'membership' => $participant->membership === null
                ? null
                : ['name' => (string) $participant->membership->user?->name],
            'group' => $participant->group === null
                ? null
                : $this->group($participant->group, false),
            'withdrawnAt' => $participant->withdrawn_at?->toIso8601String(),
        ];

        if ($includePrivate) {
            $row['rosterEntryId'] = $participant->roster_entry_id;
            $row['transferGroupId'] = $participant->transfer_group_id;
            $row['managerNotes'] = $participant->manager_notes;
            $row['membership'] = $participant->membership === null
                ? null
                : [
                    'id' => (string) $participant->membership->id,
                    'name' => (string) $participant->membership->user?->name,
                    'email' => (string) $participant->membership->user?->email,
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
                : ['name' => (string) $group->coordinator->user?->name],
        ];

        if ($includePrivate) {
            $row['id'] = (string) $group->id;
            $row['state'] = $group->state->value;
            $row['coordinatorMembershipId'] = $group->coordinator_membership_id;
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
