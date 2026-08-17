<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MemberContentController extends Controller
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
        abort_unless($user instanceof User, 401);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);

        $items = $content->memberList(
            $scope->allianceId,
            $request->string('q')->toString(),
            $request->string('type')->toString(),
            $request->string('category')->toString(),
            $request->string('locale')->toString(),
        );

        $categories = ContentCategory::query()
            ->where('alliance_id', $scope->allianceId)
            ->whereHas('items', static fn ($query) => $query
                ->where('status', ContentStatus::Published->value)
                ->whereIn('visibility', [ContentVisibility::Public->value, ContentVisibility::Members->value])
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereNull('archived_at'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Alliance/Noticeboard/Index', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'timezone' => $alliance->timezone,
            ],
            'viewerTimezone' => $user->timezone,
            'canManageContent' => $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::ContentManage),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'type' => $request->string('type')->toString(),
                'category' => $request->string('category')->toString(),
                'locale' => $request->string('locale')->toString(),
            ],
            'categories' => $categories->map(static fn (ContentCategory $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values()->all(),
            'content' => $items->map(fn ($item): array => $presenter->item($item))->values()->all(),
        ]);
    }

    public function show(
        Request $request,
        AllianceContext $context,
        ContentQuery $content,
        ContentPresenter $presenter,
        AllianceReferenceQuery $alliances,
        string $contentSlug,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $item = $content->memberBySlug($scope->allianceId, $contentSlug);
        abort_unless($item !== null, 404);

        return Inertia::render('Alliance/Noticeboard/Show', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'timezone' => $alliance->timezone,
            ],
            'viewerTimezone' => $user->timezone,
            'content' => $presenter->item($item, true),
        ]);
    }
}
