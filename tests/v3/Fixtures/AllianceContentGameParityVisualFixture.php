<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\SaveAllianceRules;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Actions\SetNoticeReaction;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Enums\NoticeReaction;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

final class AllianceContentGameParityVisualFixture
{
    public static function seed(): void
    {
        if (User::query()->where('email', 'content-visual@example.test')->exists()) {
            return;
        }

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-23 16:00:00', 'UTC'));

        $user = User::factory()->create([
            'name' => 'Alliance Content Visual',
            'email' => 'content-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $kingdom = Kingdom::query()->create(['number' => 1723, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'CONTENT-VISUAL-A',
            'current_name' => 'Frost Marshal',
        ]);
        $member = Player::query()->create([
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'CONTENT-VISUAL-B',
            'current_name' => 'Ember Scout',
        ]);

        $allianceId = app(CreateAlliance::class)->handle(
            (string) $actor->id,
            'Winter Vanguard',
            'winter-vanguard',
            'en',
            'UTC',
        );
        AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'player_id' => $member->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        app(SaveAllianceRules::class)->handle(
            $allianceId,
            (string) $actor->id,
            "1. Join Bear Hunt rallies on time.\n2. Follow R4/R5 battle calls.\n3. Keep Alliance chat respectful.\n4. Tell leadership before a long absence.",
            'en',
        );

        $noticeId = app(SaveContentItem::class)->handle($allianceId, (string) $actor->id, [
            'category_id' => null,
            'type' => ContentType::Announcement,
            'visibility' => ContentVisibility::Members,
            'title' => 'Bear Hunt Rally Window',
            'slug' => 'bear-hunt-rally-window',
            'summary' => 'Rally leaders open five minutes before Bear Hunt starts.',
            'body' => 'Be ready at 19:55 UTC. Join the marked rallies first and keep your strongest march available for the opening wave.',
            'locale' => 'en',
            'sort_order' => 0,
            'notify_members' => false,
            'source_label' => null,
            'source_url' => null,
            'game_version' => null,
            'reviewed_at' => null,
            'context_links' => [],
        ]);
        app(PublishContentItem::class)->handle($allianceId, (string) $actor->id, $noticeId);

        app(SetNoticeReaction::class)->handle(
            $allianceId,
            (string) $actor->id,
            $noticeId,
            NoticeReaction::Like,
        );
        app(SetNoticeReaction::class)->handle(
            $allianceId,
            (string) $member->id,
            $noticeId,
            NoticeReaction::Dislike,
        );
    }
}
