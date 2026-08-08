<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\UpdateAllianceKingdom;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomSettingsController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($user, $alliance, PermissionKey::AllianceManage)) {
            throw new AuthorizationException;
        }

        return Inertia::render('Alliance/KingdomSettings', [
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
            ],
        ]);
    }

    public function update(
        Request $request,
        AllianceContext $context,
        UpdateAllianceKingdom $updateKingdom,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'kingdom' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ]);

        $updateKingdom->handle(
            alliance: $context->alliance(),
            actor: $user,
            number: $validated['kingdom'] ?? null,
        );

        return back()->with('status', 'alliance-kingdom-updated');
    }
}
