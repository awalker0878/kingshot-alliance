<?php

declare(strict_types=1);

namespace Tests\Contexts\Communications\Delivery\Integration\Concurrency;

use App\Contexts\Communications\Delivery\Actions\BuildNotificationDigestDispatches;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDigests;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\SetNotificationEndpointState;
use App\Contexts\Communications\Delivery\Actions\SetNotificationRoutingPolicy;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationDigestDispatch;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Fiber;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Contexts\Operations\Participation\Support\EventReminderSourceFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class NotificationAttemptFencingTest extends TestCase
{
    use DatabaseTruncation;

    private string $primary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->primary = DB::getDefaultConnection();
        config()->set('database.connections.delivery_competitor', config('database.connections.'.$this->primary));
        DB::connection('delivery_competitor')->statement("SET lock_timeout = '100ms'");
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->primary);
        DB::purge('delivery_competitor');
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function kinds(): iterable
    {
        yield 'immediate' => [false];
        yield 'digest' => [true];
    }

    public static function ineligibleSelections(): iterable
    {
        foreach (self::kinds() as $kind => [$digest]) {
            foreach (['active lease', 'retry not due', 'retry disabled', 'future due'] as $state) {
                yield $kind.' '.$state => [$digest, $state];
            }
        }
    }

    #[DataProvider('ineligibleSelections')]
    public function test_preselected_work_rechecks_current_eligibility_under_its_lock(bool $digest, string $state): void
    {
        $fixture = $this->fixture($digest);
        $changes = match ($state) {
            'active lease' => ['status' => 'pending', 'attempt_count' => 1, 'updated_at' => now()],
            'retry not due' => ['status' => 'failed', 'attempt_count' => 1, 'next_attempt_at' => now()->addMinute()],
            'retry disabled' => ['status' => 'failed', 'attempt_count' => 1, 'next_attempt_at' => null],
            'future due' => ['due_at' => now()->addMinute()],
        };
        $intercepted = false;
        DB::connection()->beforeExecuting(function (string $sql) use ($fixture, $changes, &$intercepted): void {
            if ($intercepted || ! str_contains($sql, '"'.$fixture['table'].'"') || ! str_contains($sql, 'for update')) {
                return;
            }
            $intercepted = true;
            DB::connection('delivery_competitor')->transaction(function (Connection $connection) use ($fixture, $changes): void {
                $connection->table($fixture['table'])->where('id', $fixture['id'])->lockForUpdate()->first();
                $connection->table($fixture['table'])->where('id', $fixture['id'])->update($changes);
            });
        });
        Http::fake(['discord.com/*' => Http::response(null, 204)]);

        self::assertSame(0, $this->worker($digest)->handle(1));
        self::assertTrue($intercepted);
        Http::assertNothingSent();
        self::assertSame(0, $this->outcomes()->count());
        self::assertSame($state === 'future due' ? 0 : 1, (int) DB::table($fixture['table'])->where('id', $fixture['id'])->value('attempt_count'));
    }

    public static function staleOutcomes(): iterable
    {
        foreach (self::kinds() as $kind => [$digest]) {
            yield $kind.' old success' => [$digest, 204, 503];
            yield $kind.' old failure' => [$digest, 503, 204];
        }
    }

    #[DataProvider('staleOutcomes')]
    public function test_stale_provider_response_cannot_finalize_or_change_health_for_a_new_attempt(bool $digest, int $oldStatus, int $newStatus): void
    {
        $fixture = $this->fixture($digest);
        $calls = 0;
        $transactionLevels = [];
        Http::fake(['discord.com/*' => function () use (&$calls, &$transactionLevels) {
            $calls++;
            $transactionLevels[] = DB::connection()->transactionLevel();
            // Pause after the real claim commits, at the external transport boundary.
            $status = Fiber::suspend();

            return Http::response(null, $status);
        }]);
        $first = new Fiber(fn (): int => $this->worker($digest)->handle(1));
        $second = new Fiber(fn (): int => $this->worker($digest)->handle(1));

        try {
            $first->start();
            self::assertTrue($first->isSuspended());
            self::assertSame(1, (int) DB::table($fixture['table'])->where('id', $fixture['id'])->value('attempt_count'));
            Carbon::setTestNow(now()->addMinutes(6));
            DB::setDefaultConnection('delivery_competitor');
            $second->start();
            self::assertTrue($second->isSuspended());
            self::assertSame(2, (int) DB::table($fixture['table'])->where('id', $fixture['id'])->value('attempt_count'));
            $claimed = $this->state($fixture);

            DB::setDefaultConnection($this->primary);
            $first->resume($oldStatus);
            self::assertSame($claimed, $this->state($fixture), 'An obsolete response must not change the newer claim, route members, endpoint health or outbox.');
            DB::setDefaultConnection('delivery_competitor');
            $second->resume($newStatus);
            self::assertSame(1, $first->getReturn());
            self::assertSame(1, $second->getReturn());
        } finally {
            DB::setDefaultConnection($this->primary);
        }

        self::assertSame(2, $calls);
        self::assertSame([0, 0], $transactionLevels, 'Provider IO must remain outside database transactions.');
        $row = DB::table($fixture['table'])->where('id', $fixture['id'])->first();
        self::assertSame($newStatus === 204 ? 'sent' : 'failed', $row->status);
        self::assertSame(2, (int) $row->attempt_count);
        $endpoint = NotificationEndpoint::query()->findOrFail($fixture['endpoint']);
        self::assertSame($newStatus === 204 ? 0 : 1, $endpoint->consecutive_failures);
        $events = $this->outcomes()->get();
        self::assertCount($digest && $newStatus !== 204 ? 0 : count($fixture['routes']), $events);
        foreach ($events as $event) {
            self::assertSame(2, $event->payload['attempt_count']);
        }
    }

    #[DataProvider('kinds')]
    public function test_exact_lease_boundary_is_recoverable_but_newer_leases_are_not(bool $digest): void
    {
        $fixture = $this->fixture($digest);
        DB::table($fixture['table'])->where('id', $fixture['id'])->update([
            'status' => 'pending', 'attempt_count' => 1, 'updated_at' => now()->subMinutes(5)->addSecond(),
        ]);
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
        self::assertSame(0, $this->worker($digest)->handle());
        Http::assertNothingSent();

        Carbon::setTestNow(now()->addSecond());
        self::assertSame(1, $this->worker($digest)->handle());
        Http::assertSentCount(1);
        self::assertSame(2, (int) DB::table($fixture['table'])->where('id', $fixture['id'])->value('attempt_count'));
    }

    #[DataProvider('kinds')]
    public function test_exhausted_expired_claim_is_terminalized_without_network_or_repeated_starvation(bool $digest): void
    {
        $fixture = $this->fixture($digest);
        DB::table($fixture['table'])->where('id', $fixture['id'])->update([
            'status' => 'pending', 'attempt_count' => 5, 'max_attempts' => 5, 'updated_at' => now()->subMinutes(6),
        ]);
        Http::fake(['discord.com/*' => Http::response(null, 204)]);

        self::assertSame(0, $this->worker($digest)->handle(1));
        Http::assertNothingSent();
        $row = DB::table($fixture['table'])->where('id', $fixture['id'])->first();
        self::assertSame('failed', $row->status);
        self::assertSame(5, (int) $row->attempt_count);
        self::assertNull($row->next_attempt_at);
        self::assertStringContainsString('acknowledgement', $row->last_error);
        self::assertSame(0, NotificationEndpoint::query()->findOrFail($fixture['endpoint'])->consecutive_failures, 'A lost acknowledgement is not a newly observed transport failure.');
        foreach ($fixture['routes'] as $id) {
            $route = NotificationDelivery::query()->findOrFail($id);
            self::assertSame(DeliveryStatus::Failed, $route->status);
            self::assertNull($route->next_attempt_at);
        }
        self::assertSame(count($fixture['routes']), $this->outcomes()->count());
        $before = $this->state($fixture);
        self::assertSame(0, $this->worker($digest)->handle(1));
        self::assertSame($before, $this->state($fixture));

        $next = $this->fixture($digest);
        self::assertSame(1, $this->worker($digest)->handle(1), 'Exhausted rows must no longer occupy the bounded candidate batch.');
        self::assertSame('sent', DB::table($next['table'])->where('id', $next['id'])->value('status'));
    }

    #[DataProvider('kinds')]
    public function test_another_postgres_connection_cannot_claim_the_same_locked_generation(bool $digest): void
    {
        $fixture = $this->fixture($digest);
        self::assertNotSame(DB::connection()->getPdo(), DB::connection('delivery_competitor')->getPdo());
        $armed = true;
        $blocked = null;
        DB::listen(function (QueryExecuted $event) use ($fixture, $digest, &$armed, &$blocked): void {
            if (! $armed || $event->connectionName !== $this->primary
                || ! str_contains($event->sql, '"'.$fixture['table'].'"')
                || ! str_contains($event->sql, 'for update')) {
                return;
            }
            $armed = false;
            DB::setDefaultConnection('delivery_competitor');
            try {
                $this->worker($digest)->handle(1);
            } catch (QueryException $exception) {
                $blocked = $exception->errorInfo[0] ?? null;
            } finally {
                DB::setDefaultConnection($this->primary);
            }
        });
        Http::fake(['discord.com/*' => Http::response(null, 204)]);

        self::assertSame(1, $this->worker($digest)->handle(1));
        self::assertSame('55P03', $blocked, 'The independent PostgreSQL worker must wait on the current row lock.');
        self::assertSame(1, (int) DB::table($fixture['table'])->where('id', $fixture['id'])->value('attempt_count'));
        Http::assertSentCount(1);
    }

    #[DataProvider('kinds')]
    public function test_outbox_failure_rolls_back_completion_and_endpoint_health_together(bool $digest): void
    {
        $fixture = $this->fixture($digest);
        $armed = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$armed): void {
            if ($armed && str_starts_with($sql, 'insert into "outbox_messages"')) {
                $armed = false;
                throw new RuntimeException('Injected outbox storage failure.');
            }
        });
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
        try {
            $this->worker($digest)->handle(1);
            self::fail('A completion storage failure must not be swallowed.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected outbox storage failure.', $exception->getMessage());
        }
        self::assertSame('pending', DB::table($fixture['table'])->where('id', $fixture['id'])->value('status'));
        self::assertSame(0, $this->outcomes()->count());
        self::assertNull(NotificationEndpoint::query()->findOrFail($fixture['endpoint'])->last_successful_delivery_at);
        foreach ($fixture['routes'] as $id) {
            self::assertSame($digest ? DeliveryStatus::Queued : DeliveryStatus::Pending, NotificationDelivery::query()->findOrFail($id)->status);
        }
        self::assertSame(0, DB::connection()->transactionLevel());

        Carbon::setTestNow(now()->addMinutes(6));
        self::assertSame(1, $this->worker($digest)->handle(1));
        self::assertSame('sent', DB::table($fixture['table'])->where('id', $fixture['id'])->value('status'));
        self::assertSame(count($fixture['routes']), $this->outcomes()->count());
        foreach ($this->outcomes()->get() as $event) {
            self::assertSame(2, $event->payload['attempt_count']);
        }
        Http::assertSentCount(2);
    }

    #[DataProvider('kinds')]
    public function test_a_late_acknowledgement_cannot_overwrite_an_explicit_endpoint_pause(bool $digest): void
    {
        $fixture = $this->fixture($digest);
        $endpoint = NotificationEndpoint::query()->findOrFail($fixture['endpoint']);
        Http::fake(['discord.com/*' => static function () {
            Fiber::suspend();

            return Http::response(null, 204);
        }]);
        $worker = new Fiber(fn (): int => $this->worker($digest)->handle(1));
        $worker->start();
        self::assertTrue($worker->isSuspended());
        app(SetNotificationEndpointState::class)->handle($endpoint->recipient_user_id, (string) $endpoint->player_id, (string) $endpoint->id, false);
        $paused = (array) DB::table('notification_endpoints')->where('id', $endpoint->id)->first();
        $worker->resume();

        self::assertSame($paused, (array) DB::table('notification_endpoints')->where('id', $endpoint->id)->first());
        self::assertSame('sent', DB::table($fixture['table'])->where('id', $fixture['id'])->value('status'));
        Http::assertSentCount(1);
    }

    public function test_digest_completion_does_not_mutate_a_detached_queued_route(): void
    {
        $fixture = $this->fixture(true);
        Http::fake(['discord.com/*' => static function () {
            Fiber::suspend();

            return Http::response(null, 204);
        }]);
        $worker = new Fiber(fn (): int => $this->worker(true)->handle(1));
        $worker->start();
        self::assertTrue($worker->isSuspended());
        $detached = $fixture['routes'][0];
        DB::connection('delivery_competitor')->table('notification_digest_members')->where('notification_delivery_id', $detached)->delete();
        $before = (array) DB::table('notification_deliveries')->where('id', $detached)->first();
        $worker->resume();

        self::assertSame($before, (array) DB::table('notification_deliveries')->where('id', $detached)->first());
        self::assertSame(DeliveryStatus::Sent, NotificationDelivery::query()->findOrFail($fixture['routes'][1])->status);
        self::assertSame(1, $this->outcomes()->count());
    }

    #[DataProvider('kinds')]
    public function test_retry_time_is_inclusive_without_sending_early(bool $digest): void
    {
        $fixture = $this->fixture($digest);
        DB::table($fixture['table'])->where('id', $fixture['id'])->update([
            'status' => 'failed', 'attempt_count' => 1, 'next_attempt_at' => now()->addSecond(),
        ]);
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
        self::assertSame(0, $this->worker($digest)->handle(1));
        Http::assertNothingSent();
        Carbon::setTestNow(now()->addSecond());
        self::assertSame(1, $this->worker($digest)->handle(1));
        self::assertSame(2, (int) DB::table($fixture['table'])->where('id', $fixture['id'])->value('attempt_count'));
        Http::assertSentCount(1);
    }

    #[DataProvider('kinds')]
    public function test_exhaustion_after_a_recorded_retry_failure_does_not_reuse_its_outbox_identity(bool $digest): void
    {
        $fixture = $this->fixture($digest);
        Http::fake(['discord.com/*' => Http::response(null, 503)]);
        self::assertSame(1, $this->worker($digest)->handle(1));
        self::assertSame($digest ? 0 : 1, $this->outcomes()->count());
        DB::table($fixture['table'])->where('id', $fixture['id'])->update(['max_attempts' => 1]);
        Carbon::setTestNow(now()->addMinute());

        self::assertSame(0, $this->worker($digest)->handle(1));
        Http::assertSentCount(1);
        self::assertSame('failed', DB::table($fixture['table'])->where('id', $fixture['id'])->value('status'));
        self::assertNull(DB::table($fixture['table'])->where('id', $fixture['id'])->value('next_attempt_at'));
        self::assertSame(2, $this->outcomes()->count());
        $terminal = $this->outcomes()->where('idempotency_key', 'like', '%:exhausted')->get();
        self::assertCount(count($fixture['routes']), $terminal);
        foreach ($terminal as $event) {
            self::assertFalse($event->payload['retryable']);
            self::assertSame(1, $event->payload['attempt_count']);
        }
    }

    private function worker(bool $digest): ProcessNotificationDeliveries|ProcessNotificationDigests
    {
        return app($digest ? ProcessNotificationDigests::class : ProcessNotificationDeliveries::class);
    }

    /** @return array{table:string,id:string,endpoint:string,routes:list<string>} */
    private function fixture(bool $digest): array
    {
        Carbon::setTestNow('2026-09-10T09:15:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId);
        $alliance = $scenario->alliance($player);
        $endpoint = app(SaveNotificationEndpoint::class)->handle(
            $account->userId, $player->playerId, DeliveryChannel::Discord, 'Fencing route',
            ['webhook_url' => 'https://discord.com/api/webhooks/91234/abcdefghijklmnopqrstuvwxyz_ABCDEFGHIJKLMNOPQRSTUVWXYZ'],
        );
        if ($digest) {
            app(SetNotificationRoutingPolicy::class)->handle(
                recipientUserId: $account->userId, playerId: $player->playerId, timezone: 'UTC',
                quietHoursEnabled: false, quietHoursStart: null, quietHoursEnd: null,
                allowUrgentDuringQuietHours: false, mutedUntil: null, digestCadence: DigestCadence::Hourly,
            );
        }
        $source = (new EventReminderSourceFixture)->forPlayer($player->playerId);
        $routes = [];
        for ($index = 0; $index < ($digest ? 2 : 1); $index++) {
            $receipt = app(NotificationDeliveryService::class)->queue(NotificationIntent::fromScalars(
                notificationType: 'event.reminder', recipientUserId: $account->userId, playerId: $player->playerId,
                availableAt: now(), idempotencyKey: 'fence:'.$endpoint.':'.$index, title: 'Claim fixture',
                subjectType: 'event_occurrence', subjectId: $source['occurrence'],
                metadata: ['alliance_id' => $alliance->allianceId, 'broadcast_run_id' => 'run-fence', 'content_item_id' => 'content-fence', 'event_id' => $source['event'], 'rule_id' => $source['rule']],
            ));
            $routes[] = (string) NotificationDelivery::query()->where('notification_message_id', $receipt->messageId)
                ->where('channel', 'discord')->firstOrFail()->id;
        }
        Carbon::setTestNow('2026-09-10T10:01:00Z');
        if ($digest) {
            app(BuildNotificationDigestDispatches::class)->handle();
            $id = (string) NotificationDigestDispatch::query()->where('notification_endpoint_id', $endpoint)->firstOrFail()->id;
        } else {
            $id = $routes[0];
        }

        return ['table' => $digest ? 'notification_digest_dispatches' : 'notification_deliveries', 'id' => $id, 'endpoint' => $endpoint, 'routes' => $routes];
    }

    /** @param array{table:string,id:string,endpoint:string,routes:list<string>} $fixture
     * @return array<string,mixed>
     */
    private function state(array $fixture): array
    {
        return [
            'claim' => (array) DB::table($fixture['table'])->where('id', $fixture['id'])->first(),
            'routes' => DB::table('notification_deliveries')->whereIn('id', $fixture['routes'])->orderBy('id')->get()->toJson(),
            'members' => DB::table('notification_digest_members')->whereIn('notification_delivery_id', $fixture['routes'])->orderBy('notification_delivery_id')->get()->toJson(),
            'endpoint' => (array) DB::table('notification_endpoints')->where('id', $fixture['endpoint'])->first(),
            'outcomes' => $this->outcomes()->orderBy('id')->get()->toJson(),
        ];
    }

    /** @return Builder<OutboxMessage> */
    private function outcomes(): Builder
    {
        return OutboxMessage::query()->whereIn('event_type', ['broadcast.delivery.succeeded', 'broadcast.delivery.failed']);
    }
}
