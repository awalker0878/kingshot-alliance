<?php

declare(strict_types=1);

namespace Tests\ReadModels\AnnouncementBroadcastManagement\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/** Isolated browser data; resets only this fixture's own subject records. */
final class ContentManagementPaginationFixture
{
    public static function seed(): void
    {
        $kingdom = Kingdom::query()->firstOrCreate(['number' => 1764], ['status' => 'active']);
        $user = User::query()->where('email', 'content-manager-pages@example.test')->first()
            ?? User::factory()->create(['name' => 'Content Manager', 'email' => 'content-manager-pages@example.test',
                'password' => Hash::make('password'), 'timezone' => 'UTC', 'email_verified_at' => now()]);
        $player = Player::query()->firstOrCreate(['game_player_id' => 'CONTENT-MANAGER-PAGES', 'current_kingdom_id' => $kingdom->id],
            ['user_id' => $user->id, 'current_name' => 'Content Manager']);
        $alliance = Alliance::query()->where('slug', 'content-manager-pages')->first();
        $id = $alliance === null ? app(CreateAlliance::class)->handle((int) $user->id, (string) $player->id,
            'Content Manager Pages', 'content-manager-pages', 'en', 'UTC') : (string) $alliance->id;
        ContentItem::query()->where('alliance_id', $id)->where('slug', 'like', 'manager-page-%')->delete();
        ContentCategory::query()->where('alliance_id', $id)->where('slug', 'like', 'manager-category-%')->delete();
        MediaAsset::query()->where('alliance_id', $id)->where('path', 'like', 'manager-pages/%')->delete();
        $image = file_get_contents(public_path('images/app-icons/icon-192.png'));
        if (! is_string($image)) {
            throw new \RuntimeException('The fixture icon must be available.');
        }
        $oldCategory = null;
        for ($i = 0; $i < 31; $i++) {
            $category = ContentCategory::query()->create(['alliance_id' => $id, 'name' => 'Manager category '.$i,
                'slug' => 'manager-category-'.$i, 'sort_order' => $i]);
            $oldCategory ??= (string) $category->id;
            Storage::disk('local')->put('manager-pages/'.$i, $image);
            MediaAsset::query()->create(['alliance_id' => $id, 'original_name' => 'Manager image '.$i,
                'disk' => 'local', 'path' => 'manager-pages/'.$i, 'mime_type' => 'image/png', 'size_bytes' => strlen($image),
                'uploaded_by_player_id' => $player->id, 'sha256' => hash('sha256', $image),
                'scan_status' => 'clean', 'lifecycle_status' => 'active']);
        }
        for ($i = 0; $i < 40; $i++) {
            $item = ContentItem::query()->create(['alliance_id' => $id, 'category_id' => $oldCategory,
                'type' => 'announcement', 'visibility' => 'members', 'status' => 'published', 'title' => 'Manager page '.$i,
                'slug' => 'manager-page-'.$i, 'body' => 'Preserved editor body '.$i, 'locale' => 'en',
                'sort_order' => 0, 'current_revision_number' => 1, 'notify_members' => $i === 39,
                'published_at' => now(), 'created_by_player_id' => $player->id, 'updated_by_player_id' => $player->id]);
            if ($i !== 39) {
                continue;
            }
            for ($n = 1; $n <= 12; $n++) {
                ContentRevision::query()->create(['alliance_id' => $id, 'content_item_id' => $item->id,
                    'revision_number' => $n, 'type' => 'announcement', 'visibility' => 'members', 'title' => 'Retained revision '.$n,
                    'body' => 'Preserved revision body '.$n, 'locale' => 'en', 'sort_order' => 0, 'created_by_player_id' => $player->id]);
                AnnouncementBroadcastRun::query()->create(['alliance_id' => $id, 'content_item_id' => $item->id,
                    'content_revision_number' => $n, 'status' => 'queued', 'scheduled_for' => now(), 'queued_at' => now(),
                    'last_visited_at' => now(), 'recipient_count' => 0, 'idempotency_key' => 'manager-pages:'.$item->id.':'.$n]);
            }
        }
    }
}
