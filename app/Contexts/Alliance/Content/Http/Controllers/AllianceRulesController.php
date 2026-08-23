<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Actions\SaveAllianceRules;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceRulesController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        ContentQuery $content,
        ContentPresenter $presenter,
        AllianceReferenceQuery $alliances,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $rules = $content->memberBySlug($scope->allianceId, SaveAllianceRules::SLUG);

        return Inertia::render('Alliance/Rules/Index', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'name' => $alliance->name,
                'slug' => $alliance->slug,
            ],
            'canManageContent' => $authorization->allows(
                $scope->playerId,
                $scope->allianceId,
                AlliancePermission::ContentManage,
            ),
            'rules' => $rules === null ? null : $presenter->item($rules, true),
        ]);
    }

    public function update(
        Request $request,
        AllianceContext $context,
        SaveAllianceRules $saveRules,
    ): RedirectResponse {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'locale' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/'],
        ]);
        $scope = $context->scope();

        $saveRules->handle(
            $scope->allianceId,
            $scope->playerId,
            (string) $validated['body'],
            (string) $validated['locale'],
        );

        return back();
    }
}
