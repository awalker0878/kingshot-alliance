<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Authorization\Services\KingdomAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Http\Controllers\Controller;
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

        if (! $authorization->allows($context->player(), $alliance, PermissionKey::AllianceManage)) {
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
                && $kingdomAuthorization->allows($context->player(), $alliance->kingdom, PermissionKey::KingdomRoleManage),
        ]);
    }

}
