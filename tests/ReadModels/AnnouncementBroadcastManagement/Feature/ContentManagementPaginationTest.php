<?php

declare(strict_types=1);

namespace Tests\ReadModels\AnnouncementBroadcastManagement\Feature;

use App\Contexts\Alliance\Content\Actions\RetryAnnouncementBroadcastFailures;
use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Content\Queries\ContentManagementQuery;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class ContentManagementPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_is_bounded_and_continues_without_losing_tied_items_or_global_counts(): void
    {
        [$user, $player, $alliance] = $this->owner();
        $items = [];
        for ($i = 0; $i < 47; $i++) {
            $items[] = (string) $this->item($alliance->allianceId, $player->playerId, $i)->id;
        }
        $this->asOwner($user, $player->playerId);
        $first = $this->get('/alliance/content/manage')->assertOk();
        $first->assertJsonCount(20, 'props.content');
        $first->assertJsonPath('props.totals.content', 47);
        $first->assertJsonPath('props.catalogue.total', 47);
        $seen = array_column($first->json('props.content'), 'id');
        $cursor = $first->json('props.catalogue.nextCursor');
        while ($cursor !== null) {
            $next = $this->get('/alliance/content/manage?'.http_build_query(['content_cursor' => $cursor]))->assertOk();
            $seen = [...$seen, ...array_column($next->json('props.content'), 'id')];
            $cursor = $next->json('props.catalogue.nextCursor');
        }
        self::assertCount(47, array_unique($seen));
        self::assertEqualsCanonicalizing($items, $seen);
    }

    public function test_one_item_history_has_complete_scoped_run_and_revision_continuation(): void
    {
        [$user, $player, $alliance] = $this->owner();
        $item = $this->item($alliance->allianceId, $player->playerId, 1);
        for ($i = 1; $i <= 32; $i++) {
            ContentRevision::query()->create(['alliance_id' => $alliance->allianceId, 'content_item_id' => $item->id,
                'revision_number' => $i, 'type' => ContentType::Announcement, 'visibility' => ContentVisibility::Members,
                'title' => 'Revision '.$i, 'body' => 'History', 'locale' => 'en', 'sort_order' => 0, 'created_by_player_id' => $player->playerId, 'created_at' => now()]);
            AnnouncementBroadcastRun::query()->create(['alliance_id' => $alliance->allianceId, 'content_item_id' => $item->id,
                'content_revision_number' => $i, 'scheduled_for' => now(), 'last_visited_at' => now(),
                'status' => 'queued', 'idempotency_key' => 'run-'.$i]);
        }
        $this->asOwner($user, $player->playerId);
        $page = $this->get('/alliance/content/manage')->assertOk();
        $page->assertJsonPath('props.content.0.revisionCount', 32);
        $page->assertJsonPath('props.content.0.broadcastRunCount', 32);
        self::assertArrayNotHasKey('revisions', $page->json('props.content.0'));
        foreach (['revisions', 'runs'] as $kind) {
            $ids = [];
            $cursor = null;
            do {
                $response = $this->getJson('/alliance/content/manage/'.$item->id.'/'.$kind.'?'.http_build_query(['cursor' => $cursor]))->assertOk();
                self::assertLessThanOrEqual(10, count($response->json('page.items')));
                $response->assertJsonPath('total', 32);
                $ids = [...$ids, ...array_column($response->json('page.items'), 'id')];
                $cursor = $response->json('page.nextCursor');
            } while ($cursor !== null);
            self::assertCount(32, $ids);
            self::assertCount(32, array_unique($ids));
        }
    }

    public function test_categories_and_media_are_bounded_without_losing_off_page_selected_values(): void
    {
        [$user, $player, $alliance] = $this->owner();
        $oldCategory = null;
        $oldMedia = null;
        for ($i = 0; $i < 31; $i++) {
            $category = ContentCategory::query()->create(['alliance_id' => $alliance->allianceId, 'name' => 'Category '.$i, 'slug' => 'category-'.$i, 'sort_order' => $i]);
            $media = MediaAsset::query()->create(['alliance_id' => $alliance->allianceId, 'original_name' => 'Image '.$i,
                'disk' => 'local', 'path' => 'fixture/'.$i, 'mime_type' => 'image/png', 'size_bytes' => 1,
                'uploaded_by_player_id' => $player->playerId, 'sha256' => hash('sha256', (string) $i), 'scan_status' => 'clean', 'lifecycle_status' => 'active']);
            $oldCategory ??= (string) $category->id;
            $oldMedia ??= (string) $media->id;
        }
        $this->asOwner($user, $player->playerId);
        $this->get('/alliance/content/manage')->assertOk()->assertJsonCount(25, 'props.categories')->assertJsonCount(25, 'props.media');
        foreach (['categories' => $oldCategory, 'media' => $oldMedia] as $kind => $selected) {
            $response = $this->getJson('/alliance/content/manage/options/'.$kind.'?'.http_build_query(['q' => '30', 'selected' => $selected]))->assertOk();
            $response->assertJsonPath('selected.id', $selected);
            $response->assertJsonCount(1, 'page.items');
        }
    }

    public function test_continuation_and_history_reauthorize_current_membership_and_cannot_cross_alliance(): void
    {
        [$user, $player, $alliance] = $this->owner();
        for ($i = 0; $i < 23; $i++) {
            $this->item($alliance->allianceId, $player->playerId, $i);
        }
        [$otherUser, $otherPlayer, $otherAlliance] = $this->owner();
        $foreign = $this->item($otherAlliance->allianceId, $otherPlayer->playerId, 100);
        $this->asOwner($user, $player->playerId);
        $cursor = $this->get('/alliance/content/manage')->assertOk()->json('props.catalogue.nextCursor');
        $this->getJson('/alliance/content/manage/'.$foreign->id.'/runs')->assertNotFound();
        $this->get('/alliance/content/manage?'.http_build_query(['content_cursor' => $cursor, 'q' => 'different']))->assertSessionHasErrors('cursor');
        $this->asOwner($otherUser, $otherPlayer->playerId);
        $this->get('/alliance/content/manage?'.http_build_query(['content_cursor' => $cursor]))->assertSessionHasErrors('cursor');
        $this->asOwner($user, $player->playerId);
        AllianceMembership::query()->where('alliance_id', $alliance->allianceId)->where('player_id', $player->playerId)->update(['rank' => 'r1']);
        $this->get('/alliance/content/manage?'.http_build_query(['content_cursor' => $cursor]))->assertForbidden();
        $this->getJson('/alliance/content/manage/options/categories')->assertForbidden();
    }

    public function test_cursor_frontier_excludes_new_insertions_and_does_not_repeat_rows_after_deletion(): void
    {
        [$user, $player, $alliance] = $this->owner();
        for ($i = 0; $i < 25; $i++) {
            $this->item($alliance->allianceId, $player->playerId, $i);
        }
        $this->asOwner($user, $player->playerId);
        $first = $this->get('/alliance/content/manage')->assertOk();
        $cursor = $first->json('props.catalogue.nextCursor');
        $seen = array_column($first->json('props.content'), 'id');
        $new = $this->item($alliance->allianceId, $player->playerId, 200);
        ContentItem::query()->whereKey($seen[0])->delete();
        $next = $this->get('/alliance/content/manage?'.http_build_query(['content_cursor' => $cursor]))->assertOk();
        $ids = array_column($next->json('props.content'), 'id');
        self::assertCount(5, $ids);
        self::assertSame([], array_intersect($seen, $ids));
        self::assertNotContains((string) $new->id, $ids);
        $this->get('/alliance/content/manage')->assertOk()->assertJsonPath('props.content.0.id', (string) $new->id);
    }

    public function test_invalid_tokens_fail_and_subject_or_option_cursors_cannot_be_reused_elsewhere(): void
    {
        [$user, $player, $alliance] = $this->owner();
        $a = $this->item($alliance->allianceId, $player->playerId, 1);
        $b = $this->item($alliance->allianceId, $player->playerId, 2);
        for ($i = 0; $i < 12; $i++) {
            ContentRevision::query()->create(['alliance_id' => $alliance->allianceId, 'content_item_id' => $a->id,
                'revision_number' => $i + 1, 'type' => ContentType::Announcement, 'visibility' => ContentVisibility::Members,
                'title' => 'Private revision', 'body' => 'Body', 'locale' => 'en', 'sort_order' => 0, 'created_by_player_id' => $player->playerId]);
        }
        $this->asOwner($user, $player->playerId);
        $cursor = $this->getJson('/alliance/content/manage/'.$a->id.'/revisions')->assertOk()->json('page.nextCursor');
        foreach ([$b->id.'/revisions', $a->id.'/runs'] as $suffix) {
            $this->getJson('/alliance/content/manage/'.$suffix.'?'.http_build_query(['cursor' => $cursor]))->assertUnprocessable()->assertJsonValidationErrors('cursor');
        }
        $this->getJson('/alliance/content/manage/'.$a->id.'/revisions?cursor=broken')->assertUnprocessable()->assertJsonValidationErrors('cursor');
        $this->get('/alliance/content/manage?content_cursor=broken')->assertSessionHasErrors('cursor');
    }

    public function test_current_active_kingdom_and_manager_permission_are_required_by_internal_query_boundaries(): void
    {
        [$user, $player, $alliance] = $this->owner();
        $query = app(ContentManagementQuery::class);
        self::assertSame(0, $query->catalogue($alliance->allianceId, $player->playerId)['total']);
        AllianceMembership::query()->where('alliance_id', $alliance->allianceId)->where('player_id', $player->playerId)->update(['status' => 'suspended']);
        try {
            $query->categories($alliance->allianceId, $player->playerId);
            self::fail('An internal projection call must not bypass revoked manager authority.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        AllianceMembership::query()->where('alliance_id', $alliance->allianceId)->where('player_id', $player->playerId)->update(['status' => 'active']);
        Kingdom::query()->whereKey($player->kingdomId)->update(['status' => 'archived']);
        $this->asOwner($user, $player->playerId);
        $this->getJson('/alliance/content/manage/options/categories')->assertNotFound();
    }

    public function test_literal_search_filters_do_not_expand_wildcards_or_return_other_tenants_options(): void
    {
        [$user, $player, $alliance] = $this->owner();
        [$foreignUser, $foreignPlayer, $foreignAlliance] = $this->owner();
        $mine = ContentCategory::query()->create(['alliance_id' => $alliance->allianceId, 'name' => '100% literal_name', 'slug' => 'literal', 'sort_order' => 0]);
        ContentCategory::query()->create(['alliance_id' => $alliance->allianceId, 'name' => '1000 literalXname', 'slug' => 'wildcard', 'sort_order' => 0]);
        $foreign = ContentCategory::query()->create(['alliance_id' => $foreignAlliance->allianceId, 'name' => '100% literal_name', 'slug' => 'foreign', 'sort_order' => 0]);
        $this->asOwner($user, $player->playerId);
        $this->getJson('/alliance/content/manage/options/categories?'.http_build_query(['q' => '% literal_', 'selected' => $foreign->id]))
            ->assertOk()->assertJsonCount(1, 'page.items')->assertJsonPath('page.items.0.id', (string) $mine->id)->assertJsonPath('selected', null);
    }

    public function test_catalogue_page_query_count_does_not_grow_with_the_number_of_displayed_rows(): void
    {
        [$user, $player, $alliance] = $this->owner();
        $category = ContentCategory::query()->create(['alliance_id' => $alliance->allianceId, 'name' => 'Shared category', 'slug' => 'shared', 'sort_order' => 0]);
        $this->item($alliance->allianceId, $player->playerId, 1)->forceFill(['category_id' => $category->id])->save();
        $this->asOwner($user, $player->playerId);
        $this->get('/alliance/content/manage')->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/alliance/content/manage')->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 2; $i < 100; $i++) {
            $this->item($alliance->allianceId, $player->playerId, $i)->forceFill(['category_id' => $category->id])->save();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/alliance/content/manage')->assertOk()->assertJsonCount(20, 'props.content');
        $twenty = count(DB::getQueryLog());
        DB::disableQueryLog();
        self::assertLessThanOrEqual($one, $twenty, 'Eager categories and scoped grouped counts must avoid per-row queries.');
    }

    public function test_forged_retry_selection_cannot_bypass_the_same_alliance_and_content_scope_used_for_reading(): void
    {
        [$user, $player, $alliance] = $this->owner();
        $item = $this->item($alliance->allianceId, $player->playerId, 0);
        $run = AnnouncementBroadcastRun::query()->create(['alliance_id' => $alliance->allianceId, 'content_item_id' => $item->id,
            'content_revision_number' => 1, 'status' => BroadcastRunStatus::Queued, 'scheduled_for' => now(),
            'last_visited_at' => now(), 'idempotency_key' => 'scope-retry']);
        foreach (['alliance_id', 'content_item_id'] as $wrongKey) {
            $metadata = ['alliance_id' => $alliance->allianceId, 'content_item_id' => (string) $item->id, 'broadcast_run_id' => (string) $run->id];
            $metadata[$wrongKey] = (string) Str::ulid();
            $message = NotificationMessage::query()->create(['notification_type' => 'alliance.announcement', 'recipient_user_id' => $user->id,
                'player_id' => $player->playerId, 'subject_type' => 'content_item', 'subject_id' => $item->id,
                'title' => 'Invalid owner metadata', 'metadata' => $metadata, 'available_at' => now(), 'idempotency_key' => hash('sha256', $wrongKey)]);
            $route = NotificationDelivery::query()->create(['notification_message_id' => $message->id, 'channel' => 'discord',
                'status' => 'failed', 'due_at' => now(), 'idempotency_key' => hash('sha256', 'route-'.$wrongKey)]);
            try {
                app(RetryAnnouncementBroadcastFailures::class)->handle($alliance->allianceId, $player->playerId, (string) $run->id, [(string) $route->id]);
                self::fail('Hidden foreign metadata must not be recoverable by submitting the concrete ID.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('delivery_ids', $exception->errors());
            }
            self::assertSame('failed', $route->fresh()->status->value);
        }
    }

    public function test_hydration_and_related_schedule_work_are_bounded_before_materialization(): void
    {
        [, $player, $alliance] = $this->owner();
        for ($i = 0; $i < 67; $i++) {
            $item = $this->item($alliance->allianceId, $player->playerId, $i);
            AnnouncementBroadcastSchedule::query()->create([
                'alliance_id' => $alliance->allianceId, 'content_item_id' => $item->id,
                'created_by_player_id' => $player->playerId, 'timezone' => 'UTC', 'weekdays' => [1],
                'local_time' => '12:00', 'status' => 'active', 'next_run_at' => now()->addDay(),
            ]);
        }
        $items = 0;
        $schedules = 0;
        ContentItem::retrieved(static function () use (&$items): void {
            $items++;
        });
        AnnouncementBroadcastSchedule::retrieved(static function () use (&$schedules): void {
            $schedules++;
        });
        $query = app(ContentManagementQuery::class);
        $page = $query->catalogue($alliance->allianceId, $player->playerId);
        $related = $query->related($alliance->allianceId, $player->playerId,
            array_map(static fn (ContentItem $item): string => (string) $item->id, $page['page']->items));

        self::assertSame(67, $page['total']);
        self::assertSame(21, $items, 'Only one page and its look-ahead row may be hydrated.');
        self::assertSame(20, $schedules, 'Off-page schedules must not be materialized.');
        self::assertCount(20, $related['schedules']);
    }

    public function test_a_deleted_cursor_row_does_not_hide_the_rest_of_the_catalogue(): void
    {
        [, $player, $alliance] = $this->owner();
        for ($i = 0; $i < 25; $i++) {
            $this->item($alliance->allianceId, $player->playerId, $i);
        }
        $query = app(ContentManagementQuery::class);
        $first = $query->catalogue($alliance->allianceId, $player->playerId);
        $last = $first['page']->items[array_key_last($first['page']->items)];
        $last->delete();
        $next = $query->catalogue($alliance->allianceId, $player->playerId, $first['page']->nextCursor);

        self::assertCount(5, $next['page']->items);
        self::assertNull($next['page']->nextCursor);
        self::assertSame(24, $next['total']);
        self::assertSame([], array_intersect(
            array_column($first['page']->toArray()['items'], 'id'),
            array_column($next['page']->toArray()['items'], 'id'),
        ));
    }

    public function test_category_and_media_continuations_are_independent_and_complete(): void
    {
        [$user, $player, $alliance] = $this->owner();
        for ($i = 0; $i < 31; $i++) {
            ContentCategory::query()->create(['alliance_id' => $alliance->allianceId, 'name' => 'Category '.$i,
                'slug' => 'category-'.$i, 'sort_order' => $i]);
            MediaAsset::query()->create(['alliance_id' => $alliance->allianceId, 'original_name' => 'Media '.$i,
                'disk' => 'local', 'path' => 'options/'.$i, 'mime_type' => 'image/png', 'size_bytes' => 1,
                'uploaded_by_player_id' => $player->playerId, 'sha256' => hash('sha256', (string) $i),
                'scan_status' => 'clean', 'lifecycle_status' => 'active']);
        }
        $this->asOwner($user, $player->playerId);
        foreach (['categories', 'media'] as $kind) {
            $path = '/alliance/content/manage/options/'.$kind;
            $first = $this->getJson($path)->assertOk()->assertJsonCount(25, 'page.items')->assertJsonPath('total', 31);
            $cursor = $first->json('page.nextCursor');
            self::assertIsString($cursor);
            $next = $this->getJson($path.'?'.http_build_query(['cursor' => $cursor]))->assertOk()->assertJsonCount(6, 'page.items');
            $ids = [...array_column($first->json('page.items'), 'id'), ...array_column($next->json('page.items'), 'id')];
            self::assertCount(31, array_unique($ids));
            $next->assertJsonPath('page.nextCursor', null);
            $other = $kind === 'media' ? 'categories' : 'media';
            $this->getJson('/alliance/content/manage/options/'.$other.'?'.http_build_query(['cursor' => $cursor]))
                ->assertUnprocessable()->assertJsonValidationErrors('cursor');
            $this->getJson($path.'?'.http_build_query(['cursor' => $cursor, 'q' => 'changed']))
                ->assertUnprocessable()->assertJsonValidationErrors('cursor');
        }
    }

    public function test_old_item_history_is_not_hidden_by_another_items_hundred_newer_runs(): void
    {
        [$user, $player, $alliance] = $this->owner();
        $old = $this->item($alliance->allianceId, $player->playerId, 1);
        $new = $this->item($alliance->allianceId, $player->playerId, 2);
        $oldRun = AnnouncementBroadcastRun::query()->create([
            'alliance_id' => $alliance->allianceId, 'content_item_id' => $old->id, 'content_revision_number' => 1,
            'status' => 'queued', 'scheduled_for' => now()->subYear(), 'last_visited_at' => now(),
            'idempotency_key' => 'older-history',
        ]);
        for ($i = 0; $i < 125; $i++) {
            AnnouncementBroadcastRun::query()->create([
                'alliance_id' => $alliance->allianceId, 'content_item_id' => $new->id, 'content_revision_number' => 1,
                'status' => 'queued', 'scheduled_for' => now(), 'last_visited_at' => now(), 'idempotency_key' => 'newer-'.$i,
            ]);
        }
        $this->asOwner($user, $player->playerId);
        $this->getJson('/alliance/content/manage/'.$old->id.'/runs')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonCount(1, 'page.items')->assertJsonPath('page.items.0.id', (string) $oldRun->id);
        $this->getJson('/alliance/content/manage/'.$new->id.'/runs')->assertOk()
            ->assertJsonPath('total', 125)->assertJsonCount(5, 'page.items');
    }

    public function test_fresh_schema_indexes_match_owner_local_keyset_queries(): void
    {
        foreach (['content_media_keyset', 'content_categories_keyset', 'content_catalogue_keyset',
            'content_revision_keyset', 'content_broadcast_history_keyset'] as $name) {
            $index = DB::selectOne('SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?', [$name]);
            self::assertNotNull($index, $name);
            self::assertStringContainsString('alliance_id', $index->indexdef);
            self::assertStringContainsString('id)', $index->indexdef);
        }
    }

    private function owner(): array
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $player = $factory->player((int) $user->id);

        return [$user, $player, $factory->alliance($player)];
    }

    private function asOwner($user, string $player): void
    {
        $this->flushSession();
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $player])
            ->withHeader('X-Inertia', 'true')->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(request()) ?? '');
    }

    private function item(string $alliance, string $player, int $index): ContentItem
    {
        return ContentItem::query()->create(['alliance_id' => $alliance, 'type' => ContentType::Announcement,
            'visibility' => ContentVisibility::Members, 'status' => ContentStatus::Published,
            'title' => 'Announcement '.$index, 'slug' => 'announcement-'.$index, 'body' => 'Retained full text',
            'locale' => 'en', 'sort_order' => 0, 'current_revision_number' => 1,
            'created_by_player_id' => $player, 'updated_by_player_id' => $player, 'published_at' => now()]);
    }
}
