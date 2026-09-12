<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\GiftCodes\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeSourceOperationalAlerts;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class OperationalAlertProgressTest extends TestCase
{
    use DatabaseTruncation;

    /** @return list<int> */
    private function administrators(int $count): array
    {
        $ids = [];
        foreach (User::factory()->count($count)->create() as $user) {
            $ids[] = (int) $user->id;
            DB::table('platform_administrators')->insert(['id' => strtolower((string) Str::ulid()), 'user_id' => $user->id,
                'granted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $ids;
    }

    private function source(): GiftCodeSourceRegistry
    {
        return GiftCodeSourceRegistry::query()->create(['source_key' => 'progress-'.Str::ulid(), 'name' => 'Operational source',
            'classification' => 'official', 'verification_method' => 'fixture', 'is_active' => true, 'ingestion_enabled' => true,
            'last_ingestion_success_at' => now(), 'last_quota_remaining' => 0]);
    }

    public function test_source_and_recipient_frontiers_reach_every_record_across_small_invocations(): void
    {
        $admins = $this->administrators(31);
        $sources = [];
        for ($i = 0; $i < 5; $i++) {
            $sources[] = (string) $this->source()->id;
        }
        $action = app(QueueGiftCodeSourceOperationalAlerts::class);
        for ($i = 0; $i < 9; $i++) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $result = $action->handle(2);
            $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
            DB::disableQueryLog();
            self::assertLessThanOrEqual(2, $result['sources']);
            self::assertLessThanOrEqual(50, $result['recipients']);
            self::assertLessThanOrEqual(500, $result['queued']);
            self::assertStringContainsString('limit 2', $sql);
            self::assertStringContainsString('limit 25', $sql);
            self::assertStringNotContainsString('select * from "gift_code_sources"', $sql);
        }
        $messages = DB::table('notification_messages')->where('notification_type', QueueGiftCodeSourceOperationalAlerts::NOTIFICATION_TYPE);
        self::assertSame(155, $messages->count());
        foreach ($sources as $source) {
            self::assertSame($admins, (clone $messages)->where('subject_id', $source)->orderBy('recipient_user_id')->pluck('recipient_user_id')->all());
        }
        self::assertSame(155, (clone $messages)->distinct()->count('idempotency_key'));
    }

    public function test_subscription_pages_and_equal_expiry_instants_have_distinct_complete_alerts(): void
    {
        $this->administrators(3);
        $source = $this->source();
        $source->forceFill(['last_quota_remaining' => null])->save();
        $expiry = now()->addHour();
        $rows = [];
        for ($i = 0; $i < 61; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'gift_code_source_id' => $source->id,
                'provider' => 'provider-'.$i, 'transport' => 'webhook', 'status' => 'active',
                'expires_at' => $expiry, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('gift_code_source_subscriptions')->insert($rows);
        $action = app(QueueGiftCodeSourceOperationalAlerts::class);
        for ($i = 0; $i < 4; $i++) {
            $result = $action->handle(1);
            self::assertLessThanOrEqual(25, $result['alerts']);
            self::assertLessThanOrEqual(75, $result['queued']);
        }
        self::assertSame(183, DB::table('notification_messages')->where('subject_id', $source->id)->count());
    }

    public function test_global_intent_budget_resumes_the_same_subscription_page_for_remaining_recipients(): void
    {
        $admins = $this->administrators(11);
        $source = $this->source();
        $source->forceFill(['last_quota_remaining' => null])->save();
        $expiry = now()->addHour();
        $rows = [];
        for ($i = 0; $i < 25; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'gift_code_source_id' => $source->id,
                'provider' => 'provider-'.$i, 'transport' => 'webhook', 'status' => 'pending',
                'expires_at' => $expiry, 'created_at' => now()->subHour(), 'updated_at' => now()->subHour()];
        }
        DB::table('gift_code_source_subscriptions')->insert($rows);
        $action = app(QueueGiftCodeSourceOperationalAlerts::class);
        $first = $action->handle();
        self::assertSame(500, $first['queued']);
        self::assertSame(10, $first['recipients']);
        self::assertSame($admins[9], DB::table('gift_code_source_alert_progress')->where('gift_code_source_id', $source->id)->value('recipient_after_id'));
        $second = $action->handle();
        self::assertSame(50, $second['queued']);
        self::assertSame(1, $second['recipients']);
        self::assertSame(550, DB::table('notification_messages')->where('subject_id', $source->id)->count());
    }

    public function test_empty_sources_empty_recipients_and_revocations_do_not_poison_progress(): void
    {
        $action = app(QueueGiftCodeSourceOperationalAlerts::class);
        self::assertSame(['sources' => 0, 'alerts' => 0, 'recipients' => 0, 'queued' => 0], $action->handle());
        $source = $this->source();
        self::assertSame(0, $action->handle()['queued']);
        $admins = $this->administrators(31);
        self::assertSame(25, $action->handle()['queued']);
        DB::table('platform_administrators')->whereIn('user_id', array_slice($admins, 25))->update(['revoked_at' => now()]);
        self::assertSame(0, $action->handle()['queued']);
        $source->forceFill(['revoked_at' => now()])->save();
        self::assertSame(0, $action->handle()['sources']);
        self::assertSame(25, DB::table('notification_messages')->where('subject_id', $source->id)->count());
        $source->forceFill(['revoked_at' => null])->save();
        DB::table('platform_administrators')->whereIn('user_id', array_slice($admins, 25))->update(['revoked_at' => null]);
        $action->handle();
        $action->handle();
        self::assertSame(31, DB::table('notification_messages')->where('subject_id', $source->id)->count());
    }

    public function test_late_delivery_failure_rolls_back_all_messages_and_both_progress_dimensions(): void
    {
        $this->administrators(3);
        $source = $this->source();
        $inserts = 0;
        DB::listen(static function (QueryExecuted $query) use (&$inserts): void {
            if (str_starts_with($query->sql, 'insert into "notification_messages"') && ++$inserts === 2) {
                throw new RuntimeException('Injected producer failure.');
            }
        });
        $action = app(QueueGiftCodeSourceOperationalAlerts::class);
        try {
            $action->handle();
            self::fail('The late failure must abort producer progress.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected producer failure.', $exception->getMessage());
        }
        self::assertSame(0, DB::table('notification_messages')->count());
        self::assertSame(0, DB::table('notification_deliveries')->count());
        self::assertSame(0, DB::table('gift_code_source_alert_progress')->count());
        self::assertNull(DB::table('gift_code_source_alert_sweep')->value('last_source_id'));
        self::assertSame(3, $action->handle()['queued']);
        self::assertSame(0, $action->handle()['queued']);
        self::assertSame(3, DB::table('notification_messages')->where('subject_id', $source->id)->count());
    }

    public function test_competing_invocations_skip_the_owned_sweep_without_waiting_or_duplicate_work(): void
    {
        $this->administrators(2);
        $this->source();
        config()->set('database.connections.alert_competitor', [...DB::connection()->getConfig(), 'name' => 'alert_competitor']);
        $primary = DB::getDefaultConnection();
        $other = DB::connection('alert_competitor');
        $other->statement("SET lock_timeout = '150ms'");
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, &$attempted): void {
            if (! $attempted && $query->connectionName === $primary && str_contains($query->sql, '"gift_code_source_alert_sweep"') && str_contains($query->sql, 'skip locked')) {
                $attempted = true;
                DB::setDefaultConnection('alert_competitor');
                try {
                    self::assertSame(['sources' => 0, 'alerts' => 0, 'recipients' => 0, 'queued' => 0], app(QueueGiftCodeSourceOperationalAlerts::class)->handle());
                } finally {
                    DB::setDefaultConnection($primary);
                }
            }
        });
        try {
            self::assertSame(2, app(QueueGiftCodeSourceOperationalAlerts::class)->handle()['queued']);
            self::assertTrue($attempted);
            self::assertSame(2, DB::table('notification_messages')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('alert_competitor');
        }
    }

    public function test_new_sources_and_administrators_wait_for_the_next_finite_frontier_without_starving_existing_work(): void
    {
        $admins = $this->administrators(26);
        $first = $this->source();
        $second = $this->source();
        $action = app(QueueGiftCodeSourceOperationalAlerts::class);
        self::assertSame(25, $action->handle(1)['queued']);
        $late = $this->administrators(1)[0];
        $new = $this->source();
        // Delete the visited source boundary. The scalar frontier still reaches the second source.
        $first->delete();
        self::assertSame(25, $action->handle(1)['queued']);
        self::assertSame((string) $second->id, DB::table('gift_code_source_alert_sweep')->value('last_source_id'));
        self::assertSame(0, DB::table('notification_messages')->where('subject_id', $new->id)->count());
        // A finite source sweep wraps before including the newly inserted source.
        self::assertSame(2, $action->handle(1)['queued']);
        self::assertSame(1, DB::table('notification_messages')->where('subject_id', $second->id)->where('recipient_user_id', $late)->count());
        $action->handle(1);
        self::assertSame(25, DB::table('notification_messages')->where('subject_id', $new->id)->count());
        self::assertCount(26, $admins);
    }

    public function test_current_health_is_recomputed_after_a_partial_recipient_frontier(): void
    {
        $admins = $this->administrators(26);
        $source = $this->source();
        $action = app(QueueGiftCodeSourceOperationalAlerts::class);
        self::assertSame(25, $action->handle()['queued']);
        $source->forceFill(['last_quota_remaining' => 100, 'health_status' => 'permission_revoked', 'last_ingestion_failure_at' => now()])->save();
        self::assertSame(1, $action->handle()['queued']);
        $last = DB::table('notification_messages')->where('recipient_user_id', $admins[25])->first();
        self::assertNotNull($last);
        self::assertStringContainsString('Provider permission was revoked.', $last->body);
        // Earlier recipients see the new meaning on the next complete sweep.
        self::assertSame(25, $action->handle()['queued']);
        self::assertSame(51, DB::table('notification_messages')->where('subject_id', $source->id)->count());
    }
}
