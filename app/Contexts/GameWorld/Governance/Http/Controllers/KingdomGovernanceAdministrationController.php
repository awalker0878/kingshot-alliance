<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Actions\ArchiveKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\BulkKingdomRoleAdministration;
use App\Contexts\GameWorld\Governance\Actions\CreateKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\HandoffKingdomAdministrator;
use App\Contexts\GameWorld\Governance\Actions\UpdateKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\KingdomGovernance\Actions\ReconcileKingdomGovernancePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class KingdomGovernanceAdministrationController extends Controller
{
    public function createRole(Request $request, AllianceContext $context, CreateKingdomRole $create): RedirectResponse
    {
        $scope = $context->scope();
        $validated = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:255'], 'permissions' => ['array', 'max:50'], 'permissions.*' => ['string', 'max:100']]);
        $create->handle($scope->playerId, $scope->kingdomId, (string) $validated['name'], $validated['description'] ?? null, array_values($validated['permissions'] ?? []));

        return back()->with('actionReceipt', $this->receipt('kingdom-role-created'));
    }

    public function updateRole(Request $request, AllianceContext $context, KingdomRole $role, UpdateKingdomRole $update): RedirectResponse
    {
        $scope = $context->scope();
        $validated = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:255'], 'permissions' => ['array', 'max:50'], 'permissions.*' => ['string', 'max:100']]);
        $update->handle($scope->playerId, $scope->kingdomId, (string) $role->id, (string) $validated['name'], $validated['description'] ?? null, array_values($validated['permissions'] ?? []));

        return back()->with('actionReceipt', $this->receipt('kingdom-role-updated'));
    }

    public function archiveRole(AllianceContext $context, KingdomRole $role, ArchiveKingdomRole $archive): RedirectResponse
    {
        $scope = $context->scope();
        $archive->handle($scope->playerId, $scope->kingdomId, (string) $role->id);

        return back()->with('actionReceipt', $this->receipt('kingdom-role-archived'));
    }

    public function handoff(Request $request, AllianceContext $context, HandoffKingdomAdministrator $handoff): RedirectResponse
    {
        $scope = $context->scope();
        $validated = $request->validate([
            'player_id' => ['required', 'string', 'size:26', Rule::exists('players', 'id')->where('current_kingdom_id', $scope->kingdomId)],
            'mode' => ['required', Rule::in(['add', 'replace'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $handoff->handle($scope->playerId, $scope->kingdomId, (string) $validated['player_id'], $validated['mode'] === 'replace', $validated['reason'] ?? null);

        return back()->with('actionReceipt', $this->receipt('kingdom-administrator-handoff'));
    }

    public function bulkPreview(Request $request, AllianceContext $context, BulkKingdomRoleAdministration $bulk): JsonResponse
    {
        $scope = $context->scope();
        $validated = $request->validate(['role_id' => ['required', 'string', 'size:26'], 'operation' => ['required', Rule::in(['assign', 'remove'])], 'player_ids' => ['required', 'array', 'min:1', 'max:50'], 'player_ids.*' => ['string', 'size:26']]);

        return response()->json($bulk->preview($scope->playerId, $scope->kingdomId, (string) $validated['role_id'], (string) $validated['operation'], array_values($validated['player_ids'])));
    }

    public function bulk(Request $request, AllianceContext $context, BulkKingdomRoleAdministration $bulk): RedirectResponse
    {
        $scope = $context->scope();
        $validated = $request->validate(['role_id' => ['required', 'string', 'size:26'], 'operation' => ['required', Rule::in(['assign', 'remove'])], 'player_ids' => ['required', 'array', 'min:1', 'max:50'], 'player_ids.*' => ['string', 'size:26'], 'reason' => ['nullable', 'string', 'max:500']]);
        $result = $bulk->handle($scope->playerId, $scope->kingdomId, (string) $validated['role_id'], (string) $validated['operation'], array_values($validated['player_ids']), $validated['reason'] ?? null);

        return back()->with('actionReceipt', $this->receipt('kingdom-role-bulk-updated'))->with('governanceBulkResult', $result);
    }

    public function reconcile(AllianceContext $context, ReconcileKingdomGovernancePolicy $reconcile): RedirectResponse
    {
        $scope = $context->scope();
        $reconcile->handle($scope->playerId, $scope->kingdomId);

        return back()->with('actionReceipt', $this->receipt('kingdom-governance-reconciled'));
    }
}
