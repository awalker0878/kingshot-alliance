<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomSettings\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Shared\Http\Controller;
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
        KingdomAuthorization $kingdomAuthorization,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($context->player(), $alliance, AlliancePermission::Manage)) {
            throw new AuthorizationException;
        }

        return Inertia::render('Alliance/KingdomSettings', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
            ],
            'canManageKingdomRoles' => $alliance->kingdom !== null
                && $kingdomAuthorization->allows($context->player(), $alliance->kingdom, KingdomPermission::RoleManage),
        ]);
    }
}
