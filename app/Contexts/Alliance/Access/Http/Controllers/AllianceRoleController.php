<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Actions\ArchiveAllianceRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\UpdateAllianceRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceRoleController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $scope = $context->scope();
        $authorization->authorize($scope->playerId, $scope->allianceId, AlliancePermission::RoleManage);
        $alliance = $alliances->require($scope->allianceId);

        $roles = Role::query()
            ->where('alliance_id', $scope->allianceId)
            ->with('permissions:id,key')
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(static fn (Role $role): array => [
                'id' => (string) $role->id,
                'key' => (string) $role->key,
                'name' => (string) $role->name,
                'system' => (bool) $role->is_system,
                'archivedAt' => $role->archived_at?->toIso8601String(),
                'permissions' => $role->permissions->pluck('key')->map(static fn ($key): string => (string) $key)->sort()->values()->all(),
                'memberCount' => $role->memberships()->count(),
            ])
            ->values()
            ->all();

        return Inertia::render('Alliance/Roles/Index', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'roles' => $roles,
            'permissions' => array_map(static fn (AlliancePermission $permission): string => $permission->value, AlliancePermission::cases()),
        ]);
    }

    public function store(Request $request, AllianceContext $context, CreateAllianceRole $create): RedirectResponse
    {
        $validated = $this->validatedRole($request);
        $scope = $context->scope();
        $create->handle($scope->allianceId, $scope->playerId, (string) $validated['name'], $this->permissions($validated['permissions']));

        return redirect()->route('alliance.roles.index')->with('actionReceipt', $this->receipt('alliance-role-created'));
    }

    public function update(Request $request, AllianceContext $context, UpdateAllianceRole $update, string $role): RedirectResponse
    {
        $validated = $this->validatedRole($request);
        $scope = $context->scope();
        $update->handle($scope->allianceId, $scope->playerId, $role, (string) $validated['name'], $this->permissions($validated['permissions']));

        return redirect()->route('alliance.roles.index')->with('actionReceipt', $this->receipt('alliance-role-updated'));
    }

    public function destroy(AllianceContext $context, ArchiveAllianceRole $archive, string $role): RedirectResponse
    {
        $scope = $context->scope();
        $archive->handle($scope->allianceId, $scope->playerId, $role);

        return redirect()->route('alliance.roles.index')->with('actionReceipt', $this->receipt('alliance-role-archived'));
    }

    /** @return array{name:string,permissions:list<string>} */
    private function validatedRole(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['array', 'max:32'],
            'permissions.*' => ['string', Rule::in(array_map(static fn (AlliancePermission $permission): string => $permission->value, AlliancePermission::cases()))],
        ]);
    }

    /** @param list<string> $values @return list<AlliancePermission> */
    private function permissions(array $values): array
    {
        return array_values(array_unique(array_map(static fn (string $value): AlliancePermission => AlliancePermission::from($value), $values), SORT_REGULAR));
    }
}
