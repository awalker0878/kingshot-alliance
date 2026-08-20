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
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\Operations\Events\Models\EventType;
use App\ReadModels\AnnouncementBroadcastManagement\Queries\AnnouncementBroadcastManagementQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class AnnouncementBroadcastManagementController extends Controller
{
    public function __invoke(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        ContentQuery $content,
        ContentPresenter $presenter,
        AnnouncementBroadcastManagementQuery $broadcasts,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->find($alliance->kingdomId);

        if (! $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::ContentManage)) {
            throw new AuthorizationException;
        }

        $profile = AllianceProfile::query()->where('alliance_id', $scope->allianceId)->first();
        $branding = AllianceBrandingMedia::query()
            ->where('alliance_id', $scope->allianceId)
            ->pluck('media_id', 'slot');
        $categories = ContentCategory::query()
            ->where('alliance_id', $scope->allianceId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $items = $content->managerList($scope->allianceId);
        $items->load('revisions');
        $broadcastManagement = $broadcasts->forAlliance($scope->allianceId);
        $media = MediaAsset::query()
            ->where('alliance_id', $scope->allianceId)
            ->orderByDesc('created_at')
            ->get();

        $categoryData = $categories->map(static fn (ContentCategory $category): array => [
            'id' => (string) $category->id,
            'name' => (string) $category->name,
            'slug' => (string) $category->slug,
            'sortOrder' => (int) $category->sort_order,
        ])->values()->all();

        $contentData = [];
        foreach ($items as $item) {
            $data = $presenter->item($item, true);
            $revisionData = [];
            foreach ($item->revisions as $revision) {
                if (! $revision instanceof ContentRevision) {
                    throw new LogicException('A content revision relation returned an unexpected model.');
                }

                $revisionData[] = [
                    'id' => (string) $revision->id,
                    'revisionNumber' => (int) $revision->revision_number,
                    'title' => (string) $revision->title,
                    'createdAt' => $revision->created_at?->toIso8601String(),
                ];
            }

            $data['revisions'] = $revisionData;
            $data['broadcastSchedule'] = $broadcastManagement['schedules'][(string) $item->id] ?? null;
            $data['broadcastRuns'] = $broadcastManagement['runs'][(string) $item->id] ?? [];
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
                'kingdom' => $kingdom?->number,
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
            'media' => $mediaData,
        ]);
    }
}
