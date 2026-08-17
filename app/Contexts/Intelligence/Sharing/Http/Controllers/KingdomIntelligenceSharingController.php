<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Intelligence\Sharing\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Contexts\Intelligence\Sharing\Actions\AddKingdomIntelligenceShareTarget;
use App\Contexts\Intelligence\Sharing\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Contexts\Intelligence\Sharing\Actions\DeclineKingdomIntelligenceShareInvitation;
use App\Contexts\Intelligence\Sharing\Actions\LeaveKingdomIntelligenceShare;
use App\Contexts\Intelligence\Sharing\Actions\RemoveKingdomIntelligenceShareTarget;
use App\Contexts\Intelligence\Sharing\Actions\RevokeKingdomIntelligenceShare;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class KingdomIntelligenceSharingController extends Controller
{
    public function createInvitation(
        Request $request,
        AllianceContext $context,
        CreateKingdomIntelligenceShareInvitation $create,
    ): JsonResponse {
        $scope = $context->scope();
        $issued = $create->handle($scope->allianceId, $scope->playerId);

        return response()->json([
            'shareId' => $issued->shareId,
            'token' => $issued->token,
        ], 201);
    }

    public function acceptInvitation(
        Request $request,
        AllianceContext $context,
        AcceptKingdomIntelligenceShareInvitation $accept,
    ): RedirectResponse {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        $scope = $context->scope();
        $accept->handle($scope->allianceId, $scope->playerId, (string) $validated['token']);

        return back()->with('status', 'kingdom-shared-intelligence-accepted');
    }

    public function declineInvitation(
        Request $request,
        AllianceContext $context,
        DeclineKingdomIntelligenceShareInvitation $decline,
    ): RedirectResponse {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        $scope = $context->scope();
        $decline->handle($scope->allianceId, $scope->playerId, (string) $validated['token']);

        return back()->with('status', 'kingdom-shared-intelligence-declined');
    }

    public function revoke(
        Request $request,
        AllianceContext $context,
        RevokeKingdomIntelligenceShare $revoke,
        string $share,
    ): RedirectResponse {
        $scope = $context->scope();
        $revoke->handle($scope->allianceId, $scope->playerId, $share);

        return back()->with('status', 'kingdom-shared-intelligence-revoked');
    }

    public function leave(
        Request $request,
        AllianceContext $context,
        LeaveKingdomIntelligenceShare $leave,
        string $share,
    ): RedirectResponse {
        $scope = $context->scope();
        $leave->handle($scope->allianceId, $scope->playerId, $share);

        return back()->with('status', 'kingdom-shared-intelligence-left');
    }

    public function addTarget(
        Request $request,
        AllianceContext $context,
        AddKingdomIntelligenceShareTarget $add,
        string $share,
        string $tracking,
    ): RedirectResponse {
        $scope = $context->scope();
        $add->handle($scope->allianceId, $scope->playerId, $share, $tracking);

        return back()->with('status', 'kingdom-shared-intelligence-target-shared');
    }

    public function removeTarget(
        Request $request,
        AllianceContext $context,
        RemoveKingdomIntelligenceShareTarget $remove,
        string $share,
        string $target,
    ): RedirectResponse {
        $scope = $context->scope();
        $remove->handle($scope->allianceId, $scope->playerId, $share, $target);

        return back()->with('status', 'kingdom-shared-intelligence-target-removed');
    }
}
