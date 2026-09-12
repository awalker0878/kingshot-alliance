<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Actions\ArchiveAllianceRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\UpdateAllianceRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Queries\AllianceRoleCatalogQuery;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
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
        AllianceRoleCatalogQuery $catalog,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $scope = $context->scope();
        $authorization->authorize($scope->playerId, $scope->allianceId, AlliancePermission::RoleManage);
        $alliance = $alliances->require($scope->allianceId);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'q' => ['nullable', 'string', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:4096'],
        ]);
        $status = (string) ($validated['status'] ?? 'active');
        $search = trim((string) ($validated['q'] ?? ''));
        $page = $catalog->management($scope->allianceId, $status === 'archived', $search, $validated['cursor'] ?? null);

        return Inertia::render('Alliance/Roles/Index', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'rolePage' => $page->toArray(),
            'filters' => ['status' => $status, 'q' => $search],
            'permissions' => array_map(static fn (AlliancePermission $permission): string => $permission->value, AlliancePermission::cases()),
        ]);
    }

    public function options(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceRoleCatalogQuery $catalog,
    ): JsonResponse {
        $scope = $context->scope();
        $authorization->authorize($scope->playerId, $scope->allianceId, AlliancePermission::RoleManage);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:4096'],
        ]);

        return response()->json($catalog->options($scope->allianceId, (string) ($validated['q'] ?? ''), $validated['cursor'] ?? null)->toArray());
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
            'permissions' => ['present', 'array', 'list', 'max:'.count(AlliancePermission::cases())],
            'permissions.*' => ['string', 'distinct', Rule::in(array_map(static fn (AlliancePermission $permission): string => $permission->value, AlliancePermission::cases()))],
        ]);
    }

    /**
     * @param  array<array-key, string>  $values
     * @return list<AlliancePermission>
     */
    private function permissions(array $values): array
    {
        return array_values(array_unique(array_map(static fn (string $value): AlliancePermission => AlliancePermission::from($value), $values), SORT_REGULAR));
    }
}
