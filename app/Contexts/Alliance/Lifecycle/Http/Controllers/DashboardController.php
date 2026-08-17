<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, PlayerContext $playerContext, AllianceAuthorization $authorization): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $player = $playerContext->playerOrNull();
        $membershipSummary = null;

        if ($player !== null) {
            $membership = AllianceMembership::query()
                ->where('player_id', $player->playerId)
                ->where('status', MembershipStatus::Active->value)
                ->with([
                    'alliance:id,name,slug,timezone,status,kingdom_id',
                    'roles:id,alliance_id,key,name',
                ])
                ->first();

            if ($membership instanceof AllianceMembership) {
                $alliance = $membership->alliance;
                if (! $alliance instanceof Alliance) {
                    throw new LogicException('An active membership must reference an Alliance.');
                }

                $roles = $membership->roles
                    ->map(static fn (Role $role): array => [
                        'key' => (string) $role->key,
                        'name' => (string) $role->name,
                    ])
                    ->values()
                    ->all();

                $membershipSummary = [
                    'id' => (string) $membership->id,
                    'alliance' => [
                        'id' => (string) $alliance->id,
                        'name' => (string) $alliance->name,
                        'slug' => (string) $alliance->slug,
                        'timezone' => (string) $alliance->timezone,
                    ],
                    'rank' => $membership->rank->value,
                    'roles' => $roles,
                    'canManageAlliance' => $authorization->allows(
                        $player->playerId,
                        (string) $alliance->id,
                        AlliancePermission::Manage,
                    ),
                ];
            }
        }

        return Inertia::render('Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'timezone' => $user->timezone,
            ],
            'activePlayer' => $player === null ? null : [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'gamePlayerId' => $player->gamePlayerId,
                'kingdomNumber' => $player->kingdomNumber,
            ],
            'membership' => $membershipSummary,
            'canCreateAlliance' => $player !== null && $membershipSummary === null,
        ]);
    }
}
