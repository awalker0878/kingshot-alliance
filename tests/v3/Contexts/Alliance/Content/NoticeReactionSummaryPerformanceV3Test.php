<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Actions\SetNoticeReaction;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Enums\NoticeReaction;
use App\Contexts\Alliance\Content\Queries\NoticeReactionSummaryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class NoticeReactionSummaryPerformanceV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_reaction_summary_query_count_is_constant_for_multiple_notices(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $owner = $scenarios->player($account->userId, 724001);
        $alliance = $scenarios->alliance($owner);
        $noticeIds = [];

        foreach (range(1, 5) as $index) {
            $noticeId = app(SaveContentItem::class)->handle(
                $alliance->allianceId,
                $owner->playerId,
                $this->announcementAttributes('summary-performance-'.$index),
            );
            app(PublishContentItem::class)->handle($alliance->allianceId, $owner->playerId, $noticeId);
            app(SetNoticeReaction::class)->handle(
                $alliance->allianceId,
                $owner->playerId,
                $noticeId,
                $index % 2 === 0 ? NoticeReaction::Dislike : NoticeReaction::Like,
            );
            $noticeIds[] = $noticeId;
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $summaries = app(NoticeReactionSummaryQuery::class)->forNotices(
            $alliance->allianceId,
            $owner->playerId,
            $noticeIds,
        );
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertCount(5, $summaries);
        self::assertCount(2, $queries, 'Reaction summaries must remain two bounded queries, not one query per Notice.');
    }

    /** @return array<string, mixed> */
    private function announcementAttributes(string $slug): array
    {
        return [
            'category_id' => null,
            'type' => ContentType::Announcement,
            'visibility' => ContentVisibility::Members,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'summary' => null,
            'body' => 'Alliance Notice body.',
            'locale' => 'en',
            'sort_order' => 0,
            'notify_members' => false,
            'source_label' => null,
            'source_url' => null,
            'game_version' => null,
            'reviewed_at' => null,
            'context_links' => [],
        ];
    }
}
