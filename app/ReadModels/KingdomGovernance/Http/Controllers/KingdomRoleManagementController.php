<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\ReadModels\KingdomGovernance\Queries\KingdomGovernanceProjectionQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomRoleManagementController extends Controller
{
    public function __invoke(Request $request, AllianceContext $context, AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms, KingdomGovernanceProjectionQuery $projection): Response
    {
        $scope = $context->scope();
        $projection->authorize($scope->playerId, $scope->kingdomId);
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $validated = $request->validate([
            'roles_cursor' => ['nullable', 'string', 'max:4096'], 'assignments_cursor' => ['nullable', 'string', 'max:4096'],
            'search' => ['nullable', 'string', 'max:160'], 'role_id' => ['nullable', 'ulid'],
            'player_id' => ['nullable', 'ulid'], 'permission' => ['nullable', 'string', 'max:100'],
        ]);
        $roles = $projection->roles($scope->playerId, $scope->kingdomId, $validated['roles_cursor'] ?? null);
        $assignments = $projection->assignments($scope->playerId, $scope->kingdomId, $validated['assignments_cursor'] ?? null,
            $validated['search'] ?? '', $validated['role_id'] ?? null, $validated['player_id'] ?? null, $validated['permission'] ?? null);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->requireActive($scope->kingdomId);
        $roleChoice = isset($validated['role_id']) ? $projection->choices($scope->playerId, $scope->kingdomId, 'roles', selectedId: $validated['role_id'])['selected'] : null;
        $playerChoice = isset($validated['player_id']) ? $projection->choices($scope->playerId, $scope->kingdomId, 'players', selectedId: $validated['player_id'])['selected'] : null;

        return Inertia::render('Kingdom/PositionPerks/Roles', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'kingdom' => ['id' => $kingdom->kingdomId, 'number' => $kingdom->number],
            'catalogueScope' => $scope->playerId.'|'.$scope->kingdomId, 'roles' => $roles['items'], 'assignments' => $assignments['items'],
            'pages' => ['roles' => array_diff_key($roles, ['items' => true]), 'assignments' => array_diff_key($assignments, ['items' => true])],
            'permissionOptions' => $projection->permissions($scope->playerId, $scope->kingdomId, true),
            'filters' => ['search' => $validated['search'] ?? '', 'roleId' => $validated['role_id'] ?? null,
                'playerId' => $validated['player_id'] ?? null, 'permission' => $validated['permission'] ?? null],
            'selectedRole' => $roleChoice, 'selectedPlayer' => $playerChoice,
        ]);
    }

    public function choices(Request $request, AllianceContext $context, KingdomGovernanceProjectionQuery $projection, string $kind): JsonResponse
    {
        $scope = $context->scope();
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:160'], 'cursor' => ['nullable', 'string', 'max:4096'],
            'selected_id' => ['nullable', 'ulid']]);

        return response()->json($projection->choices($scope->playerId, $scope->kingdomId, $kind,
            $validated['search'] ?? '', $validated['cursor'] ?? null, $validated['selected_id'] ?? null));
    }
}
