<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\ContentType;
use App\Domain\Content\Enums\ContentVisibility;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Services\ContentRevisionWriter;
use App\Domain\Content\Services\ContentSanitizer;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveContentItem
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ContentSanitizer $sanitizer,
        private ContentRevisionWriter $revisions,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{
     *   category_id?: string|null,
     *   type: ContentType,
     *   visibility: ContentVisibility,
     *   title: string,
     *   slug: string,
     *   summary?: string|null,
     *   body: string,
     *   locale: string,
     *   sort_order?: int
     * } $attributes
     */
    public function handle(Alliance $alliance, User $actor, array $attributes, ?string $contentItemId = null): ContentItem
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::ContentManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $attributes, $contentItemId): ContentItem {
            $categoryId = $attributes['category_id'] ?? null;
            $this->assertCategory($alliance, $categoryId);

            $item = $contentItemId === null
                ? new ContentItem([
                    'alliance_id' => $alliance->id,
                    'created_by_user_id' => $actor->id,
                    'current_revision_number' => 1,
                ])
                : ContentItem::query()
                    ->where('id', $contentItemId)
                    ->where('alliance_id', $alliance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if ($contentItemId !== null) {
                $item->current_revision_number = (int) $item->current_revision_number + 1;
            }

            $item->forceFill([
                'category_id' => $categoryId,
                'type' => $attributes['type'],
                'visibility' => $attributes['visibility'],
                'status' => ContentStatus::Draft,
                'title' => $this->sanitizer->line($attributes['title']) ?? 'Untitled',
                'slug' => strtolower(trim($attributes['slug'])),
                'summary' => $this->sanitizer->line($attributes['summary'] ?? null),
                'body' => $this->sanitizer->body($attributes['body']),
                'locale' => strtolower(trim($attributes['locale'])),
                'sort_order' => max(0, (int) ($attributes['sort_order'] ?? 0)),
                'scheduled_for' => null,
                'published_at' => null,
                'archived_at' => null,
                'updated_by_user_id' => $actor->id,
            ])->save();

            $revision = $this->revisions->write($item, $actor);
            $event = $contentItemId === null ? 'content.created' : 'content.updated';

            $this->audit->record($event, $actor, $item, $alliance, [
                'revision_number' => $revision->revision_number,
                'visibility' => $item->visibility->value,
                'type' => $item->type->value,
            ]);
            $this->outbox->record($event, (string) $alliance->id, $item, [
                'content_item_id' => $item->id,
                'revision_number' => $revision->revision_number,
                'visibility' => $item->visibility->value,
                'type' => $item->type->value,
            ]);

            return $item->refresh();
        });
    }

    private function assertCategory(Alliance $alliance, ?string $categoryId): void
    {
        if ($categoryId === null || $categoryId === '') {
            return;
        }

        if (! ContentCategory::query()
            ->where('id', $categoryId)
            ->where('alliance_id', $alliance->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category does not belong to this alliance.',
            ]);
        }
    }
}
