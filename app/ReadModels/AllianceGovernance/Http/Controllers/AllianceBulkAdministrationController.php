<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceGovernance\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceBulkAdministrationController extends Controller
{
    public function __invoke(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        PlayerReferenceQuery $players,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $scope = $context->scope();
        $authorization->authorize($scope->playerId, $scope->allianceId, AlliancePermission::RoleManage);
        $alliance = $alliances->require($scope->allianceId);

        $memberships = AllianceMembership::query()
            ->where('alliance_id', $scope->allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->orderBy('rank')
            ->orderBy('id')
            ->limit(500)
            ->get();
        $playerRefs = $players->byIds($memberships->pluck('player_id')->map(static fn ($id): string => (string) $id)->all());
        $members = $memberships->map(static function (AllianceMembership $membership) use ($playerRefs): array {
            $player = $playerRefs[(string) $membership->player_id] ?? null;

            return [
                'membershipId' => (string) $membership->id,
                'playerId' => (string) $membership->player_id,
                'name' => $player?->currentName ?? 'Unknown Governor',
                'rank' => $membership->rank->value,
            ];
        })->values()->all();

        $roles = Role::query()
            ->where('alliance_id', $scope->allianceId)
            ->whereNull('archived_at')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'is_system'])
            ->map(static fn (Role $role): array => [
                'id' => (string) $role->id,
                'key' => (string) $role->key,
                'name' => (string) $role->name,
                'system' => (bool) $role->is_system,
            ])->values()->all();

        return Inertia::render('Alliance/Members/Bulk', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'members' => $members,
            'roles' => $roles,
            'rankOptions' => array_values(array_map(
                static fn (AllianceRank $rank): string => $rank->value,
                array_filter(AllianceRank::cases(), static fn (AllianceRank $rank): bool => $rank !== AllianceRank::R5),
            )),
            'bulkRankPreview' => $request->session()->get('bulkRankPreview'),
            'bulkRankResult' => $request->session()->get('bulkRankResult'),
            'bulkRolePreview' => $request->session()->get('bulkRolePreview'),
            'bulkRoleResult' => $request->session()->get('bulkRoleResult'),
        ]);
    }
}
