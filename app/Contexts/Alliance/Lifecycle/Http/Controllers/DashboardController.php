<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\ReadModels\CommandOverview\Queries\CommandOverviewQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * Adapts the daily briefing read model without taking ownership of source-context writes.
 */
final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        PlayerContext $playerContext,
        AllianceAuthorization $authorization,
        CommandOverviewQuery $overview,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $userId = $user->getAuthIdentifier();
        abort_unless(is_numeric($userId), 401);

        $player = $playerContext->playerOrNull();
        $membershipSummary = null;
        $allianceId = null;
        $canManageRecruitment = false;

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

                $allianceId = (string) $alliance->id;
                $canManageRecruitment = $authorization->allows(
                    $player->playerId,
                    $allianceId,
                    AlliancePermission::RecruitmentManage,
                );
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
                        'id' => $allianceId,
                        'name' => (string) $alliance->name,
                        'slug' => (string) $alliance->slug,
                        'timezone' => (string) $alliance->timezone,
                    ],
                    'rank' => $membership->rank->value,
                    'roles' => $roles,
                    'canManageAlliance' => $authorization->allows(
                        $player->playerId,
                        $allianceId,
                        AlliancePermission::Manage,
                    ),
                    'canManageRecruitment' => $canManageRecruitment,
                ];
            }
        }

        return Inertia::render('Command/Overview', [
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
            'command' => $player === null ? null : $overview->for(
                (int) $userId,
                $player,
                $allianceId,
                $canManageRecruitment,
            ),
            'canCreateAlliance' => $player !== null && $membershipSummary === null,
        ]);
    }
}
