<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\DeclineKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\LeaveKingdomIntelligenceShare;
use App\Domain\Kingdoms\Actions\RevokeKingdomIntelligenceShare;
use App\Domain\Platform\Http\Controllers\Controller;
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
        $issued = $create->handle($context->alliance(), $this->user($request));

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
        /** @var array{token: string} $validated */
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);

        $accept->handle($context->alliance(), $this->user($request), $validated['token']);

        return back()->with('status', 'kingdom-shared-intelligence-accepted');
    }

    public function declineInvitation(
        Request $request,
        AllianceContext $context,
        DeclineKingdomIntelligenceShareInvitation $decline,
    ): RedirectResponse {
        /** @var array{token: string} $validated */
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);

        $decline->handle($context->alliance(), $this->user($request), $validated['token']);

        return back()->with('status', 'kingdom-shared-intelligence-declined');
    }

    public function revoke(
        Request $request,
        AllianceContext $context,
        RevokeKingdomIntelligenceShare $revoke,
        string $share,
    ): RedirectResponse {
        $revoke->handle($context->alliance(), $this->user($request), $share);

        return back()->with('status', 'kingdom-shared-intelligence-revoked');
    }

    public function leave(
        Request $request,
        AllianceContext $context,
        LeaveKingdomIntelligenceShare $leave,
        string $share,
    ): RedirectResponse {
        $leave->handle($context->alliance(), $this->user($request), $share);

        return back()->with('status', 'kingdom-shared-intelligence-left');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
