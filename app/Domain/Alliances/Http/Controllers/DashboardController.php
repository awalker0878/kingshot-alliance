<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        PlayerContext $playerContext,
        AllianceAuthorization $authorization,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $player = $playerContext->playerOrNull();
        $membershipSummary = null;

        if ($player instanceof Player) {
            $membership = AllianceMembership::query()
                ->where('player_id', $player->id)
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

                $roles = $membership->roles->map(static function (Role $role): array {
                    return [
                        'key' => (string) $role->key,
                        'name' => (string) $role->name,
                    ];
                })->values()->all();

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
                    'canManageAlliance' => $authorization->allows($player, $alliance, PermissionKey::AllianceManage),
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
                'id' => (string) $player->id,
                'name' => (string) $player->current_name,
                'gamePlayerId' => $player->game_player_id,
                'kingdomNumber' => $player->currentKingdom?->number,
            ],
            'membership' => $membershipSummary,
            'canCreateAlliance' => $player instanceof Player
                && $player->current_kingdom_id !== null
                && $membershipSummary === null,
        ]);
    }
}
