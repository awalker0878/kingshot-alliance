<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Models\ContentItem;
use App\Models\ContentRevision;
use App\Models\User;

final class ContentRevisionWriter
{
    public function write(ContentItem $item, User $actor): ContentRevision
    {
        return ContentRevision::query()->create([
            'alliance_id' => $item->alliance_id,
            'content_item_id' => $item->id,
            'revision_number' => $item->current_revision_number,
            'category_id' => $item->category_id,
            'type' => $item->type,
            'visibility' => $item->visibility,
            'title' => $item->title,
            'summary' => $item->summary,
            'body' => $item->body,
            'locale' => $item->locale,
            'sort_order' => $item->sort_order,
            'created_by_user_id' => $actor->id,
            'created_at' => now(),
        ]);
    }
}
