<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domain\Alliances\Models\Alliance;

use App\Domain\Content\Actions\ArchiveContentItem;
use App\Domain\Content\Actions\PublishContentItem;
use App\Domain\Content\Actions\PublishScheduledContent;
use App\Domain\Content\Actions\RestoreContentRevision;
use App\Domain\Content\Actions\SaveContentItem;
use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\ContentType;
use App\Domain\Content\Enums\ContentVisibility;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_published_content_creates_revision_and_returns_item_to_draft(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Revision Alliance', 'revision-alliance');
        $save = $this->app->make(SaveContentItem::class);
        $item = $save->handle($alliance, $owner, $this->attributes('Original', 'revision-item', 'Original body'));
        $this->app->make(PublishContentItem::class)->handle($alliance, $owner, $item->id);

        $updated = $save->handle(
            $alliance,
            $owner,
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
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Restore Alliance', 'restore-alliance');
        $save = $this->app->make(SaveContentItem::class);
        $item = $save->handle($alliance, $owner, $this->attributes('Version One', 'restore-item', 'First body'));
        $firstRevision = ContentRevision::query()
            ->where('content_item_id', $item->id)
            ->where('revision_number', 1)
            ->sole();
        $save->handle($alliance, $owner, $this->attributes('Version Two', 'restore-item', 'Second body'), $item->id);

        $restored = $this->app->make(RestoreContentRevision::class)
            ->handle($alliance, $owner, $item->id, $firstRevision->id);

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
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Schedule Alliance', 'schedule-alliance');
        $item = $this->app->make(SaveContentItem::class)
            ->handle($alliance, $owner, $this->attributes('Scheduled', 'scheduled-item', 'Scheduled body'));
        $scheduled = $this->app->make(PublishContentItem::class)
            ->handle($alliance, $owner, $item->id, now()->addMinutes(5));

        self::assertSame(ContentStatus::Scheduled, $scheduled->status);
        self::assertSame(0, $this->app->make(PublishScheduledContent::class)->handle());
        $this->get('/alliances/schedule-alliance/content/scheduled-item')->assertNotFound();

        $this->travel(6)->minutes();
        self::assertSame(1, $this->app->make(PublishScheduledContent::class)->handle());
        $this->get('/alliances/schedule-alliance/content/scheduled-item')->assertOk();

        $archived = $this->app->make(ArchiveContentItem::class)
            ->handle($alliance, $owner, $item->id);
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
