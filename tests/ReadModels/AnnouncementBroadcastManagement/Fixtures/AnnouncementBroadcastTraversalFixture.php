<?php

declare(strict_types=1);

namespace Tests\ReadModels\AnnouncementBroadcastManagement\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Content\Actions\MaterializeAnnouncementBroadcastRuns;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\QueueAnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Support\Facades\Hash;

final class AnnouncementBroadcastTraversalFixture
{
    public static function seed(): void
    {
        $kingdom = Kingdom::query()->firstOrCreate(['number' => 1763], ['status' => 'active']);
        $players = [];
        for ($i = 0; $i < 3; $i++) {
            $user = User::query()->where('email', 'broadcast-progress-'.$i.'@example.test')->first()
                ?? User::factory()->create(['name' => 'Broadcast governor '.$i,
                    'email' => 'broadcast-progress-'.$i.'@example.test', 'password' => Hash::make('password'),
                    'timezone' => 'UTC', 'email_verified_at' => now()]);
            $players[] = Player::query()->firstOrCreate(['game_player_id' => 'BROADCAST-PROGRESS-'.$i,
                'current_kingdom_id' => $kingdom->id], ['user_id' => $user->id, 'current_name' => 'Broadcast governor '.$i]);
        }
        $alliance = Alliance::query()->where('slug', 'broadcast-progress-fixture')->first();
        $allianceId = $alliance === null ? app(CreateAlliance::class)->handle((int) $players[0]->user_id,
            (string) $players[0]->id, 'Broadcast Progress', 'broadcast-progress-fixture', 'en', 'UTC') : (string) $alliance->id;
        foreach (array_slice($players, 1) as $player) {
            AllianceMembership::query()->firstOrCreate(['alliance_id' => $allianceId, 'player_id' => $player->id],
                ['status' => MembershipStatus::Active, 'rank' => AllianceRank::R1, 'joined_at' => now()]);
        }
        $existing = ContentItem::query()->where('alliance_id', $allianceId)->where('slug', 'broadcast-progress-fixture')->first();
        if ($existing !== null) {
            // This private fixture only: never reset another owner's database state.
            AnnouncementBroadcastRun::query()->where('alliance_id', $allianceId)->where('content_item_id', $existing->id)->delete();
        }
        $id = app(SaveContentItem::class)->handle($allianceId, (string) $players[0]->id, [
            'type' => ContentType::Announcement, 'visibility' => ContentVisibility::Members,
            'title' => 'Resumable broadcast fixture', 'slug' => 'broadcast-progress-fixture',
            'body' => 'Current instructions for active members.', 'locale' => 'en', 'notify_members' => true,
        ], $existing === null ? null : (string) $existing->id);
        app(PublishContentItem::class)->handle($allianceId, (string) $players[0]->id, $id);
        app(MaterializeAnnouncementBroadcastRuns::class)->handle(100);
        $run = AnnouncementBroadcastRun::query()->where('content_item_id', $id)->sole();
        app(QueueAnnouncementBroadcastRun::class)->handle((string) $run->id, 1);
    }

    public static function finish(): void
    {
        $item = ContentItem::query()->where('slug', 'broadcast-progress-fixture')->sole();
        $run = AnnouncementBroadcastRun::query()->where('content_item_id', $item->id)->sole();
        app(QueueAnnouncementBroadcastRun::class)->handle((string) $run->id, 25);
    }
}
