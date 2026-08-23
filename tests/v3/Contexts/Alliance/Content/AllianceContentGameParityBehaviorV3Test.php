<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\RemoveNoticeReaction;
use App\Contexts\Alliance\Content\Actions\SaveAllianceRules;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Actions\SetNoticeReaction;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Enums\NoticeReaction;
use App\Contexts\Alliance\Content\Models\AllianceNoticeReaction;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Queries\NoticeReactionSummaryQuery;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceContentGameParityBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_rules_are_canonical_published_revisioned_and_idempotent(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $save = app(SaveAllianceRules::class);

        $contentId = $save->handle($alliance, $owner, "1. Join rallies.\n2. Follow leadership calls.", 'EN');
        $item = ContentItem::query()->findOrFail($contentId);

        self::assertSame(SaveAllianceRules::SLUG, $item->slug);
        self::assertSame(ContentType::Rule, $item->type);
        self::assertSame(ContentVisibility::Members, $item->visibility);
        self::assertSame(ContentStatus::Published, $item->status);
        self::assertSame('en', $item->locale);
        self::assertFalse((bool) $item->notify_members);
        self::assertNotNull($item->published_at);
        self::assertSame(1, (int) $item->current_revision_number);
        self::assertSame(1, ContentRevision::query()->where('content_item_id', $contentId)->count());
        self::assertSame(1, AuditEvent::query()->where('event', 'content.rules.created')->count());
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'content.rules.created')->count());

        $sameId = $save->handle($alliance, $owner, "1. Join rallies.\n2. Follow leadership calls.", 'en');
        self::assertSame($contentId, $sameId);
        self::assertSame(1, ContentRevision::query()->where('content_item_id', $contentId)->count());
        self::assertSame(1, AuditEvent::query()->whereIn('event', ['content.rules.created', 'content.rules.updated'])->count());
        self::assertSame(1, OutboxMessage::query()->whereIn('event_type', ['content.rules.created', 'content.rules.updated'])->count());

        $save->handle($alliance, $owner, "1. Join rallies.\n2. Follow leadership calls.\n3. Be respectful.", 'en');
        $updated = ContentItem::query()->findOrFail($contentId);
        self::assertSame(2, (int) $updated->current_revision_number);
        self::assertSame(2, ContentRevision::query()->where('content_item_id', $contentId)->count());
        self::assertSame(1, AuditEvent::query()->where('event', 'content.rules.updated')->count());
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'content.rules.updated')->count());
    }

    public function test_generic_content_cannot_claim_the_reserved_alliance_rules_slug(): void
    {
        [$owner, $alliance] = $this->allianceScenario();

        try {
            app(SaveContentItem::class)->handle(
                $alliance,
                $owner,
                $this->contentAttributes(ContentType::Rule, SaveAllianceRules::SLUG),
            );
            self::fail('Expected the canonical Alliance Rules slug to be reserved.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('slug', $exception->errors());
        }
    }

    public function test_reaction_authority_is_active_membership_not_content_management_authority(): void
    {
        [$owner, $alliance, $kingdomNumber] = $this->allianceScenarioWithKingdom();
        $member = $this->ordinaryMember($alliance, $kingdomNumber);
        $noticeId = $this->publishedAnnouncement($alliance, $owner, 'member-reaction-notice');

        self::assertTrue(app(SetNoticeReaction::class)->handle(
            $alliance,
            $member,
            $noticeId,
            NoticeReaction::Like,
        ));
        self::assertDatabaseHas('alliance_notice_reactions', [
            'content_item_id' => $noticeId,
            'player_id' => $member,
            'reaction' => NoticeReaction::Like->value,
        ]);

        $this->expectException(AuthorizationException::class);
        app(SaveAllianceRules::class)->handle($alliance, $member, 'R1 members cannot manage Rules.', 'en');
    }

    public function test_notice_reactions_switch_remove_and_repeat_idempotently(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $noticeId = $this->publishedAnnouncement($alliance, $owner, 'reaction-state-notice');
        $set = app(SetNoticeReaction::class);
        $remove = app(RemoveNoticeReaction::class);

        self::assertTrue($set->handle($alliance, $owner, $noticeId, NoticeReaction::Like));
        self::assertFalse($set->handle($alliance, $owner, $noticeId, NoticeReaction::Like));
        self::assertSame(1, AllianceNoticeReaction::query()->where('content_item_id', $noticeId)->count());
        self::assertSame(1, AuditEvent::query()->where('event', 'content.notice-reaction.set')->count());

        self::assertTrue($set->handle($alliance, $owner, $noticeId, NoticeReaction::Dislike));
        self::assertSame(NoticeReaction::Dislike, AllianceNoticeReaction::query()
            ->where('content_item_id', $noticeId)
            ->where('player_id', $owner)
            ->firstOrFail()
            ->reaction);
        self::assertSame(2, AuditEvent::query()->where('event', 'content.notice-reaction.set')->count());

        self::assertTrue($remove->handle($alliance, $owner, $noticeId));
        self::assertFalse($remove->handle($alliance, $owner, $noticeId));
        self::assertSame(0, AllianceNoticeReaction::query()->where('content_item_id', $noticeId)->count());
        self::assertSame(1, AuditEvent::query()->where('event', 'content.notice-reaction.removed')->count());
    }

    public function test_reactions_reject_non_published_non_notice_and_foreign_alliance_targets(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $draftAnnouncement = app(SaveContentItem::class)->handle(
            $alliance,
            $owner,
            $this->contentAttributes(ContentType::Announcement, 'draft-announcement'),
        );
        $rule = app(SaveContentItem::class)->handle(
            $alliance,
            $owner,
            $this->contentAttributes(ContentType::Rule, 'supplementary-rule'),
        );
        app(PublishContentItem::class)->handle($alliance, $owner, $rule);

        foreach ([$draftAnnouncement, $rule] as $invalidTarget) {
            try {
                app(SetNoticeReaction::class)->handle($alliance, $owner, $invalidTarget, NoticeReaction::Like);
                self::fail('Expected a non-reactable Content target to be rejected.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                self::assertTrue(true);
            }
        }

        [$foreignOwner, $foreignAlliance] = $this->allianceScenario();
        $foreignNotice = $this->publishedAnnouncement($foreignAlliance, $foreignOwner, 'foreign-notice');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(SetNoticeReaction::class)->handle($alliance, $owner, $foreignNotice, NoticeReaction::Dislike);
    }

    public function test_reaction_summary_returns_only_counts_and_current_member_state(): void
    {
        [$owner, $alliance, $kingdomNumber] = $this->allianceScenarioWithKingdom();
        $member = $this->ordinaryMember($alliance, $kingdomNumber);
        $noticeId = $this->publishedAnnouncement($alliance, $owner, 'reaction-summary-notice');

        app(SetNoticeReaction::class)->handle($alliance, $owner, $noticeId, NoticeReaction::Like);
        app(SetNoticeReaction::class)->handle($alliance, $member, $noticeId, NoticeReaction::Dislike);

        $summary = app(NoticeReactionSummaryQuery::class)->forNotices($alliance, $member, [$noticeId]);

        self::assertSame([
            'likes' => 1,
            'dislikes' => 1,
            'current' => 'dislike',
        ], $summary[$noticeId]);
        self::assertSame(['likes', 'dislikes', 'current'], array_keys($summary[$noticeId]));
    }

    public function test_reaction_totals_never_change_noticeboard_content_ordering(): void
    {
        [$owner, $alliance, $kingdomNumber] = $this->allianceScenarioWithKingdom();
        $firstId = $this->publishedAnnouncement($alliance, $owner, 'first-by-content-order', 0);
        $popularId = $this->publishedAnnouncement($alliance, $owner, 'popular-but-second', 50);

        for ($index = 0; $index < 4; $index++) {
            $member = $this->ordinaryMember($alliance, $kingdomNumber);
            app(SetNoticeReaction::class)->handle($alliance, $member, $popularId, NoticeReaction::Like);
        }

        $items = app(ContentQuery::class)->memberList($alliance);
        $ids = $items->map(static fn (ContentItem $item): string => (string) $item->id)->all();

        self::assertSame($firstId, $ids[0]);
        self::assertSame($popularId, $ids[1]);
        self::assertSame(4, AllianceNoticeReaction::query()
            ->where('content_item_id', $popularId)
            ->where('reaction', NoticeReaction::Like->value)
            ->count());
    }

    public function test_deleting_notice_cascades_reactions(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $noticeId = $this->publishedAnnouncement($alliance, $owner, 'cascade-reaction-notice');
        app(SetNoticeReaction::class)->handle($alliance, $owner, $noticeId, NoticeReaction::Like);

        ContentItem::query()->findOrFail($noticeId)->delete();

        self::assertSame(0, AllianceNoticeReaction::query()->where('content_item_id', $noticeId)->count());
    }

    /** @return array{0:string,1:string} */
    private function allianceScenario(): array
    {
        [$owner, $alliance] = $this->allianceScenarioWithKingdom();

        return [$owner, $alliance];
    }

    /** @return array{0:string,1:string,2:int} */
    private function allianceScenarioWithKingdom(): array
    {
        static $kingdomNumber = 710000;
        $kingdomNumber++;
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $owner = $scenarios->player($account->userId, $kingdomNumber);
        $alliance = $scenarios->alliance($owner);

        return [$owner->playerId, $alliance->allianceId, $kingdomNumber];
    }

    private function ordinaryMember(string $allianceId, int $kingdomNumber): string
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, $kingdomNumber);

        AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'player_id' => $player->playerId,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        return $player->playerId;
    }

    private function publishedAnnouncement(
        string $allianceId,
        string $ownerPlayerId,
        string $slug,
        int $sortOrder = 0,
    ): string {
        $contentId = app(SaveContentItem::class)->handle(
            $allianceId,
            $ownerPlayerId,
            $this->contentAttributes(ContentType::Announcement, $slug, $sortOrder),
        );
        app(PublishContentItem::class)->handle($allianceId, $ownerPlayerId, $contentId);

        return $contentId;
    }

    /** @return array<string, mixed> */
    private function contentAttributes(ContentType $type, string $slug, int $sortOrder = 0): array
    {
        return [
            'category_id' => null,
            'type' => $type,
            'visibility' => ContentVisibility::Members,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'summary' => null,
            'body' => 'Alliance content body.',
            'locale' => 'en',
            'sort_order' => $sortOrder,
            'notify_members' => false,
            'source_label' => null,
            'source_url' => null,
            'game_version' => null,
            'reviewed_at' => null,
            'context_links' => [],
        ];
    }
}
