<?php

declare(strict_types=1);

namespace Tests\ReadModels\AnnouncementBroadcastManagement\Feature;

use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Queries\AnnouncementDeliverySummaryQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\HandleInertiaRequests;
use App\ReadModels\AnnouncementBroadcastManagement\Queries\AnnouncementBroadcastManagementQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;
use UnexpectedValueException;

final class BroadcastDeliveryCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_counts_all_retained_messages_and_routes_above_former_sample_limits(): void
    {
        $fixture = $this->broadcast();
        $this->messages($fixture, 1205, array_column(DeliveryStatus::cases(), 'value'));

        $row = $this->management($fixture);

        self::assertSame(1205, $row['readCount']);
        self::assertSame(array_fill_keys(array_column(DeliveryStatus::cases(), 'value'), 1205), $row['deliveryCounts']);
        self::assertSame(6025, array_sum($row['deliveryCounts']));
        // Content preparation facts are independent of Communications outcomes.
        self::assertSame(17, $row['recipientCount']);
        self::assertSame(19, $row['deliveryCount']);
        self::assertSame(3, $row['skippedCount']);
        self::assertSame(4, $row['suppressedCount']);
        self::assertSame(5, $row['replayedCount']);
    }

    public function test_manager_correlates_alliance_run_and_actual_content_before_counting(): void
    {
        $fixture = $this->broadcast();
        $other = $this->broadcast();
        $this->messages($fixture, 1, ['sent']);
        $this->messages($fixture, 1, ['failed'], ['alliance_id' => $other['alliance']]);
        $this->messages($fixture, 1, ['failed'], ['content_item_id' => $other['content']]);
        $this->messages($fixture, 1, ['failed'], ['broadcast_run_id' => $other['run']]);
        $this->messages($fixture, 1, ['failed'], [], ['subject_id' => $other['content']]);
        $this->messages($fixture, 1, ['failed'], [], ['notification_type' => 'event.reminder']);
        $this->messages($fixture, 1, ['failed'], [], ['subject_type' => 'event_occurrence']);
        $this->messages($fixture, 1, ['failed'], [], ['metadata' => '{"alliance_id":"unknown"}']);
        $this->messages($other, 1, ['sent']);

        $row = $this->management($fixture);

        self::assertSame(1, $row['readCount']);
        self::assertSame(1, $row['deliveryCounts']['sent']);
        self::assertSame(0, $row['deliveryCounts']['failed']);
        self::assertSame([], $row['failedDeliveryIds']);
        self::assertSame(1, $this->management($other)['readCount']);
    }

    public function test_retry_selection_is_independently_bounded_and_reports_the_complete_candidate_count(): void
    {
        $fixture = $this->broadcast();
        $ids = $this->messages($fixture, 375, ['failed']);
        $this->messages($fixture, 12, ['failed'], [], [], ['attempt_count' => 5]);
        $this->messages($fixture, 8, ['sent']);
        rsort($ids, SORT_STRING); // All fixture created_at values tie; ID is the stable tiebreaker.

        $row = $this->management($fixture);

        self::assertArrayHasKey('retryCandidateCount', $row);
        self::assertSame(375, $row['retryCandidateCount']);
        self::assertSame(387, $row['deliveryCounts']['failed']);
        self::assertSame(array_slice($ids, 0, 50), $row['failedDeliveryIds']);
        self::assertSame($row, $this->management($fixture));
    }

    public function test_logical_reads_include_messages_without_routes_and_do_not_multiply_by_route_count(): void
    {
        $fixture = $this->broadcast();
        $this->messages($fixture, 2, ['sent', 'sent', 'failed']);
        $this->messages($fixture, 3, []);
        $this->messages($fixture, 4, ['queued'], [], ['read_at' => null]);

        $row = $this->management($fixture);

        self::assertSame(5, $row['readCount']);
        self::assertSame(10, array_sum($row['deliveryCounts']));
        self::assertSame(4, $row['deliveryCounts']['sent']);
        self::assertSame(2, $row['deliveryCounts']['failed']);
    }

    public function test_owner_query_has_fixed_query_count_for_multiple_runs_and_returns_empty_run_defaults(): void
    {
        $fixture = $this->broadcast();
        $map = [$fixture['run'] => $fixture['content']];
        $this->messages($fixture, 1, ['failed']);
        for ($i = 0; $i < 6; $i++) {
            $copy = $this->broadcast($fixture['alliance']);
            $map[$copy['run']] = $copy['content'];
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $result = app(AnnouncementDeliverySummaryQuery::class)->forRuns($fixture['alliance'], $map);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        self::assertCount(2, $queries);
        self::assertSame(array_keys($map), array_keys($result));
        self::assertSame(1, $result[$fixture['run']]->readCount);
        self::assertSame(1, $result[$fixture['run']]->retryCandidateCount);
        $empty = $result[$copy['run']];
        self::assertSame(0, $empty->readCount);
        self::assertSame(0, array_sum($empty->deliveryCounts));
        self::assertSame(0, $empty->retryCandidateCount);
        self::assertSame([], $empty->failedDeliveryIds);
    }

    public function test_each_run_has_its_own_retry_budget(): void
    {
        $a = $this->broadcast();
        $b = $this->broadcast($a['alliance']);
        $aIds = $this->messages($a, 63, ['failed']);
        $bIds = $this->messages($b, 72, ['failed']);
        rsort($aIds, SORT_STRING);
        rsort($bIds, SORT_STRING);

        $result = app(AnnouncementDeliverySummaryQuery::class)->forRuns($a['alliance'], [
            $a['run'] => $a['content'], $b['run'] => $b['content'],
        ]);

        self::assertSame(63, $result[$a['run']]->retryCandidateCount);
        self::assertSame(72, $result[$b['run']]->retryCandidateCount);
        self::assertSame(array_slice($aIds, 0, 50), $result[$a['run']]->failedDeliveryIds);
        self::assertSame(array_slice($bIds, 0, 50), $result[$b['run']]->failedDeliveryIds);
    }

    public function test_empty_scope_never_queries_a_tenant_wide_default(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            self::assertSame([], app(AnnouncementDeliverySummaryQuery::class)->forRuns((string) Str::ulid(), []));
            self::assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public static function invalidScopes(): iterable
    {
        yield 'empty alliance' => ['', ['run' => 'content']];
        yield 'blank alliance' => ['   ', ['run' => 'content']];
        yield 'empty run' => ['alliance', ['' => 'content']];
        yield 'numeric run' => ['alliance', [12 => 'content']];
        yield 'empty content' => ['alliance', ['run' => '']];
        yield 'oversized' => ['alliance', array_fill_keys(array_map(static fn (int $i): string => 'run-'.$i, range(1, 101)), 'content')];
    }

    #[DataProvider('invalidScopes')]
    public function test_invalid_or_unbounded_scope_fails_closed(string $allianceId, array $map): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(AnnouncementDeliverySummaryQuery::class)->forRuns($allianceId, $map);
    }

    public function test_unknown_delivery_status_is_not_omitted_from_totals(): void
    {
        $fixture = $this->broadcast();
        $this->messages($fixture, 1, ['unrecognized']);

        $this->expectException(UnexpectedValueException::class);
        app(AnnouncementDeliverySummaryQuery::class)->forRuns($fixture['alliance'], [$fixture['run'] => $fixture['content']]);
    }

    public function test_fresh_schema_has_the_scoped_announcement_lookup_index(): void
    {
        $index = DB::selectOne("select indexdef from pg_indexes where schemaname = current_schema() and indexname = 'notification_message_broadcast_scope'");
        self::assertNotNull($index);
        foreach (['notification_type', 'subject_type', 'alliance_id', 'broadcast_run_id', 'subject_id'] as $column) {
            self::assertStringContainsString($column, $index->indexdef);
        }
    }

    public function test_current_manager_http_scope_is_required_and_revocation_denies_the_projection(): void
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $player = $scenario->player((int) $user->id);
        $alliance = $scenario->alliance($player);
        $own = $this->broadcast($alliance->allianceId);
        $foreign = $this->broadcast();
        $this->messages($own, 1, ['sent']);
        $this->messages($foreign, 1, ['failed']);

        $request = $this->actingAs($user)
            ->withSession([(string) config('game_world.active_player_session_key') => $player->playerId])
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(request()) ?? '');
        $response = $request->get('/alliance/content/manage')->assertOk();
        self::assertSame([$own['content']], array_column($response->json('props.content'), 'id'));
        self::assertSame(1, $response->json('props.content.0.broadcastRuns.0.readCount'));
        self::assertSame(0, $response->json('props.content.0.broadcastRuns.0.deliveryCounts.failed'));

        AllianceMembership::query()->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $player->playerId)->update(['rank' => 'r1']);
        $request->get('/alliance/content/manage')->assertForbidden();

        AllianceMembership::query()->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $player->playerId)->update(['status' => MembershipStatus::Suspended->value]);
        // The real context middleware rejects a lost active membership before the controller.
        $request->get('/alliance/content/manage')->assertStatus(409);
    }

    /** @return array{alliance:string,content:string,run:string} */
    private function broadcast(?string $allianceId = null): array
    {
        if ($allianceId === null) {
            $scenario = new ScenarioFactory;
            $owner = $scenario->player($scenario->account()->userId);
            $allianceId = $scenario->alliance($owner)->allianceId;
        }
        $author = (string) AllianceMembership::query()->where('alliance_id', $allianceId)->value('player_id');
        $content = ContentItem::query()->create([
            'alliance_id' => $allianceId, 'created_by_player_id' => $author, 'updated_by_player_id' => $author,
            'type' => ContentType::Announcement, 'visibility' => ContentVisibility::Members,
            'status' => ContentStatus::Published, 'title' => 'Outcome fixture',
            'slug' => 'outcome-'.strtolower((string) Str::ulid()), 'body' => 'Content-owned truth',
            'locale' => 'en', 'sort_order' => 0, 'current_revision_number' => 1,
            'notify_members' => true, 'published_at' => now(),
        ]);
        $run = AnnouncementBroadcastRun::query()->create([
            'alliance_id' => $allianceId, 'content_item_id' => $content->id,
            'scheduled_for' => now(), 'last_visited_at' => now(),
            'content_revision_number' => 1, 'status' => 'queued',
            'recipient_count' => 17, 'delivery_count' => 19, 'skipped_count' => 3,
            'suppressed_count' => 4, 'replayed_count' => 5,
            'idempotency_key' => (string) Str::ulid(),
        ]);

        return ['alliance' => $allianceId, 'content' => (string) $content->id, 'run' => (string) $run->id];
    }

    /**
     * @param array{alliance:string,content:string,run:string} $scope
     * @param list<string> $statuses
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $messageOverrides
     * @param array<string,mixed> $routeOverrides
     * @return list<string>
     */
    private function messages(array $scope, int $count, array $statuses, array $metadata = [], array $messageOverrides = [], array $routeOverrides = []): array
    {
        $messages = [];
        $routes = [];
        $ids = [];
        $at = '2026-09-11 10:00:00+00:00';
        for ($i = 0; $i < $count; $i++) {
            $messageId = (string) Str::ulid();
            $messages[] = array_replace([
                'id' => $messageId, 'notification_type' => 'alliance.announcement',
                'subject_type' => 'content_item', 'subject_id' => $scope['content'],
                'recipient_user_id' => 123, 'title' => 'Outcome record', 'available_at' => $at,
                'read_at' => $at, 'idempotency_key' => hash('sha256', 'message:'.$messageId),
                'metadata' => json_encode(array_replace([
                    'alliance_id' => $scope['alliance'], 'content_item_id' => $scope['content'],
                    'broadcast_run_id' => $scope['run'],
                ], $metadata), JSON_THROW_ON_ERROR), 'created_at' => $at, 'updated_at' => $at,
            ], $messageOverrides);
            foreach ($statuses as $status) {
                $id = (string) Str::ulid();
                $routes[] = array_replace([
                    'id' => $id, 'notification_message_id' => $messageId, 'channel' => 'discord',
                    'status' => $status, 'attempt_count' => 1, 'max_attempts' => 5,
                    'due_at' => $at, 'idempotency_key' => hash('sha256', 'route:'.$id),
                    'created_at' => $at, 'updated_at' => $at,
                ], $routeOverrides);
                $ids[] = $id;
            }
        }
        foreach (array_chunk($messages, 200) as $chunk) {
            DB::table('notification_messages')->insert($chunk);
        }
        foreach (array_chunk($routes, 200) as $chunk) {
            DB::table('notification_deliveries')->insert($chunk);
        }

        return $ids;
    }

    /** @param array{alliance:string,content:string,run:string} $scope */
    private function management(array $scope): array
    {
        return app(AnnouncementBroadcastManagementQuery::class)->forAlliance($scope['alliance'])['runs'][$scope['content']][0];
    }
}
