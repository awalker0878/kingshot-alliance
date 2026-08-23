<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\ArchiveContentItem;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Enums\NoticeReaction;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceNoticeReactionHttpBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_active_member_can_react_over_http_without_content_management_authority(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $ownerUser = $scenarios->authUser();
        $ownerUser->forceFill(['email_verified_at' => now()])->save();
        $owner = $scenarios->player((int) $ownerUser->id, 723001);
        $alliance = $scenarios->alliance($owner);
        $noticeId = $this->publishedAnnouncement($alliance->allianceId, $owner->playerId, 'http-member-reaction');

        $memberUser = $scenarios->authUser();
        $memberUser->forceFill(['email_verified_at' => now()])->save();
        $member = $scenarios->player((int) $memberUser->id, 723001);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId,
            'player_id' => $member->playerId,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $contextVersion = $this->versionFor($member);

        $this->actingAs($memberUser)
            ->withSession([(string) config('game_world.active_player_session_key') => $member->playerId])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $contextVersion)
            ->from('/alliance/content')
            ->put('/alliance/content/'.$noticeId.'/reaction', ['reaction' => NoticeReaction::Like->value])
            ->assertRedirect('/alliance/content');

        self::assertDatabaseHas('alliance_notice_reactions', [
            'content_item_id' => $noticeId,
            'player_id' => $member->playerId,
            'reaction' => NoticeReaction::Like->value,
        ]);
    }

    public function test_notice_that_becomes_unavailable_returns_retryable_reaction_validation_error(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $user = $scenarios->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $owner = $scenarios->player((int) $user->id, 723002);
        $alliance = $scenarios->alliance($owner);
        $noticeId = $this->publishedAnnouncement($alliance->allianceId, $owner->playerId, 'http-stale-reaction');
        $contextVersion = $this->versionFor($owner);
        app(ArchiveContentItem::class)->handle($alliance->allianceId, $owner->playerId, $noticeId);

        $this->actingAs($user)
            ->withSession([(string) config('game_world.active_player_session_key') => $owner->playerId])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $contextVersion)
            ->from('/alliance/content')
            ->put('/alliance/content/'.$noticeId.'/reaction', ['reaction' => NoticeReaction::Dislike->value])
            ->assertRedirect('/alliance/content')
            ->assertSessionHasErrors('reaction');

        self::assertDatabaseMissing('alliance_notice_reactions', [
            'content_item_id' => $noticeId,
            'player_id' => $owner->playerId,
        ]);
    }

    public function test_reaction_http_preserves_the_standard_stale_context_precondition(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $user = $scenarios->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $owner = $scenarios->player((int) $user->id, 723003);
        $alliance = $scenarios->alliance($owner);
        $noticeId = $this->publishedAnnouncement($alliance->allianceId, $owner->playerId, 'http-context-precondition');

        $this->actingAs($user)
            ->withSession([(string) config('game_world.active_player_session_key') => $owner->playerId])
            ->put('/alliance/content/'.$noticeId.'/reaction', ['reaction' => NoticeReaction::Like->value])
            ->assertStatus(409)
            ->assertHeader(RequireCurrentPlayerContextVersion::ERROR_HEADER, 'stale')
            ->assertJsonPath('code', 'CONTEXT_STALE');

        self::assertDatabaseMissing('alliance_notice_reactions', [
            'content_item_id' => $noticeId,
            'player_id' => $owner->playerId,
        ]);
    }

    private function publishedAnnouncement(string $allianceId, string $ownerPlayerId, string $slug): string
    {
        $contentId = app(SaveContentItem::class)->handle($allianceId, $ownerPlayerId, [
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
        ]);
        app(PublishContentItem::class)->handle($allianceId, $ownerPlayerId, $contentId);

        return $contentId;
    }

    private function versionFor(PlayerReference $player): string
    {
        $alliance = app(PlayerIdentityContextQuery::class)
            ->forPlayers([$player->playerId])[$player->playerId] ?? null;
        $kingdomPermissions = app(KingdomAuthorityFactsQuery::class)
            ->findCurrent($player->playerId, $player->kingdomId)
            ?->permissionKeysObservedAtRead ?? [];

        return app(PlayerAuthorityContextVersion::class)->issue(
            $player,
            $alliance,
            $kingdomPermissions,
        );
    }
}
