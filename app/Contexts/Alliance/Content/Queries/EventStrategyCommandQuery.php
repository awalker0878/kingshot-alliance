<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Queries;

use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\ContentFreshness;

final readonly class EventStrategyCommandQuery
{
    public function __construct(
        private ContentQuery $content,
        private ContentFreshness $freshness,
    ) {}

    /**
     * @return array{
     *     guideCount:int,
     *     guide:?array{id:string,slug:string,title:string,revisionNumber:int,publishedAt:?string,sourceLabel:?string,reviewedAt:?string,freshness:array{status:string,dueAt:?string,daysUntilDue:?int}}
     * }
     */
    public function forEventType(string $allianceId, string $eventTypeSlug): array
    {
        $items = $this->content->contextualForEventType($allianceId, $eventTypeSlug);
        $item = $items->first();
        $guide = null;

        if ($item instanceof ContentItem) {
            $guide = [
                'id' => (string) $item->id,
                'slug' => (string) $item->slug,
                'title' => (string) $item->title,
                'revisionNumber' => (int) $item->current_revision_number,
                'publishedAt' => $item->published_at?->toIso8601String(),
                'sourceLabel' => $item->source_label,
                'reviewedAt' => $item->reviewed_at?->toDateString(),
                'freshness' => $this->freshness->assess($item),
            ];
        }

        return [
            'guideCount' => $items->count(),
            'guide' => $guide,
        ];
    }
}
