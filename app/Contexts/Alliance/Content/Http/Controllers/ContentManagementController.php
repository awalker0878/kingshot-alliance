<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Actions\ArchiveContentItem;
use App\Contexts\Alliance\Content\Actions\ArchiveMediaAsset;
use App\Contexts\Alliance\Content\Actions\DeleteContentCategory;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\RestoreContentRevision;
use App\Contexts\Alliance\Content\Actions\SaveContentCategory;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Actions\UpdateAlliancePublicProfile;
use App\Contexts\Alliance\Content\Actions\UploadMediaAsset;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\AllianceBrandingMedia;
use App\Contexts\Alliance\Content\Models\AllianceProfile;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class ContentManagementController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        ContentQuery $content,
        ContentPresenter $presenter,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
    ): Response {
        $user = $this->user($request);
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
        $media = MediaAsset::query()
            ->where('alliance_id', $scope->allianceId)
            ->orderByDesc('created_at')
            ->get();

        /** @var list<array{id: string, name: string, slug: string, sortOrder: int}> $categoryData */
        $categoryData = [];
        foreach ($categories as $category) {
            $categoryData[] = [
                'id' => (string) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'sortOrder' => (int) $category->sort_order,
            ];
        }

        /** @var list<array<string, mixed>> $contentData */
        $contentData = [];
        foreach ($items as $item) {
            $data = $presenter->item($item, true);
            /** @var list<array{id: string, revisionNumber: int, title: string, createdAt: string|null}> $revisionData */
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
            $contentData[] = $data;
        }

        /** @var list<array{id: string, name: string, mimeType: string, sizeBytes: int, scanStatus: string, lifecycleStatus: string, createdAt: string|null}> $mediaData */
        $mediaData = [];
        foreach ($media as $asset) {
            $mediaData[] = [
                'id' => (string) $asset->id,
                'name' => (string) $asset->original_name,
                'mimeType' => (string) $asset->mime_type,
                'sizeBytes' => (int) $asset->size_bytes,
                'scanStatus' => $asset->scan_status->value,
                'lifecycleStatus' => $asset->lifecycle_status->value,
                'createdAt' => $asset->created_at?->toIso8601String(),
            ];
        }

        return Inertia::render('Alliance/Noticeboard/Manage', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
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
            ], ContentType::cases()),
            'visibilityOptions' => array_map(static fn (ContentVisibility $visibility): array => [
                'value' => $visibility->value,
                'label' => ucfirst($visibility->value),
            ], ContentVisibility::cases()),
            'categories' => $categoryData,
            'content' => $contentData,
            'media' => $mediaData,
        ]);
    }

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

        return back()->with('status', 'public-profile-updated');
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
        ]);

        $saveCategory->handle(
            $scope->allianceId,
            $scope->playerId,
            (string) $validated['name'],
            (string) $validated['slug'],
            (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('status', 'content-category-saved');
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

        return back()->with('status', 'content-category-saved');
    }

    public function destroyCategory(
        Request $request,
        AllianceContext $context,
        DeleteContentCategory $deleteCategory,
        string $category,
    ): RedirectResponse {
        $scope = $context->scope();
        $deleteCategory->handle($scope->allianceId, $scope->playerId, $category);

        return back()->with('status', 'content-category-deleted');
    }

    public function storeContent(
        Request $request,
        AllianceContext $context,
        SaveContentItem $saveContent,
    ): RedirectResponse {
        $scope = $context->scope();
        $validated = $this->validateContent($request, $scope->allianceId);

        $saveContent->handle($scope->allianceId, $scope->playerId, $validated);

        return back()->with('status', 'content-saved');
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

        return back()->with('status', 'content-saved');
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

        return back()->with('status', $scheduledFor?->isFuture() ? 'content-scheduled' : 'content-published');
    }

    public function archiveContent(
        Request $request,
        AllianceContext $context,
        ArchiveContentItem $archive,
        string $content,
    ): RedirectResponse {
        $scope = $context->scope();
        $archive->handle($scope->allianceId, $scope->playerId, $content);

        return back()->with('status', 'content-archived');
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

        return back()->with('status', 'content-revision-restored');
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

        return back()->with('status', 'media-uploaded');
    }

    public function archiveMedia(
        Request $request,
        AllianceContext $context,
        ArchiveMediaAsset $archive,
        string $media,
    ): RedirectResponse {
        $scope = $context->scope();
        $archive->handle($scope->allianceId, $scope->playerId, $media);

        return back()->with('status', 'media-archived');
    }

    private function user(Request $request): AuthenticatedAccount
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        return $user;
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
     *   sort_order: int
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
        ];
    }
}
