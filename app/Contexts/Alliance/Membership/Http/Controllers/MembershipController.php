<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Http\Controllers;

use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Membership\Actions\LeaveAlliance;
use App\Contexts\Alliance\Membership\Actions\TransferAllianceLeadership;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MembershipController extends Controller
{
    public function updateStatus(Request $request, AllianceContext $context, UpdateMembershipStatus $updateStatus, string $membership): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(MembershipStatus::class)]]);
        $updateStatus->handle($context->alliance(), $context->player(), $membership, MembershipStatus::from($validated['status']));

        return redirect()->route('alliance.overview');
    }

    public function updateRank(Request $request, AllianceContext $context, UpdateAllianceRank $updateRank, string $membership): RedirectResponse
    {
        $validated = $request->validate(['rank' => ['required', Rule::enum(AllianceRank::class)]]);
        $updateRank->handle($context->alliance(), $context->player(), $membership, AllianceRank::from($validated['rank']));

        return redirect()->route('alliance.overview');
    }

    public function assignRole(Request $request, AllianceContext $context, AssignMembershipRole $assignRole, string $membership, string $role): RedirectResponse
    {
        $assignRole->handle($context->alliance(), $context->player(), $membership, $role);

        return redirect()->route('alliance.overview');
    }

    public function removeRole(Request $request, AllianceContext $context, RemoveMembershipRole $removeRole, string $membership, string $role): RedirectResponse
    {
        $removeRole->handle($context->alliance(), $context->player(), $membership, $role);

        return redirect()->route('alliance.overview');
    }


    public function transferLeadership(
        Request $request,
        AllianceContext $context,
        TransferAllianceLeadership $transfer,
    ): RedirectResponse {
        $validated = $request->validate([
            'player_id' => ['required', 'string', 'size:26'],
        ]);

        $transfer->handle(
            $context->alliance(),
            $context->player(),
            (string) $validated['player_id'],
        );

        return redirect()->route('alliance.overview')->with('status', 'alliance-leadership-transferred');
    }

    public function leave(Request $request, AllianceContext $context, LeaveAlliance $leaveAlliance): RedirectResponse
    {
        $leaveAlliance->handle($context->alliance(), $context->player());

        return redirect()->route('dashboard');
    }
}
