<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\Access\Models\Permission;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomRoleController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        PlayerReferenceQuery $players,
        KingdomAuthorization $authorization,
        KingdomAuthorityFactsQuery $authorityFacts,
    ): Response {
        $user = $this->user($request);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->requireActive($scope->kingdomId);
        if (! $authorization->allows($scope->playerId, $scope->kingdomId, KingdomPermission::RoleManage)) {
            throw new AuthorizationException;
        }

        $roles = KingdomRole::query()->where('kingdom_id', $scope->kingdomId)->with('permissions')->orderByDesc('is_system')->orderBy('name')->get();
        $assignmentRows = KingdomRoleAssignment::query()->where('kingdom_id', $scope->kingdomId)->whereNull('revoked_at')->with('role:id,key,name,archived_at')->orderBy('created_at')->get();
        $playerReferences = $players->byIds($assignmentRows->pluck('player_id')->map('strval')->all());
        $assignments = $assignmentRows->map(static function (KingdomRoleAssignment $assignment) use ($playerReferences): array {
            $playerId = (string) $assignment->player_id;
            $player = $playerReferences[$playerId] ?? null;
            $state = $assignment->isEffectiveAt() ? 'effective' : (($assignment->effective_from?->isFuture() ?? false) ? 'scheduled' : 'expired');

            return [
                'id' => (string) $assignment->id,
                'player' => ['id' => $playerId, 'name' => $player === null ? 'Unknown Governor' : $player->currentName, 'gamePlayerId' => $player?->gamePlayerId],
                'role' => ['id' => (string) $assignment->role->id, 'key' => (string) $assignment->role->key, 'name' => (string) $assignment->role->name],
                'state' => $state,
                'effectiveFrom' => $assignment->effective_from?->toIso8601String(),
                'expiresAt' => $assignment->expires_at?->toIso8601String(),
                'reason' => $assignment->reason,
                'assignedAt' => $assignment->created_at?->toIso8601String(),
            ];
        })->values()->all();
        $kingdomPlayers = array_map(static fn ($player): array => ['id' => $player->playerId, 'name' => $player->currentName, 'gamePlayerId' => $player->gamePlayerId], $players->inKingdom($scope->kingdomId));
        $facts = $authorityFacts->findCurrent($scope->playerId, $scope->kingdomId);
        $actorPermissionKeys = $facts === null ? [] : $facts->permissionKeysObservedAtRead;
        $permissionOptions = Permission::query()->whereNotNull('owner_key')->whereIn('key', $actorPermissionKeys)->orderBy('owner_key')->orderBy('key')->get()->map(static fn (Permission $permission): array => [
            'key' => (string) $permission->key,
            'owner' => (string) $permission->owner_key,
            'description' => (string) $permission->description,
        ])->values()->all();

        return Inertia::render('Kingdom/PositionPerks/Roles', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'kingdom' => ['id' => $kingdom->kingdomId, 'number' => $kingdom->number],
            'roles' => $roles->map(static fn (KingdomRole $role): array => [
                'id' => (string) $role->id,
                'key' => (string) $role->key,
                'name' => (string) $role->name,
                'description' => $role->description,
                'isSystem' => (bool) $role->is_system,
                'archivedAt' => $role->archived_at?->toIso8601String(),
                'permissions' => $role->permissions->map(static fn (Permission $permission): array => ['key' => (string) $permission->key, 'owner' => $permission->owner_key, 'description' => (string) $permission->description])->values()->all(),
            ])->values()->all(),
            'players' => $kingdomPlayers,
            'assignments' => $assignments,
            'permissionOptions' => $permissionOptions,
        ]);
    }

    public function store(Request $request, AllianceContext $context, AssignKingdomRole $assign): RedirectResponse
    {
        $scope = $context->scope();
        $validated = $request->validate([
            'player_id' => ['required', 'string', 'size:26', Rule::exists('players', 'id')->where('current_kingdom_id', $scope->kingdomId)],
            'role_id' => ['required', 'string', 'size:26', Rule::exists('kingdom_roles', 'id')->where('kingdom_id', $scope->kingdomId)],
            'effective_from' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $assign->handle($scope->playerId, $scope->kingdomId, (string) $validated['player_id'], (string) $validated['role_id'], $validated['effective_from'] ?? null, $validated['expires_at'] ?? null, $validated['reason'] ?? null);

        return back()->with('actionReceipt', $this->receipt('kingdom-role-assigned'));
    }

    public function destroy(Request $request, AllianceContext $context, KingdomRoleAssignment $assignment, RemoveKingdomRole $remove): RedirectResponse
    {
        $scope = $context->scope();
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $remove->handle($scope->playerId, $scope->kingdomId, (string) $assignment->id, $validated['reason'] ?? null);

        return back()->with('actionReceipt', $this->receipt('kingdom-role-removed'));
    }

    private function user(Request $request): AuthenticatedAccount
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        return $user;
    }
}
