<?php

declare(strict_types=1);

namespace Tests\Contexts\Communications\Delivery\Integration\Concurrency;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Communications\Delivery\Actions\BuildNotificationDigestDispatches;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDigests;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Actions\SetNotificationRoutingPolicy;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationDigestDispatch;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class DigestMemberScopeTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-11T09:15:00Z');
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function mismatches(): iterable
    {
        yield 'recipient' => ['recipient'];
        yield 'endpoint' => ['endpoint'];
        yield 'channel' => ['channel'];
    }

    public static function outcomesAndMismatches(): iterable
    {
        foreach (self::mismatches() as $name => [$kind]) {
            yield $name.' success' => [$kind, 204];
            yield $name.' failure' => [$kind, 400];
        }
    }

    public function test_valid_member_is_sent_and_finalized_once(): void
    {
        [$dispatch, $route] = $this->fixture();
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
        self::assertSame(1, app(ProcessNotificationDigests::class)->handle(1));
        self::assertSame(DeliveryStatus::Sent, $dispatch->refresh()->status);
        self::assertSame(DeliveryStatus::Sent, $route->refresh()->status);
        self::assertSame(0, app(ProcessNotificationDigests::class)->handle(1));
        Http::assertSentCount(1);
    }

    #[DataProvider('mismatches')]
    public function test_claim_detaches_foreign_scope_without_sending_or_mutating_it(string $kind): void
    {
        [$dispatch, $route] = $this->fixture();
        $foreign = $this->foreignRoute($dispatch, $kind);
        $before = $foreign->getRawOriginal();
        $this->attach($dispatch, $foreign);
        Http::fake(['discord.com/*' => Http::response(null, 204)]);

        self::assertSame(1, app(ProcessNotificationDigests::class)->handle(1));

        Http::assertSentCount(1);
        Http::assertSent(static fn ($request): bool => str_contains((string) $request['content'], 'Allowed original')
            && ! str_contains((string) $request['content'], 'Different scope'));
        self::assertSame($before, $foreign->refresh()->getRawOriginal());
        self::assertFalse($this->attached($dispatch, $foreign));
        self::assertSame(DeliveryStatus::Sent, $route->refresh()->status);
    }

    public function test_digest_with_only_an_invalid_member_never_calls_provider(): void
    {
        [$dispatch, $route] = $this->fixture();
        DB::table('notification_digest_members')->where('notification_digest_dispatch_id', $dispatch->id)->delete();
        $foreign = $this->foreignRoute($dispatch, 'recipient');
        $this->attach($dispatch, $foreign);
        $before = $foreign->getRawOriginal();
        Http::fake(['discord.com/*' => Http::response(null, 204)]);

        self::assertSame(0, app(ProcessNotificationDigests::class)->handle(1));

        Http::assertNothingSent();
        self::assertSame(DeliveryStatus::Cancelled, $dispatch->refresh()->status);
        self::assertSame($before, $foreign->refresh()->getRawOriginal());
        self::assertSame(DeliveryStatus::Queued, $route->refresh()->status);
        self::assertFalse($this->attached($dispatch, $foreign));
    }

    #[DataProvider('mismatches')]
    public function test_exhaustion_never_finalizes_an_out_of_scope_route(string $kind): void
    {
        [$dispatch, $route] = $this->fixture();
        $foreign = $this->foreignRoute($dispatch, $kind);
        $this->attach($dispatch, $foreign);
        $before = $foreign->getRawOriginal();
        $dispatch->forceFill(['status' => DeliveryStatus::Pending, 'attempt_count' => 5,
            'max_attempts' => 5, 'updated_at' => now()->subMinutes(6)])->save();
        Http::fake(['discord.com/*' => Http::response(null, 204)]);

        self::assertSame(0, app(ProcessNotificationDigests::class)->handle(1));

        Http::assertNothingSent();
        self::assertSame(DeliveryStatus::Failed, $dispatch->refresh()->status);
        self::assertSame(DeliveryStatus::Failed, $route->refresh()->status);
        self::assertSame($before, $foreign->refresh()->getRawOriginal());
        self::assertFalse($this->attached($dispatch, $foreign));
    }

    #[DataProvider('outcomesAndMismatches')]
    public function test_old_provider_result_does_not_finalize_a_rebound_route(string $kind, int $status): void
    {
        [$dispatch, $route] = $this->fixture();
        $foreign = $this->foreignRoute($dispatch, $kind);
        $rebound = null;
        Http::fake(['discord.com/*' => function () use ($route, $foreign, $kind, $status, &$rebound) {
            self::assertSame(0, DB::connection()->transactionLevel(), 'Provider work must be outside the claim transaction.');
            $attributes = match ($kind) {
                'recipient' => ['notification_message_id' => $foreign->notification_message_id],
                'endpoint' => ['notification_endpoint_id' => $foreign->notification_endpoint_id],
                'channel' => ['channel' => DeliveryChannel::Email, 'notification_endpoint_id' => null],
            };
            $route->refresh()->forceFill($attributes)->save();
            $rebound = $route->getRawOriginal();

            return Http::response(null, $status);
        }]);

        self::assertSame(1, app(ProcessNotificationDigests::class)->handle(1));

        Http::assertSentCount(1);
        self::assertNotNull($rebound);
        self::assertSame($rebound, $route->refresh()->getRawOriginal());
        self::assertFalse($this->attached($dispatch, $route));
        self::assertSame($status === 204 ? DeliveryStatus::Sent : DeliveryStatus::Failed, $dispatch->refresh()->status);
    }

    /** @return array{NotificationDigestDispatch, NotificationDelivery} */
    private function fixture(): array
    {
        $userId = (new ScenarioFactory)->account()->userId;
        $this->policy($userId);
        $endpoint = $this->endpoint($userId);
        $route = $this->queue($userId, 'Allowed original', DeliveryChannel::Discord, (string) $endpoint->id);
        Carbon::setTestNow('2026-09-11T10:01:00Z');
        app(BuildNotificationDigestDispatches::class)->handle();
        $dispatch = NotificationDigestDispatch::query()->where('notification_endpoint_id', $endpoint->id)->sole();

        return [$dispatch, $route];
    }

    private function foreignRoute(NotificationDigestDispatch $dispatch, string $kind): NotificationDelivery
    {
        Carbon::setTestNow('2026-09-11T09:15:00Z');
        $userId = $kind === 'recipient' ? (new ScenarioFactory)->account()->userId : (int) $dispatch->recipient_user_id;
        $this->policy($userId);
        if ($kind === 'channel') {
            User::query()->whereKey($userId)->update(['email_verified_at' => now()]);
            app(SetNotificationPreference::class)->handle($userId, null, 'account.security', DeliveryChannel::Email, true);
            $route = $this->queue($userId, 'Different scope', DeliveryChannel::Email, null);
        } else {
            $endpoint = $this->endpoint($userId);
            $route = $this->queue($userId, 'Different scope', DeliveryChannel::Discord, (string) $endpoint->id);
        }
        Carbon::setTestNow('2026-09-11T10:01:00Z');

        return $route;
    }

    private function policy(int $userId): void
    {
        app(SetNotificationRoutingPolicy::class)->handle($userId, null, 'UTC', false, null, null, false, null, DigestCadence::Hourly);
    }

    private function endpoint(int $userId): NotificationEndpoint
    {
        return NotificationEndpoint::query()->create(['recipient_user_id' => $userId, 'player_id' => null,
            'channel' => DeliveryChannel::Discord, 'label' => 'Scoped destination', 'enabled' => true,
            'configuration' => ['webhook_url' => 'https://discord.com/api/webhooks/123456789/scoped-token'],
        ]);
    }

    private function queue(int $userId, string $title, DeliveryChannel $channel, ?string $endpoint): NotificationDelivery
    {
        $receipt = app(NotificationDeliveryService::class)->queue(NotificationIntent::fromScalars(
            notificationType: 'account.security', recipientUserId: $userId, playerId: null,
            availableAt: now(), idempotencyKey: $title.':'.$userId.':'.$endpoint, title: $title,
            subjectType: 'account_security_event', subjectId: 'scope.fixture', metadata: ['event' => 'scope.fixture'],
        ));

        return NotificationDelivery::query()->where('notification_message_id', $receipt->messageId)
            ->where('channel', $channel->value)->where('notification_endpoint_id', $endpoint)->sole();
    }

    private function attach(NotificationDigestDispatch $dispatch, NotificationDelivery $route): void
    {
        DB::table('notification_digest_members')->insert(['notification_digest_dispatch_id' => $dispatch->id,
            'notification_delivery_id' => $route->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function attached(NotificationDigestDispatch $dispatch, NotificationDelivery $route): bool
    {
        return DB::table('notification_digest_members')->where('notification_digest_dispatch_id', $dispatch->id)
            ->where('notification_delivery_id', $route->id)->exists();
    }
}
