<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Actions\UpdateAllianceSettings;
use App\Contexts\Alliance\Lifecycle\Enums\SupportedAllianceLocale;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceSettingsController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $scope = $context->scope();
        $authorization->authorize($scope->playerId, $scope->allianceId, AlliancePermission::Manage);
        $alliance = $alliances->require($scope->allianceId);

        return Inertia::render('Alliance/Settings/Index', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
            ],
            'locales' => array_map(static fn (SupportedAllianceLocale $locale): string => $locale->value, SupportedAllianceLocale::cases()),
        ]);
    }

    public function update(
        Request $request,
        AllianceContext $context,
        UpdateAllianceSettings $update,
    ): RedirectResponse {
        $scope = $context->scope();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120'],
            'language' => ['required', Rule::enum(SupportedAllianceLocale::class)],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $update->handle(
            $scope->allianceId,
            $scope->playerId,
            (string) $validated['name'],
            (string) $validated['slug'],
            SupportedAllianceLocale::from((string) $validated['language']),
            (string) $validated['timezone'],
        );

        return redirect()->route('alliance.settings.index')
            ->with('actionReceipt', $this->receipt('alliance-settings-updated'));
    }
}
