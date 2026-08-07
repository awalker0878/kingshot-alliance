<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\AllianceMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $memberships = AllianceMembership::query()
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->with([
                'alliance:id,name,slug,timezone,status',
                'roles:id,alliance_id,key,name',
            ])
            ->get();

        $sessionKey = (string) config('identity.active_alliance_session_key');
        $activeAllianceId = $request->session()->get($sessionKey);

        if (! is_string($activeAllianceId) || ! $memberships->contains('alliance_id', $activeAllianceId)) {
            $request->session()->forget($sessionKey);
            $activeAllianceId = null;
        }

        return Inertia::render('Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'timezone' => $user->timezone,
            ],
            'memberships' => $memberships->map(static fn (AllianceMembership $membership): array => [
                'id' => $membership->id,
                'alliance' => [
                    'id' => $membership->alliance->id,
                    'name' => $membership->alliance->name,
                    'slug' => $membership->alliance->slug,
                    'timezone' => $membership->alliance->timezone,
                ],
                'roles' => $membership->roles->map(static fn ($role): array => [
                    'key' => $role->key,
                    'name' => $role->name,
                ])->values(),
            ])->values(),
            'activeAllianceId' => $activeAllianceId,
        ]);
    }
}
