<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Http\Controllers;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;

use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Accounts\Models\User;
use App\Shared\Http\Controller;
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
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $actor = $context->player();
        $alliance = $context->alliance();

        $items = $content->memberList(
            $alliance,
            $request->string('q')->toString(),
            $request->string('type')->toString(),
            $request->string('category')->toString(),
            $request->string('locale')->toString(),
        );

        $categories = ContentCategory::query()
            ->where('alliance_id', $alliance->id)
            ->whereHas('items', static fn ($query) => $query
                ->where('status', ContentStatus::Published->value)
                ->whereIn('visibility', [ContentVisibility::Public->value, ContentVisibility::Members->value])
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereNull('archived_at'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Alliance/ContentIndex', [
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
            'canManageContent' => $authorization->allows($actor, $alliance, AlliancePermission::ContentManage),
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
        string $contentSlug,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $alliance = $context->alliance();
        $item = $content->memberBySlug($alliance, $contentSlug);
        abort_unless($item !== null, 404);

        return Inertia::render('Alliance/ContentDetail', [
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
