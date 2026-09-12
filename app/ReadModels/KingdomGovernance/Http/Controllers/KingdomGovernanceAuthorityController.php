<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\ReadModels\KingdomGovernance\Queries\KingdomGovernanceProjectionQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomGovernanceAuthorityController extends Controller
{
    public function __invoke(Request $request, AllianceContext $context, AllianceReferenceQuery $alliances, KingdomReferenceQuery $kingdoms, KingdomAuthorization $authorization, KingdomGovernanceProjectionQuery $projection): Response
    {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->kingdomId, KingdomPermission::RoleManage)) {
            throw new AuthorizationException;
        }
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $validated = $request->validate(['permission' => ['nullable', 'string', 'max:100'],
            'roles_cursor' => ['nullable', 'string', 'max:4096'], 'holders_cursor' => ['nullable', 'string', 'max:4096']]);
        $selected = $validated['permission'] ?? null;
        $roles = $projection->roles($scope->playerId, $scope->kingdomId, $validated['roles_cursor'] ?? null);
        $holders = $selected === null ? null : $projection->holders($scope->playerId, $scope->kingdomId, $selected, $validated['holders_cursor'] ?? null);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->requireActive($scope->kingdomId);

        return Inertia::render('Kingdom/Governance/Authority', ['user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()], 'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name], 'kingdom' => ['id' => $kingdom->kingdomId, 'number' => $kingdom->number], 'catalogueScope' => $scope->playerId.'|'.$scope->kingdomId, 'roles' => $roles['items'], 'permissions' => $projection->permissions($scope->playerId, $scope->kingdomId, false), 'selectedPermission' => $selected, 'holders' => $holders['items'] ?? [], 'pages' => ['roles' => array_diff_key($roles, ['items' => true]), 'holders' => $holders === null ? null : array_diff_key($holders, ['items' => true])]]);
    }
}
