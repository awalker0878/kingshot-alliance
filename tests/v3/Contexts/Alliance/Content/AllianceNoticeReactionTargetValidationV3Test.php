<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\ArchiveContentItem;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Actions\SetNoticeReaction;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Enums\NoticeReaction;
use App\Contexts\Alliance\Content\Models\AllianceNoticeReaction;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceNoticeReactionTargetValidationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_and_archived_announcements_reject_reactions(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $save = app(SaveContentItem::class);
        $publish = app(PublishContentItem::class);

        $scheduled = $save->handle(
            $alliance,
            $owner,
            $this->announcementAttributes('scheduled-reaction-target'),
        );
        $publish->handle($alliance, $owner, $scheduled, now()->addHour());

        $archived = $save->handle(
            $alliance,
            $owner,
            $this->announcementAttributes('archived-reaction-target'),
        );
        $publish->handle($alliance, $owner, $archived);
        app(ArchiveContentItem::class)->handle($alliance, $owner, $archived);

        foreach ([$scheduled, $archived] as $invalidTarget) {
            try {
                app(SetNoticeReaction::class)->handle(
                    $alliance,
                    $owner,
                    $invalidTarget,
                    NoticeReaction::Like,
                );
                self::fail('Expected an unavailable Alliance Notice to reject reactions.');
            } catch (ModelNotFoundException) {
                self::assertTrue(true);
            }
        }

        self::assertSame(0, AllianceNoticeReaction::query()->count());
    }

    public function test_losing_active_membership_removes_reaction_write_authority(): void
    {
        [$owner, $alliance, $kingdomNumber] = $this->allianceScenarioWithKingdom();
        $notice = $this->publishedAnnouncement($alliance, $owner, 'membership-loss-reaction-target');
        $member = $this->ordinaryMember($alliance, $kingdomNumber);

        AllianceMembership::query()
            ->where('alliance_id', $alliance)
            ->where('player_id', $member)
            ->update(['status' => MembershipStatus::Left->value]);

        $this->expectException(AuthorizationException::class);
        app(SetNoticeReaction::class)->handle($alliance, $member, $notice, NoticeReaction::Dislike);
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
        static $kingdomNumber = 721000;
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

    private function publishedAnnouncement(string $allianceId, string $ownerPlayerId, string $slug): string
    {
        $contentId = app(SaveContentItem::class)->handle(
            $allianceId,
            $ownerPlayerId,
            $this->announcementAttributes($slug),
        );
        app(PublishContentItem::class)->handle($allianceId, $ownerPlayerId, $contentId);

        return $contentId;
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
