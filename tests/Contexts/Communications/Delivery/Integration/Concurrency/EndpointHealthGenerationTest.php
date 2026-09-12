<?php

declare(strict_types=1);

namespace Tests\Contexts\Communications\Delivery\Integration\Concurrency;

use App\Contexts\Communications\Delivery\Actions\BuildNotificationDigestDispatches;
use App\Contexts\Communications\Delivery\Actions\DeleteNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDigests;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\SetNotificationEndpointState;
use App\Contexts\Communications\Delivery\Actions\SetNotificationRoutingPolicy;
use App\Contexts\Communications\Delivery\Actions\UpdateNotificationEndpoint;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Enums\EndpointHealthStatus;
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
use Tests\Contexts\Operations\Participation\Support\EventReminderSourceFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class EndpointHealthGenerationTest extends TestCase
{
    use DatabaseTruncation;

    private const ORIGINAL = 'https://discord.com/api/webhooks/91234/original-token';

    private const REPLACEMENT = 'https://discord.com/api/webhooks/91234/replacement-token';

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

    public static function workersAndOutcomes(): iterable
    {
        foreach ([false, true] as $digest) {
            foreach ([204, 400] as $status) {
                yield ($digest ? 'digest' : 'immediate').' '.$status => [$digest, $status];
            }
        }
    }

    public static function staleObservations(): iterable
    {
        foreach (self::workersAndOutcomes() as $name => [$digest, $status]) {
            foreach (['rotate', 'rotate-back', 'pause-resume', 'label-update'] as $change) {
                yield $name.' '.$change => [$digest, $status, $change];
            }
        }
    }

    #[DataProvider('staleObservations')]
    public function test_old_credential_result_cannot_rewrite_a_new_verification_generation(bool $digest, int $status, string $change): void
    {
        $fixture = $this->fixture($digest);
        $expected = null;
        Http::fake(['discord.com/*' => function ($request) use ($fixture, $status, $change, &$expected) {
            self::assertSame(0, DB::connection()->transactionLevel(), 'Provider IO must remain outside a database transaction.');
            self::assertSame(self::ORIGINAL, $request->url());
            $endpoint = $fixture['endpoint'];
            if ($change === 'pause-resume') {
                app(SetNotificationEndpointState::class)->handle($fixture['user'], $fixture['player'], $endpoint, false);
                app(SetNotificationEndpointState::class)->handle($fixture['user'], $fixture['player'], $endpoint, true);
            } else {
                $this->update($fixture, $change === 'label-update' ? [] : ['webhook_url' => self::REPLACEMENT]);
                if ($change === 'rotate-back') {
                    $this->update($fixture, ['webhook_url' => self::ORIGINAL]);
                }
            }
            $current = NotificationEndpoint::query()->findOrFail($endpoint);
            self::assertSame(in_array($change, ['rotate-back', 'pause-resume'], true) ? 3 : 2, $current->verification_generation);
            self::assertNull($current->last_verified_at);
            $expected = $current->getRawOriginal();

            return Http::response(null, $status);
        }]);

        self::assertSame(1, $this->process($digest));

        Http::assertSentCount(1);
        self::assertNotNull($expected);
        self::assertSame($expected, NotificationEndpoint::query()->findOrFail($fixture['endpoint'])->getRawOriginal());
        $this->assertOutcome($fixture, $digest, $status);
    }

    #[DataProvider('workersAndOutcomes')]
    public function test_current_generation_records_the_actual_outcome(bool $digest, int $status): void
    {
        $fixture = $this->fixture($digest);
        Http::fake(['discord.com/*' => Http::response(null, $status)]);

        self::assertSame(1, $this->process($digest));

        $endpoint = NotificationEndpoint::query()->findOrFail($fixture['endpoint']);
        self::assertSame($status === 204 ? EndpointHealthStatus::Healthy : EndpointHealthStatus::Degraded, $endpoint->health_status);
        self::assertSame($status === 204 ? 0 : 1, $endpoint->consecutive_failures);
        $this->assertOutcome($fixture, $digest, $status);
        Http::assertSentCount(1);
    }

    #[DataProvider('workersAndOutcomes')]
    public function test_transport_observes_current_configuration_not_the_queued_generation(bool $digest, int $status): void
    {
        $fixture = $this->fixture($digest);
        $this->update($fixture, ['webhook_url' => self::REPLACEMENT]);
        Http::fake(['discord.com/*' => function ($request) use ($status) {
            self::assertSame(0, DB::connection()->transactionLevel());
            self::assertSame(self::REPLACEMENT, $request->url());

            return Http::response(null, $status);
        }]);

        self::assertSame(1, $this->process($digest));

        $endpoint = NotificationEndpoint::query()->findOrFail($fixture['endpoint']);
        self::assertSame(2, $endpoint->verification_generation);
        self::assertSame($status === 204 ? EndpointHealthStatus::Healthy : EndpointHealthStatus::Degraded, $endpoint->health_status);
        self::assertSame($status === 204 ? 0 : 1, $endpoint->consecutive_failures);
        $this->assertOutcome($fixture, $digest, $status);
        Http::assertSentCount(1);
    }

    #[DataProvider('workersAndOutcomes')]
    public function test_deleted_endpoint_result_cannot_change_a_new_destination(bool $digest, int $status): void
    {
        $fixture = $this->fixture($digest);
        $replacement = null;
        $expected = null;
        Http::fake(['discord.com/*' => function () use ($fixture, $status, &$replacement, &$expected) {
            app(DeleteNotificationEndpoint::class)->handle($fixture['user'], $fixture['player'], $fixture['endpoint']);
            $replacement = app(SaveNotificationEndpoint::class)->handle($fixture['user'], $fixture['player'], DeliveryChannel::Discord,
                'Replacement', ['webhook_url' => self::REPLACEMENT]);
            $expected = NotificationEndpoint::query()->findOrFail($replacement)->getRawOriginal();

            return Http::response(null, $status);
        }]);
        self::assertSame(1, $this->process($digest));
        self::assertNotNull($replacement);
        self::assertNotSame($fixture['endpoint'], $replacement);
        self::assertSame($expected, NotificationEndpoint::query()->findOrFail($replacement)->getRawOriginal());
        $this->assertOutcome($fixture, $digest, $status);
    }

    /** @return array{user:int,player:string,endpoint:string,route:string,dispatch:?string} */
    private function fixture(bool $digest): array
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId);
        $endpoint = app(SaveNotificationEndpoint::class)->handle($account->userId, $player->playerId, DeliveryChannel::Discord,
            'Original', ['webhook_url' => self::ORIGINAL]);
        self::assertSame(1, NotificationEndpoint::query()->findOrFail($endpoint)->verification_generation);
        app(SetNotificationRoutingPolicy::class)->handle($account->userId, $player->playerId, 'UTC', false, null, null,
            false, null, $digest ? DigestCadence::Hourly : DigestCadence::Immediate);
        $source = (new EventReminderSourceFixture)->forPlayer($player->playerId);
        $receipt = app(NotificationDeliveryService::class)->queue(NotificationIntent::fromScalars(
            notificationType: 'event.reminder', recipientUserId: $account->userId, playerId: $player->playerId,
            availableAt: now(), idempotencyKey: 'generation:'.$endpoint, title: 'Real source',
            subjectType: 'event_occurrence', subjectId: $source['occurrence'],
            metadata: ['event_id' => $source['event'], 'rule_id' => $source['rule']],
        ));
        $route = NotificationDelivery::query()->where('notification_message_id', $receipt->messageId)
            ->where('channel', DeliveryChannel::Discord)->sole();
        Carbon::setTestNow('2026-09-11T10:01:00Z');
        $dispatch = null;
        if ($digest) {
            app(BuildNotificationDigestDispatches::class)->handle();
            $dispatch = (string) NotificationDigestDispatch::query()->where('notification_endpoint_id', $endpoint)->sole()->id;
        }

        return ['user' => $account->userId, 'player' => $player->playerId, 'endpoint' => $endpoint,
            'route' => (string) $route->id, 'dispatch' => $dispatch];
    }

    /** @param array{user:int,player:string,endpoint:string,route:string,dispatch:?string} $fixture
     * @param  array<string,string>  $configuration
     */
    private function update(array $fixture, array $configuration): void
    {
        app(UpdateNotificationEndpoint::class)->handle($fixture['user'], $fixture['player'], $fixture['endpoint'], 'Updated', $configuration);
    }

    private function process(bool $digest): int
    {
        return $digest ? app(ProcessNotificationDigests::class)->handle(1) : app(ProcessNotificationDeliveries::class)->handle(1);
    }

    /** @param array{user:int,player:string,endpoint:string,route:string,dispatch:?string} $fixture */
    private function assertOutcome(array $fixture, bool $digest, int $status): void
    {
        $expected = $status === 204 ? DeliveryStatus::Sent : DeliveryStatus::Failed;
        self::assertSame($expected, NotificationDelivery::query()->findOrFail($fixture['route'])->status);
        if ($digest) {
            self::assertSame($expected, NotificationDigestDispatch::query()->findOrFail($fixture['dispatch'])->status);
        }
    }
}
