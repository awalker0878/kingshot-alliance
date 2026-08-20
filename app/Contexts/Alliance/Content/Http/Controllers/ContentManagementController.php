<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Http\Controllers;

use App\Contexts\Alliance\Content\Actions\ArchiveContentItem;
use App\Contexts\Alliance\Content\Actions\ArchiveMediaAsset;
use App\Contexts\Alliance\Content\Actions\CancelAnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Actions\DeleteContentCategory;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\RestoreContentRevision;
use App\Contexts\Alliance\Content\Actions\RetryAnnouncementBroadcastFailures;
use App\Contexts\Alliance\Content\Actions\SaveAnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Actions\SaveContentCategory;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Actions\TestAnnouncementBroadcast;
use App\Contexts\Alliance\Content\Actions\UpdateAlliancePublicProfile;
use App\Contexts\Alliance\Content\Actions\UploadMediaAsset;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

final class ContentManagementController extends Controller
{
    public function updateProfile(
        Request $request,
        AllianceContext $context,
        UpdateAlliancePublicProfile $updateProfile,
    ): RedirectResponse {
        $scope = $context->scope();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'language' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/'],
            'timezone' => ['required', 'timezone'],
            'description' => ['nullable', 'string', 'max:5000'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_media_id' => ['nullable', 'string', 'ulid'],
            'banner_media_id' => ['nullable', 'string', 'ulid'],
        ]);

        $updateProfile->handle($scope->allianceId, $scope->playerId, $validated);

        return back()->with('actionReceipt', $this->receipt('public-profile-updated'));
    }

    public function storeCategory(
        Request $request,
        AllianceContext $context,
        SaveContentCategory $saveCategory,
    ): RedirectResponse {
        $scope = $context->scope();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('content_categories', 'slug')->where('alliance_id', $scope->allianceId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notify_members' => ['nullable', 'boolean'],
        ]);

        $saveCategory->handle(
            $scope->allianceId,
            $scope->playerId,
            (string) $validated['name'],
            (string) $validated['slug'],
            (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('actionReceipt', $this->receipt('content-category-saved'));
    }

    public function updateCategory(
        Request $request,
        AllianceContext $context,
        SaveContentCategory $saveCategory,
        string $category,
    ): RedirectResponse {
        $scope = $context->scope();
        $existing = ContentCategory::query()
            ->where('id', $category)
            ->where('alliance_id', $scope->allianceId)
            ->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('content_categories', 'slug')
                    ->where('alliance_id', $scope->allianceId)
                    ->ignore($existing->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $saveCategory->handle(
            $scope->allianceId,
            $scope->playerId,
            (string) $validated['name'],
            (string) $validated['slug'],
            (int) ($validated['sort_order'] ?? 0),
            (string) $existing->id,
        );

        return back()->with('actionReceipt', $this->receipt('content-category-saved'));
    }

    public function destroyCategory(
        Request $request,
        AllianceContext $context,
        DeleteContentCategory $deleteCategory,
        string $category,
    ): RedirectResponse {
        $scope = $context->scope();
        $deleteCategory->handle($scope->allianceId, $scope->playerId, $category);

        return back()->with('actionReceipt', $this->receipt('content-category-deleted'));
    }

    public function storeContent(
        Request $request,
        AllianceContext $context,
        SaveContentItem $saveContent,
    ): RedirectResponse {
        $scope = $context->scope();
        $validated = $this->validateContent($request, $scope->allianceId);

        $saveContent->handle($scope->allianceId, $scope->playerId, $validated);

        return back()->with('actionReceipt', $this->receipt('content-saved'));
    }

    public function updateContent(
        Request $request,
        AllianceContext $context,
        SaveContentItem $saveContent,
        string $content,
    ): RedirectResponse {
        $scope = $context->scope();
        $existing = ContentItem::query()
            ->where('id', $content)
            ->where('alliance_id', $scope->allianceId)
            ->firstOrFail();
        $validated = $this->validateContent($request, $scope->allianceId, $existing);

        $saveContent->handle($scope->allianceId, $scope->playerId, $validated, (string) $existing->id);

        return back()->with('actionReceipt', $this->receipt('content-saved'));
    }

    public function publishContent(
        Request $request,
        AllianceContext $context,
        PublishContentItem $publish,
        string $content,
    ): RedirectResponse {
        $validated = $request->validate([
            'scheduled_for' => ['nullable', 'date'],
        ]);
        $scheduledFor = isset($validated['scheduled_for'])
            ? Carbon::parse((string) $validated['scheduled_for'])->utc()
            : null;

        $scope = $context->scope();
        $publish->handle($scope->allianceId, $scope->playerId, $content, $scheduledFor);

        return back()->with(
            'actionReceipt',
            $this->receipt($scheduledFor?->isFuture() ? 'content-scheduled' : 'content-published'),
        );
    }

    public function archiveContent(
        Request $request,
        AllianceContext $context,
        ArchiveContentItem $archive,
        string $content,
    ): RedirectResponse {
        $scope = $context->scope();
        $archive->handle($scope->allianceId, $scope->playerId, $content);

        return back()->with('actionReceipt', $this->receipt('content-archived'));
    }

    public function restoreRevision(
        Request $request,
        AllianceContext $context,
        RestoreContentRevision $restore,
        string $content,
        string $revision,
    ): RedirectResponse {
        $scope = $context->scope();
        $restore->handle($scope->allianceId, $scope->playerId, $content, $revision);

        return back()->with('actionReceipt', $this->receipt('content-revision-restored'));
    }

    public function saveBroadcastSchedule(
        Request $request,
        AllianceContext $context,
        SaveAnnouncementBroadcastSchedule $saveSchedule,
        string $content,
    ): RedirectResponse {
        $validated = $request->validate([
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'local_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);
        $scope = $context->scope();
        /** @var non-empty-list<int> $weekdays */
        $weekdays = array_values(array_map('intval', $validated['weekdays']));
        $saveSchedule->handle(
            $scope->allianceId,
            $scope->playerId,
            $content,
            $weekdays,
            (string) $validated['local_time'],
            (string) $validated['timezone'],
            isset($validated['ends_at']) ? (string) $validated['ends_at'] : null,
        );

        return back()->with('actionReceipt', $this->receipt('content-broadcast-schedule-saved'));
    }

    public function cancelBroadcastSchedule(
        Request $request,
        AllianceContext $context,
        CancelAnnouncementBroadcastSchedule $cancelSchedule,
        string $schedule,
    ): RedirectResponse {
        $scope = $context->scope();
        $cancelSchedule->handle($scope->allianceId, $scope->playerId, $schedule);

        return back()->with('actionReceipt', $this->receipt('content-broadcast-schedule-cancelled'));
    }

    public function testBroadcast(
        Request $request,
        AllianceContext $context,
        TestAnnouncementBroadcast $testBroadcast,
        string $content,
    ): RedirectResponse {
        $scope = $context->scope();
        $channels = $testBroadcast->handle($scope->allianceId, $scope->playerId, $content);

        return back()->with('actionReceipt', $this->receipt('content-broadcast-test-queued', [
            'count' => count($channels),
            'channels' => implode(', ', $channels),
        ]));
    }

    public function retryBroadcastFailures(
        Request $request,
        AllianceContext $context,
        RetryAnnouncementBroadcastFailures $retryFailures,
        string $run,
    ): RedirectResponse {
        $validated = $request->validate([
            'delivery_ids' => ['required', 'array', 'min:1', 'max:50'],
            'delivery_ids.*' => ['required', 'string', 'ulid', 'distinct'],
        ]);
        /** @var non-empty-list<string> $deliveryIds */
        $deliveryIds = array_values(array_map('strval', $validated['delivery_ids']));
        $scope = $context->scope();
        $retried = $retryFailures->handle(
            $scope->allianceId,
            $scope->playerId,
            $run,
            $deliveryIds,
        );

        return back()->with('actionReceipt', $this->receipt('content-broadcast-failures-retried', [
            'count' => $retried,
        ]));
    }

    public function storeMedia(
        Request $request,
        AllianceContext $context,
        UploadMediaAsset $upload,
    ): RedirectResponse {
        $maxKilobytes = max(1, (int) config('content.media_max_kilobytes', 8192));
        $mimes = implode(',', (array) config('content.media_mime_types', []));
        $validated = $request->validate([
            'media' => ['required', 'file', 'max:'.$maxKilobytes, 'mimetypes:'.$mimes],
        ]);
        $file = $validated['media'] ?? null;
        abort_unless($file instanceof UploadedFile, 422);

        $scope = $context->scope();
        $upload->handle($scope->allianceId, $scope->playerId, $file);

        return back()->with('actionReceipt', $this->receipt('media-uploaded'));
    }

    public function archiveMedia(
        Request $request,
        AllianceContext $context,
        ArchiveMediaAsset $archive,
        string $media,
    ): RedirectResponse {
        $scope = $context->scope();
        $archive->handle($scope->allianceId, $scope->playerId, $media);

        return back()->with('actionReceipt', $this->receipt('media-archived'));
    }

    /**
     * @return array{
     *   category_id: string|null,
     *   type: ContentType,
     *   visibility: ContentVisibility,
     *   title: string,
     *   slug: string,
     *   summary: string|null,
     *   body: string,
     *   locale: string,
     *   sort_order: int,
     *   notify_members: bool,
     *   source_label: string|null,
     *   source_url: string|null,
     *   game_version: string|null,
     *   reviewed_at: string|null,
     *   context_links: list<array{type:string,key:string}>
     * }
     */
    private function validateContent(Request $request, string $allianceId, ?ContentItem $existing = null): array
    {
        $slugRule = Rule::unique('content_items', 'slug')->where('alliance_id', $allianceId);

        if ($existing instanceof ContentItem) {
            $slugRule->ignore($existing->id);
        }

        $validated = $request->validate([
            'category_id' => ['nullable', 'string', 'ulid'],
            'type' => ['required', Rule::enum(ContentType::class)],
            'visibility' => ['required', Rule::enum(ContentVisibility::class)],
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugRule],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'locale' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notify_members' => ['nullable', 'boolean'],
            'source_label' => ['nullable', 'string', 'max:180'],
            'source_url' => ['nullable', 'string', 'max:2048', 'url:https'],
            'game_version' => ['nullable', 'string', 'max:64'],
            'reviewed_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'event_type_slugs' => ['nullable', 'array', 'max:20'],
            'event_type_slugs.*' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'distinct',
            ],
        ]);

        return [
            'category_id' => isset($validated['category_id']) ? (string) $validated['category_id'] : null,
            'type' => ContentType::from((string) $validated['type']),
            'visibility' => ContentVisibility::from((string) $validated['visibility']),
            'title' => (string) $validated['title'],
            'slug' => (string) $validated['slug'],
            'summary' => isset($validated['summary']) ? (string) $validated['summary'] : null,
            'body' => (string) $validated['body'],
            'locale' => (string) $validated['locale'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'notify_members' => (bool) ($validated['notify_members'] ?? false),
            'source_label' => isset($validated['source_label']) ? (string) $validated['source_label'] : null,
            'source_url' => isset($validated['source_url']) ? (string) $validated['source_url'] : null,
            'game_version' => isset($validated['game_version']) ? (string) $validated['game_version'] : null,
            'reviewed_at' => isset($validated['reviewed_at']) ? (string) $validated['reviewed_at'] : null,
            'context_links' => array_values(array_map(
                static fn (mixed $slug): array => ['type' => 'event_type', 'key' => (string) $slug],
                (array) ($validated['event_type_slugs'] ?? []),
            )),
        ];
    }
}
