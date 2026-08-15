<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\ContentRevisionWriter;
use App\Contexts\Alliance\Content\Services\ContentSanitizer;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveContentItem
{
    public function __construct(
        private AllianceMutationAuthority $authority,
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
    public function handle(Alliance $alliance, Player $actor, array $attributes, ?string $contentItemId = null): ContentItem
    {
        return DB::transaction(function () use ($alliance, $actor, $attributes, $contentItemId): ContentItem {
            $context = $this->authority->require($actor, $alliance, AlliancePermission::ContentManage);
            $categoryId = $attributes['category_id'] ?? null;
            $this->assertCategory($context->alliance, $categoryId);

            $item = $contentItemId === null
                ? new ContentItem([
                    'alliance_id' => $context->alliance->id,
                    'created_by_player_id' => $context->actor->id,
                    'current_revision_number' => 1,
                ])
                : ContentItem::query()
                    ->where('id', $contentItemId)
                    ->where('alliance_id', $context->alliance->id)
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
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            $revision = $this->revisions->write($item, $context->actor);
            $event = $contentItemId === null ? 'content.created' : 'content.updated';

            $this->audit->record($event, $context->actor, $item, $context->alliance, [
                'revision_number' => $revision->revision_number,
                'visibility' => $item->visibility->value,
                'type' => $item->type->value,
            ]);
            $this->outbox->record($event, (string) $context->alliance->id, $item, [
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

        $category = ContentCategory::query()
            ->where('id', $categoryId)
            ->where('alliance_id', $alliance->id)
            ->sharedLock()
            ->first();

        if (! $category instanceof ContentCategory) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category does not belong to this alliance.',
            ]);
        }
    }
}
