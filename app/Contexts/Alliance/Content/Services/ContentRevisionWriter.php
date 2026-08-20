<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Services;

use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

final class ContentRevisionWriter
{
    public function write(ContentItem $item, PlayerReference $actor): ContentRevision
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
            'notify_members' => $item->notify_members,
            'source_label' => $item->source_label,
            'source_url' => $item->source_url,
            'game_version' => $item->game_version,
            'reviewed_at' => $item->reviewed_at,
            'context_links' => $item->context_links,
            'created_by_player_id' => $actor->playerId,
            'created_at' => now(),
        ]);
    }
}
