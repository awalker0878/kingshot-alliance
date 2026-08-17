<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Http\Controllers;

use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Actions\LeaveAlliance;
use App\Contexts\Alliance\Membership\Actions\TransferAllianceLeadership;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MembershipController extends Controller
{
    public function updateStatus(Request $request, AllianceContext $context, UpdateMembershipStatus $updateStatus, string $membership): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(MembershipStatus::class)]]);
        $scope = $context->scope();
        $updateStatus->handle($scope->allianceId, $scope->playerId, $membership, MembershipStatus::from($validated['status']));

        return redirect()->route('alliance.overview');
    }

    public function updateRank(Request $request, AllianceContext $context, UpdateAllianceRank $updateRank, string $membership): RedirectResponse
    {
        $validated = $request->validate(['rank' => ['required', Rule::enum(AllianceRank::class)]]);
        $scope = $context->scope();
        $updateRank->handle($scope->allianceId, $scope->playerId, $membership, AllianceRank::from($validated['rank']));

        return redirect()->route('alliance.overview');
    }

    public function assignRole(Request $request, AllianceContext $context, AssignMembershipRole $assignRole, string $membership, string $role): RedirectResponse
    {
        $scope = $context->scope();
        $assignRole->handle($scope->allianceId, $scope->playerId, $membership, $role);

        return redirect()->route('alliance.overview');
    }

    public function removeRole(Request $request, AllianceContext $context, RemoveMembershipRole $removeRole, string $membership, string $role): RedirectResponse
    {
        $scope = $context->scope();
        $removeRole->handle($scope->allianceId, $scope->playerId, $membership, $role);

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
            $context->scope()->allianceId,
            $context->scope()->playerId,
            (string) $validated['player_id'],
        );

        return redirect()->route('alliance.overview')->with('status', 'alliance-leadership-transferred');
    }

    public function leave(Request $request, AllianceContext $context, LeaveAlliance $leaveAlliance): RedirectResponse
    {
        $scope = $context->scope();
        $leaveAlliance->handle($scope->allianceId, $scope->playerId);

        return redirect()->route('dashboard');
    }
}
