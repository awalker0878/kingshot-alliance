<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Queries\NoticeReactionSummaryQuery;
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
        NoticeReactionSummaryQuery $reactions,
        AllianceReferenceQuery $alliances,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);

        $items = $content->memberList(
            $scope->allianceId,
            $request->string('q')->toString(),
            $request->string('type')->toString(),
            $request->string('category')->toString(),
            $request->string('locale')->toString(),
        );
        $noticeIds = $items
            ->filter(static fn (ContentItem $item): bool => $item->type === ContentType::Announcement)
            ->map(static fn (ContentItem $item): string => (string) $item->id)
            ->values()
            ->all();
        $reactionSummaries = $reactions->forNotices($scope->allianceId, $scope->playerId, $noticeIds);

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
            'content' => $items->map(function (ContentItem $item) use ($presenter, $reactionSummaries): array {
                $presented = $presenter->item($item);
                $presented['reactions'] = $item->type === ContentType::Announcement
                    ? ($reactionSummaries[(string) $item->id] ?? ['likes' => 0, 'dislikes' => 0, 'current' => null])
                    : null;

                return $presented;
            })->values()->all(),
        ]);
    }

    public function show(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        ContentQuery $content,
        ContentPresenter $presenter,
        NoticeReactionSummaryQuery $reactions,
        AllianceReferenceQuery $alliances,
        string $contentSlug,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $item = $content->memberBySlug($scope->allianceId, $contentSlug);
        abort_unless($item !== null, 404);

        $presented = $presenter->item($item, true);
        $presented['reactions'] = null;

        if ($item->type === ContentType::Announcement) {
            $summary = $reactions->forNotices(
                $scope->allianceId,
                $scope->playerId,
                [(string) $item->id],
            );
            $presented['reactions'] = $summary[(string) $item->id] ?? ['likes' => 0, 'dislikes' => 0, 'current' => null];
        }

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
            'canManageContent' => $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::ContentManage),
            'content' => $presented,
        ]);
    }
}
