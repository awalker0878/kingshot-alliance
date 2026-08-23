<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Queries;

use App\Contexts\Alliance\Content\Enums\NoticeReaction;
use App\Contexts\Alliance\Content\Models\AllianceNoticeReaction;
use Illuminate\Support\Collection;

final class NoticeReactionSummaryQuery
{
    /**
     * @param list<string> $contentItemIds
     * @return array<string, array{likes:int, dislikes:int, current:string|null}>
     */
    public function forNotices(string $allianceId, string $playerId, array $contentItemIds): array
    {
        $contentItemIds = array_values(array_unique(array_filter(
            $contentItemIds,
            static fn (string $id): bool => $id !== '',
        )));

        if ($contentItemIds === []) {
            return [];
        }

        $summaries = [];
        foreach ($contentItemIds as $contentItemId) {
            $summaries[$contentItemId] = ['likes' => 0, 'dislikes' => 0, 'current' => null];
        }

        /** @var Collection<int, AllianceNoticeReaction> $counts */
        $counts = AllianceNoticeReaction::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('content_item_id', $contentItemIds)
            ->select(['content_item_id', 'reaction'])
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('content_item_id', 'reaction')
            ->get();

        foreach ($counts as $row) {
            $contentItemId = (string) $row->content_item_id;
            $count = (int) $row->getAttribute('aggregate');

            if (! isset($summaries[$contentItemId])) {
                continue;
            }

            if ($row->reaction === NoticeReaction::Like) {
                $summaries[$contentItemId]['likes'] = $count;
            } elseif ($row->reaction === NoticeReaction::Dislike) {
                $summaries[$contentItemId]['dislikes'] = $count;
            }
        }

        /** @var Collection<int, AllianceNoticeReaction> $current */
        $current = AllianceNoticeReaction::query()
            ->where('alliance_id', $allianceId)
            ->where('player_id', $playerId)
            ->whereIn('content_item_id', $contentItemIds)
            ->get(['content_item_id', 'reaction']);

        foreach ($current as $row) {
            $contentItemId = (string) $row->content_item_id;
            if (isset($summaries[$contentItemId])) {
                $summaries[$contentItemId]['current'] = $row->reaction->value;
            }
        }

        return $summaries;
    }
}
