<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceGovernance\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\ReadModels\AllianceGovernance\Queries\AllianceGovernanceTimelineQuery;
use App\ReadModels\AllianceGovernance\Queries\AllianceRosterReconciliationQuery;
use App\ReadModels\AllianceGovernance\Queries\MembershipGovernanceHistoryQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceGovernanceController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        AllianceGovernanceTimelineQuery $timeline,
    ): Response {
        $user = $this->user($request);
        $scope = $context->scope();
        $this->authorizeOfficer($authorization, $scope->playerId, $scope->allianceId);
        $alliance = $alliances->require($scope->allianceId);
        $validated = $request->validate([
            'capability' => ['nullable', 'string', 'in:alliance,membership,invitation,recruitment,content,integration'],
            'actor_player_id' => ['nullable', 'string', 'ulid'],
            'before' => ['nullable', 'string', 'ulid'],
        ]);
        $result = $timeline->forAlliance(
            $scope->allianceId,
            isset($validated['capability']) ? (string) $validated['capability'] : null,
            isset($validated['actor_player_id']) ? (string) $validated['actor_player_id'] : null,
            isset($validated['before']) ? (string) $validated['before'] : null,
        );

        return Inertia::render('Alliance/History/Index', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'timeline' => $result,
            'filters' => [
                'capability' => $validated['capability'] ?? null,
                'actorPlayerId' => $validated['actor_player_id'] ?? null,
            ],
        ]);
    }

    public function member(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        PlayerReferenceQuery $players,
        MembershipGovernanceHistoryQuery $history,
        string $player,
    ): Response {
        $user = $this->user($request);
        $scope = $context->scope();
        $this->authorizeOfficer($authorization, $scope->playerId, $scope->allianceId);
        $alliance = $alliances->require($scope->allianceId);
        $target = $players->require($player);

        return Inertia::render('Alliance/Members/History', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'player' => ['id' => $target->playerId, 'name' => $target->currentName, 'gamePlayerId' => $target->gamePlayerId],
            'history' => $history->forPlayer($scope->allianceId, $player),
        ]);
    }

    public function reconciliation(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        AllianceRosterReconciliationQuery $reconciliation,
    ): Response {
        $user = $this->user($request);
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $alliance = $alliances->require($scope->allianceId);

        return Inertia::render('Alliance/RosterReconciliation/Index', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'reconciliation' => $reconciliation->forAlliance($scope->allianceId),
        ]);
    }

    private function authorizeOfficer(AllianceAuthorization $authorization, string $playerId, string $allianceId): void
    {
        if (! $authorization->allows($playerId, $allianceId, AlliancePermission::MembershipManage)
            && ! $authorization->allows($playerId, $allianceId, AlliancePermission::RoleManage)
            && ! $authorization->allows($playerId, $allianceId, AlliancePermission::Manage)) {
            throw new AuthorizationException;
        }
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
