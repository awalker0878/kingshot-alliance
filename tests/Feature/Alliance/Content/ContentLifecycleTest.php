<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Content;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Content\Actions\ArchiveContentItem;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\PublishScheduledContent;
use App\Contexts\Alliance\Content\Actions\RestoreContentRevision;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_published_content_creates_revision_and_returns_item_to_draft(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4901]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'content-revision-r5',
            'current_name' => 'Content Revision R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Revision Alliance', 'revision-alliance');
        $save = $this->app->make(SaveContentItem::class);
        $item = $save->handle($alliance, $ownerPlayer, $this->attributes('Original', 'revision-item', 'Original body'));
        $this->app->make(PublishContentItem::class)->handle($alliance, $ownerPlayer, $item->id);

        $updated = $save->handle(
            $alliance,
            $ownerPlayer,
            $this->attributes('<b>Revised</b>', 'revision-item', '<script>alert(1)</script>Safe body'),
            $item->id,
        );

        self::assertSame(ContentStatus::Draft, $updated->status);
        self::assertSame('Revised', $updated->title);
        self::assertSame('alert(1)Safe body', $updated->body);
        self::assertSame(2, $updated->current_revision_number);
        self::assertNull($updated->published_at);
        self::assertCount(2, $updated->revisions()->get());
        $this->get('/alliances/revision-alliance/content/revision-item')->assertNotFound();
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'event' => 'content.updated',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'content.updated',
        ]);
    }

    public function test_historical_revision_can_be_restored_only_as_a_new_draft_revision(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4902]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'content-restore-r5',
            'current_name' => 'Content Restore R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Restore Alliance', 'restore-alliance');
        $save = $this->app->make(SaveContentItem::class);
        $item = $save->handle($alliance, $ownerPlayer, $this->attributes('Version One', 'restore-item', 'First body'));
        $firstRevision = ContentRevision::query()
            ->where('content_item_id', $item->id)
            ->where('revision_number', 1)
            ->sole();
        $save->handle($alliance, $ownerPlayer, $this->attributes('Version Two', 'restore-item', 'Second body'), $item->id);

        $restored = $this->app->make(RestoreContentRevision::class)
            ->handle($alliance, $ownerPlayer, $item->id, $firstRevision->id);

        self::assertSame('Version One', $restored->title);
        self::assertSame('First body', $restored->body);
        self::assertSame(ContentStatus::Draft, $restored->status);
        self::assertSame(3, $restored->current_revision_number);
        self::assertCount(3, $restored->revisions()->get());
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'event' => 'content.revision_restored',
        ]);
    }

    public function test_scheduled_content_publishes_only_when_due_and_archival_removes_visibility(): void
    {
        $this->travelTo(now()->startOfMinute());
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4903]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'content-schedule-r5',
            'current_name' => 'Content Schedule R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Schedule Alliance', 'schedule-alliance');
        $item = $this->app->make(SaveContentItem::class)
            ->handle($alliance, $ownerPlayer, $this->attributes('Scheduled', 'scheduled-item', 'Scheduled body'));
        $scheduled = $this->app->make(PublishContentItem::class)
            ->handle($alliance, $ownerPlayer, $item->id, now()->addMinutes(5));

        self::assertSame(ContentStatus::Scheduled, $scheduled->status);
        self::assertSame(0, $this->app->make(PublishScheduledContent::class)->handle());
        $this->get('/alliances/schedule-alliance/content/scheduled-item')->assertNotFound();

        $this->travel(6)->minutes();
        self::assertSame(1, $this->app->make(PublishScheduledContent::class)->handle());
        $this->get('/alliances/schedule-alliance/content/scheduled-item')->assertOk();

        $archived = $this->app->make(ArchiveContentItem::class)
            ->handle($alliance, $ownerPlayer, $item->id);
        self::assertSame(ContentStatus::Archived, $archived->status);
        $this->get('/alliances/schedule-alliance/content/scheduled-item')->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function attributes(string $title, string $slug, string $body): array
    {
        return [
            'category_id' => null,
            'type' => ContentType::Guide,
            'visibility' => ContentVisibility::Public,
            'title' => $title,
            'slug' => $slug,
            'summary' => null,
            'body' => $body,
            'locale' => 'en',
            'sort_order' => 0,
        ];
    }
}
