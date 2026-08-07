<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Actions\AssignMembershipRole;
use App\Domain\Authorization\Actions\RemoveMembershipRole;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Actions\LeaveAlliance;
use App\Domain\Memberships\Actions\UpdateMembershipStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MembershipController extends Controller
{
    public function updateStatus(
        Request $request,
        AllianceContext $context,
        UpdateMembershipStatus $updateStatus,
        string $membership,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(MembershipStatus::class)],
        ]);

        $updateStatus->handle(
            $context->alliance(),
            $user,
            $membership,
            MembershipStatus::from($validated['status']),
        );

        return redirect()->route('alliance.overview');
    }

    public function assignRole(
        Request $request,
        AllianceContext $context,
        AssignMembershipRole $assignRole,
        string $membership,
        string $role,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $assignRole->handle($context->alliance(), $user, $membership, $role);

        return redirect()->route('alliance.overview');
    }

    public function removeRole(
        Request $request,
        AllianceContext $context,
        RemoveMembershipRole $removeRole,
        string $membership,
        string $role,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $removeRole->handle($context->alliance(), $user, $membership, $role);

        return redirect()->route('alliance.overview');
    }

    public function leave(
        Request $request,
        AllianceContext $context,
        LeaveAlliance $leaveAlliance,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $alliance = $context->alliance();
        $leaveAlliance->handle($alliance, $user);
        $request->session()->forget((string) config('identity.active_alliance_session_key'));

        return redirect()->route('dashboard');
    }
}
