<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Services;

use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;

final readonly class ContentPresenter
{
    public function __construct(private ContentFreshness $freshness) {}

    /** @return array<string, mixed> */
    public function item(ContentItem $item, bool $includeBody = false): array
    {
        $category = $item->relationLoaded('category') ? $item->category : $item->category()->first();

        $result = [
            'id' => (string) $item->id,
            'type' => $item->type->value,
            'typeLabel' => $item->type->label(),
            'visibility' => $item->visibility->value,
            'status' => $item->status->value,
            'title' => (string) $item->title,
            'slug' => (string) $item->slug,
            'summary' => $item->summary,
            'locale' => (string) $item->locale,
            'sortOrder' => (int) $item->sort_order,
            'revisionNumber' => (int) $item->current_revision_number,
            'notifyMembers' => (bool) $item->notify_members,
            'provenance' => $this->provenance($item),
            'freshness' => $this->freshness->assess($item),
            'contextLinks' => $item->context_links ?? [],
            'scheduledFor' => $item->scheduled_for?->toIso8601String(),
            'publishedAt' => $item->published_at?->toIso8601String(),
            'broadcastedAt' => $item->broadcasted_at?->toIso8601String(),
            'archivedAt' => $item->archived_at?->toIso8601String(),
            'updatedAt' => $item->updated_at?->toIso8601String(),
            'category' => $category instanceof ContentCategory ? [
                'id' => (string) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
            ] : null,
        ];

        if ($includeBody) {
            $result['body'] = (string) $item->body;
        }

        return $result;
    }

    /**
     * @return array{
     *   sourceLabel: string|null,
     *   sourceUrl: string|null,
     *   gameVersion: string|null,
     *   reviewedAt: string|null
     * }|null
     */
    private function provenance(ContentItem $item): ?array
    {
        if (
            $item->source_label === null
            && $item->source_url === null
            && $item->game_version === null
            && $item->reviewed_at === null
        ) {
            return null;
        }

        return [
            'sourceLabel' => $item->source_label,
            'sourceUrl' => $item->source_url,
            'gameVersion' => $item->game_version,
            'reviewedAt' => $item->reviewed_at?->toDateString(),
        ];
    }
}
