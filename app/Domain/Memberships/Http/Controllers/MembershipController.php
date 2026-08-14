<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Actions\AssignMembershipRole;
use App\Domain\Authorization\Actions\RemoveMembershipRole;
use App\Domain\Memberships\Actions\LeaveAlliance;
use App\Domain\Memberships\Actions\TransferAllianceLeadership;
use App\Domain\Memberships\Actions\UpdateAllianceRank;
use App\Domain\Memberships\Actions\UpdateMembershipStatus;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Platform\Http\Controllers\Controller;
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
