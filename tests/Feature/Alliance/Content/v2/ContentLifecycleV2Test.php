<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Content\v2;

use App\Contexts\Alliance\Content\Actions\ArchiveContentItem;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\PublishScheduledContent;
use App\Contexts\Alliance\Content\Actions\RestoreContentRevision;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class ContentLifecycleV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_editing_published_content_creates_revision_and_returns_item_to_draft(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4570, 'Content Owner', 'Revision V2', 'revision-v2-4570');
        $save = app(SaveContentItem::class);
        $item = $save->handle(
            $scenario['alliance'],
            $scenario['player'],
            $this->attributes('Original', 'revision-v2-item', 'Original body'),
        );
        app(PublishContentItem::class)->handle($scenario['alliance'], $scenario['player'], $item->id);

        $updated = $save->handle(
            $scenario['alliance'],
            $scenario['player'],
            $this->attributes('<b>Revised</b>', 'revision-v2-item', '<script>alert(1)</script>Safe body'),
            $item->id,
        );

        self::assertSame(ContentStatus::Draft, $updated->status);
        self::assertSame('Revised', $updated->title);
        self::assertSame('alert(1)Safe body', $updated->body);
        self::assertSame(2, $updated->current_revision_number);
        self::assertNull($updated->published_at);
        self::assertCount(2, $updated->revisions()->get());
        $this->get('/alliances/revision-v2-4570/content/revision-v2-item')->assertNotFound();
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $scenario['alliance']->id,
            'event' => 'content.updated',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $scenario['alliance']->id,
            'event_type' => 'content.updated',
        ]);
    }

    public function test_historical_revision_restores_only_as_a_new_draft_revision(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4571, 'Restore Owner', 'Restore V2', 'restore-v2-4571');
        $save = app(SaveContentItem::class);
        $item = $save->handle(
            $scenario['alliance'],
            $scenario['player'],
            $this->attributes('Version One', 'restore-v2-item', 'First body'),
        );
        $firstRevision = ContentRevision::query()
            ->where('content_item_id', $item->id)
            ->where('revision_number', 1)
            ->sole();
        $save->handle(
            $scenario['alliance'],
            $scenario['player'],
            $this->attributes('Version Two', 'restore-v2-item', 'Second body'),
            $item->id,
        );

        $restored = app(RestoreContentRevision::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $item->id,
            $firstRevision->id,
        );

        self::assertSame('Version One', $restored->title);
        self::assertSame('First body', $restored->body);
        self::assertSame(ContentStatus::Draft, $restored->status);
        self::assertSame(3, $restored->current_revision_number);
        self::assertCount(3, $restored->revisions()->get());
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $scenario['alliance']->id,
            'event' => 'content.revision_restored',
        ]);
    }

    public function test_scheduled_content_publishes_only_when_due_and_archive_removes_visibility(): void
    {
        $this->travelTo(now()->startOfMinute());
        $scenario = (new ScenarioFactory)->alliance(4572, 'Schedule Owner', 'Schedule V2', 'schedule-v2-4572');
        $item = app(SaveContentItem::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $this->attributes('Scheduled', 'scheduled-v2-item', 'Scheduled body'),
        );
        $scheduled = app(PublishContentItem::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $item->id,
            now()->addMinutes(5),
        );

        self::assertSame(ContentStatus::Scheduled, $scheduled->status);
        self::assertSame(0, app(PublishScheduledContent::class)->handle());
        $this->get('/alliances/schedule-v2-4572/content/scheduled-v2-item')->assertNotFound();

        $this->travel(6)->minutes();
        self::assertSame(1, app(PublishScheduledContent::class)->handle());
        $this->get('/alliances/schedule-v2-4572/content/scheduled-v2-item')->assertOk();

        $archived = app(ArchiveContentItem::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $item->id,
        );
        self::assertSame(ContentStatus::Archived, $archived->status);
        $this->get('/alliances/schedule-v2-4572/content/scheduled-v2-item')->assertNotFound();
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
