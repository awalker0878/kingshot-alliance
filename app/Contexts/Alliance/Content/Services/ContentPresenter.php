<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Services;

use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;

final class ContentPresenter
{
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
}
