<?php

declare(strict_types=1);

namespace App\ReadModels\AnnouncementBroadcastManagement\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\AllianceBrandingMedia;
use App\Contexts\Alliance\Content\Models\AllianceProfile;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Content\Queries\ContentManagementQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\Operations\Events\Models\EventType;
use App\ReadModels\AnnouncementBroadcastManagement\Queries\AnnouncementBroadcastManagementQuery;
use App\Shared\Infrastructure\Http\Controller;
use App\Shared\Infrastructure\Pagination\PageSlice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AnnouncementBroadcastManagementController extends Controller
{
    public function __invoke(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        ContentManagementQuery $content,
        ContentPresenter $presenter,
        AnnouncementBroadcastManagementQuery $broadcasts,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->requireActive($alliance->kingdomId);

        if (! $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::ContentManage)) {
            throw new AuthorizationException;
        }

        $input = $request->validate([
            'content_cursor' => ['nullable', 'string', 'max:4096'],
            'category_cursor' => ['nullable', 'string', 'max:4096'],
            'media_cursor' => ['nullable', 'string', 'max:4096'],
            'q' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:draft,scheduled,published,archived'],
        ]);
        $profile = AllianceProfile::query()->where('alliance_id', $scope->allianceId)->first();
        $branding = AllianceBrandingMedia::query()->where('alliance_id', $scope->allianceId)
            ->whereIn('slot', ['logo', 'banner'])->pluck('media_id', 'slot');
        $catalogue = $content->catalogue($scope->allianceId, $scope->playerId, $input['content_cursor'] ?? null, $input['q'] ?? '', $input['status'] ?? '');
        $categoryPage = $content->categories($scope->allianceId, $scope->playerId, $input['category_cursor'] ?? null);
        $mediaPage = $content->media($scope->allianceId, $scope->playerId, $input['media_cursor'] ?? null);
        $related = $content->related($scope->allianceId, $scope->playerId, array_map(static fn ($item): string => (string) $item->id, $catalogue['page']->items));
        $items = $catalogue['page']->items;
        $categories = collect($categoryPage['page']->items);
        $media = collect($mediaPage['page']->items);

        $categoryData = $categories->map(static fn (ContentCategory $category): array => [
            'id' => (string) $category->id,
            'name' => (string) $category->name,
            'slug' => (string) $category->slug,
            'sortOrder' => (int) $category->sort_order,
        ])->values()->all();

        $contentData = [];
        foreach ($items as $item) {
            $data = $presenter->item($item, true);
            $data['revisionCount'] = $related['revisions'][(string) $item->id] ?? 0;
            $data['broadcastRunCount'] = $related['runs'][(string) $item->id] ?? 0;
            $data['broadcastSchedule'] = $broadcasts->schedule($related['schedules'][(string) $item->id] ?? null);
            $contentData[] = $data;
        }

        $mediaData = $media->map(static fn (MediaAsset $asset): array => [
            'id' => (string) $asset->id,
            'name' => (string) $asset->original_name,
            'mimeType' => (string) $asset->mime_type,
            'sizeBytes' => (int) $asset->size_bytes,
            'scanStatus' => $asset->scan_status->value,
            'lifecycleStatus' => $asset->lifecycle_status->value,
            'createdAt' => $asset->created_at?->toIso8601String(),
        ])->values()->all();

        return Inertia::render('Alliance/Noticeboard/Manage', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'kingdom' => $kingdom->number,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
                'description' => $profile?->description,
                'primaryColor' => $profile?->primary_color,
                'logoMediaId' => $branding->get('logo'),
                'bannerMediaId' => $branding->get('banner'),
                'publicUrl' => route('public.alliances.show', $alliance->slug),
            ],
            'contentTypes' => array_map(static fn (ContentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'requiresProvenance' => $type->requiresProvenance(),
            ], ContentType::cases()),
            'visibilityOptions' => array_map(static fn (ContentVisibility $visibility): array => [
                'value' => $visibility->value,
                'label' => ucfirst($visibility->value),
            ], ContentVisibility::cases()),
            'categories' => $categoryData,
            'eventTypes' => EventType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('slug')
                ->get(['slug', 'name_key'])
                ->map(static fn (EventType $eventType): array => [
                    'slug' => (string) $eventType->slug,
                    'nameKey' => (string) $eventType->name_key,
                ])
                ->values()
                ->all(),
            'content' => $contentData,
            'catalogue' => $this->metadata($catalogue),
            'categoryPage' => $this->metadata($categoryPage),
            'mediaPage' => $this->metadata($mediaPage),
            'filters' => ['q' => $input['q'] ?? '', 'status' => $input['status'] ?? ''],
            'totals' => $content->totals($scope->allianceId, $scope->playerId),
            'media' => $mediaData,
        ]);
    }

    public function runs(Request $request, string $contentId, AllianceContext $context, AnnouncementBroadcastManagementQuery $broadcasts): JsonResponse
    {
        $input = $request->validate(['cursor' => ['nullable', 'string', 'max:4096']]);
        $scope = $context->scope();

        return response()->json($broadcasts->history($scope->allianceId, $scope->playerId, $contentId, $input['cursor'] ?? null));
    }

    public function revisions(Request $request, string $contentId, AllianceContext $context, ContentManagementQuery $content): JsonResponse
    {
        $input = $request->validate(['cursor' => ['nullable', 'string', 'max:4096']]);
        $scope = $context->scope();
        $result = $content->revisions($scope->allianceId, $scope->playerId, $contentId, $input['cursor'] ?? null);
        $page = $result['page']->toArray();
        $page['items'] = array_map(static fn (ContentRevision $revision): array => [
            'id' => (string) $revision->id, 'revisionNumber' => (int) $revision->revision_number,
            'title' => (string) $revision->title, 'createdAt' => $revision->created_at?->toIso8601String(),
        ], $result['page']->items);

        return response()->json(['page' => $page, 'total' => $result['total']]);
    }

    public function options(Request $request, string $kind, AllianceContext $context, ContentManagementQuery $content): JsonResponse
    {
        $input = $request->validate(['cursor' => ['nullable', 'string', 'max:4096'], 'q' => ['nullable', 'string', 'max:160'], 'selected' => ['nullable', 'ulid']]);
        $scope = $context->scope();
        $result = match ($kind) {
            'categories' => $content->categories($scope->allianceId, $scope->playerId, $input['cursor'] ?? null, $input['q'] ?? ''),
            'media' => $content->media($scope->allianceId, $scope->playerId, $input['cursor'] ?? null, $input['q'] ?? ''),
            default => abort(404),
        };
        $page = $result['page']->toArray();
        $page['items'] = array_map(static fn (ContentCategory|MediaAsset $row): array => [
            'id' => (string) $row->getKey(), 'name' => (string) $row->getAttribute($kind === 'media' ? 'original_name' : 'name'),
        ], $result['page']->items);

        return response()->json(['page' => $page, 'total' => $result['total'],
            'selected' => $content->selectedOption($scope->allianceId, $scope->playerId, $kind, $input['selected'] ?? null)]);
    }

    /** @template T
     * @param  array{page:PageSlice<T>,total:int}  $result
     * @return array<string,mixed>
     */
    private function metadata(array $result): array
    {
        $page = $result['page']->toArray();
        unset($page['items']);

        return [...$page, 'total' => $result['total']];
    }
}
