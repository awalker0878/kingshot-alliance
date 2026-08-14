<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\AddKingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\DeclineKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\LeaveKingdomIntelligenceShare;
use App\Domain\Kingdoms\Actions\RemoveKingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Actions\RevokeKingdomIntelligenceShare;
use App\Domain\Kingdoms\Queries\KingdomIntelligenceSharingManageQuery;
use App\Domain\Kingdoms\Queries\SharedKingdomIntelligenceCurrentQuery;
use App\Domain\Kingdoms\Queries\SharedKingdomIntelligenceHistoryQuery;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomIntelligenceSharingController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        SharedKingdomIntelligenceCurrentQuery $current,
        SharedKingdomIntelligenceHistoryQuery $history,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($context->player(), $alliance, PermissionKey::AllianceView)) {
            throw new AuthorizationException;
        }

        /** @var array{target?: string|null, cursor?: string|null} $validated */
        $validated = $request->validate([
            'target' => ['sometimes', 'nullable', 'ulid'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:4096'],
        ]);

        $target = $validated['target'] ?? null;
        $cursor = $validated['cursor'] ?? null;
        if ($cursor !== null && $target === null) {
            throw ValidationException::withMessages([
                'cursor' => 'A shared history cursor requires its target.',
            ]);
        }

        return Inertia::render('Alliance/KingdomSharing', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => $this->allianceSummary($alliance),
            'canManage' => $authorization->allows($context->player(), $alliance, PermissionKey::KingdomManage),
            'current' => $current->forRecipient($alliance),
            'selectedHistory' => $target === null
                ? null
                : $history->forRecipientTarget($alliance, $target, $cursor),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        KingdomIntelligenceSharingManageQuery $sharing,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($context->player(), $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return Inertia::render('Alliance/KingdomSharingManage', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => $this->allianceSummary($alliance),
            'passwordConfirmUrl' => route('password.confirm'),
            'sharing' => $sharing->forAlliance($alliance),
        ]);
    }

    public function createInvitation(
        Request $request,
        AllianceContext $context,
        CreateKingdomIntelligenceShareInvitation $create,
    ): JsonResponse {
        $issued = $create->handle($context->alliance(), $context->player());

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

        $accept->handle($context->alliance(), $context->player(), $validated['token']);

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

        $decline->handle($context->alliance(), $context->player(), $validated['token']);

        return back()->with('status', 'kingdom-shared-intelligence-declined');
    }

    public function revoke(
        Request $request,
        AllianceContext $context,
        RevokeKingdomIntelligenceShare $revoke,
        string $share,
    ): RedirectResponse {
        $revoke->handle($context->alliance(), $context->player(), $share);

        return back()->with('status', 'kingdom-shared-intelligence-revoked');
    }

    public function leave(
        Request $request,
        AllianceContext $context,
        LeaveKingdomIntelligenceShare $leave,
        string $share,
    ): RedirectResponse {
        $leave->handle($context->alliance(), $context->player(), $share);

        return back()->with('status', 'kingdom-shared-intelligence-left');
    }

    public function addTarget(
        Request $request,
        AllianceContext $context,
        AddKingdomIntelligenceShareTarget $add,
        string $share,
        string $tracking,
    ): RedirectResponse {
        $add->handle($context->alliance(), $context->player(), $share, $tracking);

        return back()->with('status', 'kingdom-shared-intelligence-target-shared');
    }

    public function removeTarget(
        Request $request,
        AllianceContext $context,
        RemoveKingdomIntelligenceShareTarget $remove,
        string $share,
        string $target,
    ): RedirectResponse {
        $remove->handle($context->alliance(), $context->player(), $share, $target);

        return back()->with('status', 'kingdom-shared-intelligence-target-removed');
    }

    /** @return array{id: string, name: string, kingdom: string|null} */
    private function allianceSummary(Alliance $alliance): array
    {
        return [
            'id' => (string) $alliance->id,
            'name' => (string) $alliance->name,
            'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
