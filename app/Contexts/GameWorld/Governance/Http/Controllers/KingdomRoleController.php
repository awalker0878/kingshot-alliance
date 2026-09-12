<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class KingdomRoleController extends Controller
{
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
}
