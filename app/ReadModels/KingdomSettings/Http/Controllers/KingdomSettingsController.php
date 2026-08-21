<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomSettings\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomSettingsController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        KingdomAuthorization $kingdomAuthorization,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($scope->kingdomId);

        if (! $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::Manage)) {
            throw new AuthorizationException;
        }

        return Inertia::render('Kingdom/PositionPerks/Settings', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'kingdom' => (string) $kingdom->number,
            ],
            'canManageKingdomRoles' => $kingdomAuthorization->allows(
                $scope->playerId,
                $scope->kingdomId,
                KingdomPermission::RoleManage,
            ),
        ]);
    }
}
