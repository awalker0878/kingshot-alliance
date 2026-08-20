<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
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
    ): Response {
        $user = $this->user($request);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($scope->kingdomId);

        if (! $authorization->allows($scope->playerId, $scope->kingdomId, KingdomPermission::RoleManage)) {
            throw new AuthorizationException;
        }

        $assignmentRows = KingdomRoleAssignment::query()
            ->where('kingdom_id', $scope->kingdomId)
            ->with('role:id,key,name')
            ->orderBy('created_at')
            ->get();
        $playerReferences = $players->byIds(
            $assignmentRows->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
        );

        $assignments = $assignmentRows->map(static function (KingdomRoleAssignment $assignment) use ($playerReferences): array {
            $player = $playerReferences[(string) $assignment->player_id] ?? null;

            return [
                'id' => (string) $assignment->id,
                'player' => [
                    'id' => (string) $assignment->player_id,
                    'name' => $player->currentName ?? 'Unknown player',
                    'gamePlayerId' => $player?->gamePlayerId,
                ],
                'role' => [
                    'key' => (string) $assignment->role->key,
                    'name' => (string) $assignment->role->name,
                ],
                'assignedAt' => $assignment->created_at?->toIso8601String(),
            ];
        })->values()->all();

        $kingdomPlayers = array_map(
            static fn ($player): array => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'gamePlayerId' => $player->gamePlayerId,
            ],
            $players->inKingdom($scope->kingdomId),
        );

        return Inertia::render('Kingdom/RoyalCourt/Roles', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'kingdom' => ['id' => $kingdom->kingdomId, 'number' => $kingdom->number],
            'roles' => array_map(
                static fn (DefaultKingdomRole $role): array => ['key' => $role->value, 'name' => $role->name()],
                DefaultKingdomRole::cases(),
            ),
            'players' => $kingdomPlayers,
            'assignments' => $assignments,
        ]);
    }

    public function store(Request $request, AllianceContext $context, AssignKingdomRole $assign): RedirectResponse
    {
        $scope = $context->scope();
        $validated = $request->validate([
            'player_id' => ['required', 'string', 'size:26', Rule::exists('players', 'id')->where('current_kingdom_id', $scope->kingdomId)],
            'role' => ['required', Rule::enum(DefaultKingdomRole::class)],
        ]);

        $assign->handle(
            actorPlayerId: $scope->playerId,
            kingdomId: $scope->kingdomId,
            targetPlayerId: (string) $validated['player_id'],
            roleTemplate: DefaultKingdomRole::from((string) $validated['role']),
        );

        return back()->with('actionReceipt', $this->receipt('kingdom-role-assigned'));
    }

    public function destroy(AllianceContext $context, KingdomRoleAssignment $assignment, RemoveKingdomRole $remove): RedirectResponse
    {
        $scope = $context->scope();
        $remove->handle($scope->playerId, $scope->kingdomId, (string) $assignment->id);

        return back()->with('actionReceipt', $this->receipt('kingdom-role-removed'));
    }

    private function user(Request $request): AuthenticatedAccount
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        return $user;
    }
}
