<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

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

        /** @var list<array{id: string, alliance: array{id: string, name: string, slug: string, timezone: string}, roles: list<array{key: string, name: string}>}> $membershipSummaries */
        $membershipSummaries = [];

        foreach ($memberships as $membership) {
            $alliance = $membership->alliance;

            if (! $alliance instanceof Alliance) {
                throw new LogicException('An active membership must reference an alliance.');
            }

            /** @var list<array{key: string, name: string}> $roles */
            $roles = [];

            foreach ($membership->roles as $role) {
                if (! $role instanceof Role) {
                    throw new LogicException('A membership role relation returned an unexpected model.');
                }

                $roles[] = [
                    'key' => (string) $role->key,
                    'name' => (string) $role->name,
                ];
            }

            $membershipSummaries[] = [
                'id' => (string) $membership->id,
                'alliance' => [
                    'id' => (string) $alliance->id,
                    'name' => (string) $alliance->name,
                    'slug' => (string) $alliance->slug,
                    'timezone' => (string) $alliance->timezone,
                ],
                'roles' => $roles,
            ];
        }

        return Inertia::render('Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'timezone' => $user->timezone,
            ],
            'memberships' => $membershipSummaries,
            'activeAllianceId' => $activeAllianceId,
        ]);
    }
}
