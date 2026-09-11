<?php

declare(strict_types=1);

namespace Tests\Contexts\Alliance\Content\Integration\Concurrency;

use App\Contexts\Alliance\Content\Actions\CancelAnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Actions\MaterializeAnnouncementBroadcastRuns;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\QueueAnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Actions\QueuePublishedAnnouncementBroadcasts;
use App\Contexts\Alliance\Content\Actions\SaveAnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Queries\AnnouncementNotificationEligibilityQuery;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\ReadModels\AnnouncementBroadcastManagement\Queries\AnnouncementBroadcastManagementQuery;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AnnouncementBroadcastTraversalTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-11T09:00:00Z');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recipient_budget_is_global_and_resumes_after_application_restart(): void
    {
        $item = $this->fixture(7);
        self::assertSame(0, app(QueuePublishedAnnouncementBroadcasts::class)->handle(25, 2));
        self::assertSame(2, NotificationMessage::query()->count());
        $run = AnnouncementBroadcastRun::query()->sole();
        self::assertNull($run->queued_at);
        self::assertSame('pending', $run->status->value);
        $application = $this->app;
        $target = DB::connection()->getConfig();
        $this->refreshApplication();
        config()->set('database.connections.pgsql', $target);
        DB::purge('pgsql');
        self::assertNotSame($application, $this->app);
        self::assertSame($target['database'], DB::connection()->getDatabaseName());
        for ($i = 0; $i < 5; $i++) {
            app(QueuePublishedAnnouncementBroadcasts::class)->handle(25, 2);
        }
        self::assertSame(7, NotificationMessage::query()->count());
        self::assertSame(1, AnnouncementBroadcastRun::query()->count());
        self::assertSame(BroadcastRunStatus::Queued, $run->fresh()->status);
        self::assertSame(7, $run->fresh()->recipient_count);
        self::assertNotNull($item->fresh()->broadcasted_at);
        self::assertSame(0, app(QueuePublishedAnnouncementBroadcasts::class)->handle(25, 2));
    }

    public function test_pending_runs_rotate_under_a_one_recipient_budget(): void
    {
        $first = $this->fixture(3);
        $second = $this->fixture(3);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(25, 1);
        $queue->handle(25, 1);
        self::assertSame(2, NotificationMessage::query()->count());
        self::assertSame(2, NotificationMessage::query()->distinct()->count('subject_id'));
        for ($i = 0; $i < 8; $i++) {
            $queue->handle(25, 1);
        }
        self::assertSame(3, NotificationMessage::query()->where('subject_id', $first->id)->count());
        self::assertSame(3, NotificationMessage::query()->where('subject_id', $second->id)->count());
    }

    public function test_member_query_is_limited_before_hydration(): void
    {
        $this->fixture(3);
        $selects = [];
        DB::listen(static function (QueryExecuted $query) use (&$selects): void {
            if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, '"alliance_memberships"')
                && str_contains($query->sql, 'order by')) {
                $selects[] = $query->sql;
            }
        });
        app(QueuePublishedAnnouncementBroadcasts::class)->handle(1, 1);
        self::assertNotEmpty($selects);
        foreach ($selects as $sql) {
            self::assertMatchesRegularExpression('/limit [1-9][0-9]?\b/i', $sql, $sql);
        }
    }

    public function test_revision_cancels_unfinished_audience_and_new_revision_has_new_occurrence_identity(): void
    {
        $item = $this->fixture(3);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        $first = AnnouncementBroadcastRun::query()->sole();
        $item->forceFill(['current_revision_number' => 2, 'status' => ContentStatus::Draft,
            'published_at' => null, 'broadcasted_at' => null])->save();
        $queue->handle(1, 1);
        self::assertSame(BroadcastRunStatus::Cancelled, $first->fresh()->status);
        self::assertSame(1, NotificationMessage::query()->count());
        app(PublishContentItem::class)->handle($item->alliance_id, $item->created_by_player_id, (string) $item->id);
        for ($i = 0; $i < 4; $i++) {
            $queue->handle(25, 2);
        }
        self::assertSame(2, AnnouncementBroadcastRun::query()->count());
        self::assertSame(4, NotificationMessage::query()->count());
    }

    public function test_cancelling_recurrence_stops_pending_recipients_without_erasing_prior_messages(): void
    {
        $item = $this->fixture(3);
        $item->forceFill(['broadcasted_at' => now()])->save();
        $id = app(SaveAnnouncementBroadcastSchedule::class)->handle($item->alliance_id, $item->created_by_player_id,
            (string) $item->id, [5], '10:00', 'UTC');
        AnnouncementBroadcastSchedule::query()->whereKey($id)->update(['next_run_at' => now()->subMinute()]);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        app(CancelAnnouncementBroadcastSchedule::class)->handle($item->alliance_id, $item->created_by_player_id, $id);
        $queue->handle(1, 1);
        self::assertSame(1, NotificationMessage::query()->count());
        self::assertSame(BroadcastRunStatus::Cancelled, AnnouncementBroadcastRun::query()->sole()->status);
    }

    public function test_all_suppressed_routes_consume_budget_and_are_not_reported_as_replays(): void
    {
        $item = $this->fixture(3);
        foreach (AllianceMembership::query()->where('alliance_id', $item->alliance_id)->get() as $membership) {
            $player = app(PlayerReferenceQuery::class)->require($membership->player_id);
            app(SetNotificationPreference::class)->handle($player->userId, $player->playerId, 'alliance.announcement', DeliveryChannel::InApp, false);
        }
        app(QueuePublishedAnnouncementBroadcasts::class)->handle(1, 1);
        $run = AnnouncementBroadcastRun::query()->sole();
        self::assertSame(1, $run->recipient_count);
        self::assertSame(1, $run->suppressed_count);
        self::assertSame(0, $run->replayed_count);
        self::assertSame(0, $run->delivery_count);
        self::assertSame(1, NotificationMessage::query()->count());
    }

    public function test_notification_failure_rolls_back_its_cursor_and_can_retry(): void
    {
        $this->fixture(3);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        $run = AnnouncementBroadcastRun::query()->sole();
        $cursor = $run->recipient_cursor;
        $fail = true;
        NotificationMessage::created(static function () use (&$fail): void {
            if ($fail) {
                throw new RuntimeException('injected persistence failure');
            }
        });
        try {
            $queue->handle(1, 1);
            self::fail('The injected failure must escape the worker.');
        } catch (RuntimeException $e) {
            self::assertSame('injected persistence failure', $e->getMessage());
        }
        self::assertSame($cursor, $run->fresh()->recipient_cursor);
        self::assertSame(1, NotificationMessage::query()->count());
        $fail = false;
        $queue->handle(1, 2);
        self::assertSame(3, NotificationMessage::query()->count());
        self::assertSame(3, $run->fresh()->recipient_count);
    }

    public function test_members_joining_after_materialization_are_not_appended_to_the_captured_audience(): void
    {
        $item = $this->fixture(3);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        $late = $this->member($item);
        for ($i = 0; $i < 4; $i++) {
            $queue->handle(1, 2);
        }
        self::assertSame(3, NotificationMessage::query()->count());
        self::assertFalse(NotificationMessage::query()->where('player_id', $late)->exists());
    }

    public function test_audience_larger_than_two_pages_has_one_occurrence_and_one_final_receipt(): void
    {
        $this->fixture(53);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        self::assertSame(0, $queue->handle(1, 1000));
        self::assertSame(25, NotificationMessage::query()->count());
        self::assertSame(0, $queue->handle(1, 1000));
        self::assertSame(50, NotificationMessage::query()->count());
        self::assertSame(1, $queue->handle(1, 1000));
        self::assertSame(53, NotificationMessage::query()->count());
        self::assertSame(53, NotificationMessage::query()->distinct()->count('player_id'));
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'broadcast.run.queued')->count());
        self::assertSame(0, $queue->handle(1, 1000));
    }

    public function test_revocation_after_candidate_selection_consumes_the_slot_without_queueing(): void
    {
        $item = $this->fixture(3);
        app(MaterializeAnnouncementBroadcastRuns::class)->handle(1);
        $run = AnnouncementBroadcastRun::query()->sole();
        $first = AllianceMembership::query()->where('alliance_id', $item->alliance_id)->orderBy('id')->firstOrFail();
        $changed = false;
        DB::listen(static function (QueryExecuted $event) use ($first, &$changed): void {
            if (! $changed && str_starts_with($event->sql, 'select "id" from "alliance_memberships"') && str_contains($event->sql, 'order by')) {
                $changed = true;
                AllianceMembership::query()->whereKey($first->id)->update(['status' => MembershipStatus::Suspended->value]);
            }
        });
        $page = app(QueueAnnouncementBroadcastRun::class)->handle((string) $run->id, 1);
        self::assertTrue($changed);
        self::assertSame(1, $page->examined);
        self::assertSame(0, NotificationMessage::query()->count());
        self::assertSame(1, $run->fresh()->skipped_count);
        self::assertSame((string) $first->id, $run->fresh()->recipient_cursor);
        app(QueuePublishedAnnouncementBroadcasts::class)->handle(1, 2);
        self::assertSame(2, NotificationMessage::query()->count());
    }

    public function test_deleting_the_cursor_boundary_does_not_restart_prior_recipients(): void
    {
        $this->fixture(3);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        $run = AnnouncementBroadcastRun::query()->sole();
        AllianceMembership::query()->whereKey($run->recipient_cursor)->delete();
        self::assertSame(1, $queue->handle(1, 2));
        self::assertSame(3, NotificationMessage::query()->count());
        self::assertSame(3, NotificationMessage::query()->distinct()->count('player_id'));
    }

    public function test_a_competing_connection_cannot_advance_the_same_selected_page_twice(): void
    {
        $this->fixture(3);
        app(MaterializeAnnouncementBroadcastRuns::class)->handle(1);
        $run = AnnouncementBroadcastRun::query()->sole();
        $name = DB::getDefaultConnection();
        config()->set('database.connections.competing_broadcast', [...DB::connection()->getConfig(), 'name' => 'competing_broadcast']);
        $competed = false;
        DB::listen(function (QueryExecuted $event) use ($run, $name, &$competed): void {
            if (! $competed && str_starts_with($event->sql, 'select "id" from "alliance_memberships"') && str_contains($event->sql, 'order by')) {
                $competed = true;
                self::assertSame(0, DB::transactionLevel(), 'Candidate pages must not hold a fan-out transaction.');
                DB::setDefaultConnection('competing_broadcast');
                try {
                    self::assertSame('competing_broadcast', DB::connection()->getName());
                    self::assertNotSame(DB::connection($name)->getPdo(), DB::connection()->getPdo());
                    app(QueueAnnouncementBroadcastRun::class)->handle((string) $run->id, 1);
                } finally {
                    DB::setDefaultConnection($name);
                }
            }
        });
        try {
            app(QueueAnnouncementBroadcastRun::class)->handle((string) $run->id, 2);
            self::assertTrue($competed);
            self::assertSame(1, NotificationMessage::query()->count());
            self::assertSame(1, $run->fresh()->recipient_count);
            app(QueuePublishedAnnouncementBroadcasts::class)->handle(1, 2);
            self::assertSame(3, NotificationMessage::query()->count());
            self::assertSame(3, $run->fresh()->recipient_count);
        } finally {
            DB::purge('competing_broadcast');
        }
    }

    public function test_completion_outbox_failure_rolls_back_the_last_recipient_and_terminal_state(): void
    {
        $this->fixture(2);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        $run = AnnouncementBroadcastRun::query()->sole();
        $cursor = $run->recipient_cursor;
        $fail = true;
        OutboxMessage::creating(static function (OutboxMessage $message) use (&$fail): void {
            if ($fail && $message->event_type === 'broadcast.run.queued') {
                throw new RuntimeException('injected final receipt failure');
            }
        });
        try {
            $queue->handle(1, 1);
            self::fail('The failed receipt must not leave completed progress.');
        } catch (RuntimeException $exception) {
            self::assertSame('injected final receipt failure', $exception->getMessage());
        }
        self::assertSame(1, NotificationMessage::query()->count());
        self::assertSame($cursor, $run->fresh()->recipient_cursor);
        self::assertSame(BroadcastRunStatus::Pending, $run->fresh()->status);
        $fail = false;
        self::assertSame(1, $queue->handle(1, 1));
        self::assertSame(2, NotificationMessage::query()->count());
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'broadcast.run.queued')->count());
    }

    public function test_archived_kingdom_cancels_pending_work_and_does_not_starve_another_source(): void
    {
        $first = $this->fixture(3);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        $run = AnnouncementBroadcastRun::query()->sole();
        $kingdomId = Alliance::query()->findOrFail($first->alliance_id)->kingdom_id;
        $message = NotificationMessage::query()->sole();
        $player = app(PlayerReferenceQuery::class)->require($message->player_id);
        Kingdom::query()->whereKey($kingdomId)->update(['status' => 'archived']);
        self::assertFalse(app(AnnouncementNotificationEligibilityQuery::class)->allows($message->source(), $player));
        $queue->handle(1, 1);
        self::assertSame(BroadcastRunStatus::Cancelled, $run->fresh()->status);
        self::assertSame(1, NotificationMessage::query()->count());
        self::assertSame(0, $queue->handle(1, 1));
        $second = $this->fixture(1);
        self::assertSame(1, $queue->handle(1, 1));
        self::assertSame(1, NotificationMessage::query()->where('subject_id', $second->id)->count());
    }

    public function test_recurrence_generation_change_invalidates_old_occurrence_and_external_source(): void
    {
        $item = $this->fixture(3);
        $item->forceFill(['broadcasted_at' => now()])->save();
        $save = app(SaveAnnouncementBroadcastSchedule::class);
        $id = $save->handle($item->alliance_id, $item->created_by_player_id, (string) $item->id, [5], '10:00', 'UTC');
        AnnouncementBroadcastSchedule::query()->whereKey($id)->update(['next_run_at' => now()->subMinute()]);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        $run = AnnouncementBroadcastRun::query()->sole();
        $message = NotificationMessage::query()->sole();
        $player = app(PlayerReferenceQuery::class)->require($message->player_id);
        $eligibility = app(AnnouncementNotificationEligibilityQuery::class);
        self::assertTrue($eligibility->allows($message->source(), $player));
        $save->handle($item->alliance_id, $item->created_by_player_id, (string) $item->id, [5], '11:00', 'UTC');
        $save->handle($item->alliance_id, $item->created_by_player_id, (string) $item->id, [5], '10:00', 'UTC');
        self::assertSame(3, AnnouncementBroadcastSchedule::query()->findOrFail($id)->generation);
        self::assertFalse($eligibility->allows($message->source(), $player));
        $queue->handle(1, 1);
        self::assertSame(BroadcastRunStatus::Cancelled, $run->fresh()->status);
        self::assertSame(1, NotificationMessage::query()->count());
    }

    public function test_empty_audience_completes_once_and_source_creation_is_bounded(): void
    {
        $first = $this->fixture(1);
        $this->fixture(1);
        AllianceMembership::query()->where('alliance_id', $first->alliance_id)->update(['status' => MembershipStatus::Suspended->value]);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        self::assertSame(1, $queue->handle(1, 1));
        self::assertSame(1, AnnouncementBroadcastRun::query()->count());
        self::assertSame(BroadcastRunStatus::Empty, AnnouncementBroadcastRun::query()->sole()->status);
        self::assertSame(1, $queue->handle(1, 1));
        self::assertSame(2, AnnouncementBroadcastRun::query()->count());
        self::assertSame(0, $queue->handle(1, 1));
    }

    public function test_command_enforces_requested_work_and_exposes_pending_projection(): void
    {
        $item = $this->fixture(3);
        self::assertSame(0, Artisan::call('content:queue-announcement-broadcasts', ['--limit' => 1, '--recipients' => 1]));
        self::assertStringContainsString('Completed queueing 0', Artisan::output());
        self::assertSame(1, NotificationMessage::query()->count());
        $projection = app(AnnouncementBroadcastManagementQuery::class)->forAlliance($item->alliance_id);
        $run = $projection['runs'][(string) $item->id][0];
        self::assertSame('pending', $run['status']);
        self::assertSame(1, $run['recipientCount']);
        self::assertSame(0, $run['suppressedCount']);
        self::assertSame(0, $run['skippedCount']);
        self::assertSame(0, $run['replayedCount']);
        self::assertNull($run['queuedAt']);
        self::assertSame(0, Artisan::call('content:queue-announcement-broadcasts', ['--limit' => 0, '--recipients' => 0]));
        self::assertSame(2, NotificationMessage::query()->count(), 'Zero options clamp to one unit rather than unlimited work.');
    }

    public static function invalidSources(): iterable
    {
        yield 'archived' => ['status', ContentStatus::Archived];
        yield 'notifications disabled' => ['notify_members', false];
        yield 'publication rescheduled' => ['status', ContentStatus::Scheduled];
    }

    #[DataProvider('invalidSources')]
    public function test_source_invalidations_stop_audience_and_pending_external_delivery(string $field, mixed $value): void
    {
        $item = $this->fixture(3);
        $member = AllianceMembership::query()->where('alliance_id', $item->alliance_id)->orderBy('id')->firstOrFail();
        $player = app(PlayerReferenceQuery::class)->require($member->player_id);
        app(SaveNotificationEndpoint::class)->handle($player->userId, $player->playerId, DeliveryChannel::Discord,
            'Source fixture', ['webhook_url' => 'https://discord.com/api/webhooks/91234/source-boundary']);
        Http::preventStrayRequests();
        Http::fake();
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        $run = AnnouncementBroadcastRun::query()->sole();
        $external = NotificationDelivery::query()->where('channel', DeliveryChannel::Discord)->sole();
        $item->forceFill([$field => $value])->save();
        app(ProcessNotificationDeliveries::class)->handle(1);
        self::assertSame(DeliveryStatus::Cancelled, $external->fresh()->status);
        Http::assertNothingSent();
        $queue->handle(1, 1);
        self::assertSame(1, NotificationMessage::query()->count());
        self::assertSame(BroadcastRunStatus::Cancelled, $run->fresh()->status);
    }

    public function test_already_exhausted_recurrence_still_finishes_its_last_valid_occurrence(): void
    {
        $item = $this->fixture(3);
        $item->forceFill(['broadcasted_at' => now()])->save();
        $id = app(SaveAnnouncementBroadcastSchedule::class)->handle($item->alliance_id, $item->created_by_player_id,
            (string) $item->id, [5], '09:30', 'UTC', '2026-09-11T10:00:00Z');
        AnnouncementBroadcastSchedule::query()->whereKey($id)->update(['next_run_at' => now()->subMinute()]);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        $queue->handle(1, 1);
        Carbon::setTestNow('2026-09-11T09:30:00Z');
        $queue->handle(1, 1);
        $schedule = AnnouncementBroadcastSchedule::query()->findOrFail($id);
        self::assertSame('completed', $schedule->status->value);
        Carbon::setTestNow('2026-09-11T10:01:00Z');
        for ($i = 0; $i < 6; $i++) {
            $queue->handle(25, 2);
        }
        self::assertSame(2, AnnouncementBroadcastRun::query()->count());
        self::assertSame(0, AnnouncementBroadcastRun::query()->where('status', '!=', 'queued')->count());
        self::assertSame(6, NotificationMessage::query()->count());
    }

    public function test_due_order_does_not_give_old_recurring_backlog_priority_forever(): void
    {
        $recurring = $this->fixture(1);
        $recurring->forceFill(['broadcasted_at' => now()->subMonth(), 'published_at' => now()->subMonth()])->save();
        $id = app(SaveAnnouncementBroadcastSchedule::class)->handle($recurring->alliance_id, $recurring->created_by_player_id,
            (string) $recurring->id, [5], '10:00', 'UTC');
        AnnouncementBroadcastSchedule::query()->whereKey($id)->update(['next_run_at' => now()->subWeeks(3)]);
        $once = $this->fixture(1);
        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        self::assertSame(1, $queue->handle(1, 1));
        self::assertSame((string) $recurring->id, NotificationMessage::query()->sole()->subject_id);
        self::assertSame(1, $queue->handle(1, 1));
        self::assertSame(1, NotificationMessage::query()->where('subject_id', $once->id)->count());
    }

    private function fixture(int $members): ContentItem
    {
        $factory = new ScenarioFactory;
        $owner = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($owner);
        $item = ContentItem::query()->create([
            'alliance_id' => $alliance->allianceId, 'type' => ContentType::Announcement,
            'visibility' => ContentVisibility::Members, 'status' => ContentStatus::Published,
            'title' => 'Bounded announcement', 'slug' => 'bounded-announcement', 'body' => 'Current instructions',
            'locale' => 'en', 'sort_order' => 0, 'current_revision_number' => 1,
            'notify_members' => true, 'published_at' => now(),
            'created_by_player_id' => $owner->playerId, 'updated_by_player_id' => $owner->playerId,
        ]);
        for ($i = 1; $i < $members; $i++) {
            $this->member($item);
        }

        return $item;
    }

    private function member(ContentItem $item): string
    {
        $factory = new ScenarioFactory;
        $owner = app(PlayerReferenceQuery::class)->require($item->created_by_player_id);
        $player = $factory->player($factory->account()->userId, $owner->kingdomNumber);
        AllianceMembership::query()->create(['alliance_id' => $item->alliance_id, 'player_id' => $player->playerId,
            'status' => MembershipStatus::Active, 'rank' => AllianceRank::R1, 'joined_at' => now()]);

        return $player->playerId;
    }
}
