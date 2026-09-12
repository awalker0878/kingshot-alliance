<?php

declare(strict_types=1);

namespace Tests\Workflows\NotificationDelivery\Feature;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Actions\BuildNotificationDigestDispatches;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDigests;
use App\Contexts\Communications\Delivery\Actions\SetNotificationRoutingPolicy;
use App\Contexts\Communications\Delivery\Contracts\NotificationSourceAuthorization;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Workflows\NotificationDelivery\Services\IntelligenceSignalNotificationPublisher;
use App\Workflows\NotificationDelivery\Services\OfficerBriefNotificationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class QueuedNotificationSourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private int $providerStatus = 204;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10T09:15:00Z');
        Http::preventStrayRequests();
        Http::fake(['discord.com/*' => fn () => Http::response(null, $this->providerStatus, ['Retry-After' => '60'])]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function sourcesAndWorkers(): iterable
    {
        foreach (['officer', 'intelligence'] as $source) {
            foreach ([false, true] as $digest) {
                yield $source.($digest ? ' digest' : ' immediate') => [$source, $digest];
            }
        }
    }

    #[DataProvider('sourcesAndWorkers')]
    public function test_current_authorized_source_reaches_the_real_transport(string $source, bool $digest): void
    {
        $fixture = $this->fixture($digest);
        $route = $this->publish($source, $fixture);
        $this->dispatch($digest);

        self::assertSame(DeliveryStatus::Sent, NotificationDelivery::findOrFail($route)->status);
        Http::assertSentCount(1);
    }

    #[DataProvider('sourcesAndWorkers')]
    public function test_revocation_after_queueing_prevents_provider_io_even_on_account_destinations(string $source, bool $digest): void
    {
        $fixture = $this->fixture($digest);
        $route = $this->publish($source, $fixture);
        AllianceMembership::query()->where('player_id', $fixture['player'])->where('alliance_id', $fixture['alliance'])
            ->update(['status' => MembershipStatus::Suspended->value]);
        $this->dispatch($digest);

        Http::assertNothingSent();
        self::assertSame(DeliveryStatus::Cancelled, NotificationDelivery::findOrFail($route)->status);
        self::assertSame(0, NotificationDelivery::findOrFail($route)->attempt_count);
        self::assertStringContainsString('source', (string) NotificationDelivery::findOrFail($route)->last_error);
    }

    #[DataProvider('sourcesAndWorkers')]
    public function test_account_endpoint_cannot_bypass_original_message_governor_ownership(string $source, bool $digest): void
    {
        $fixture = $this->fixture($digest);
        $route = $this->publish($source, $fixture);
        $other = (new ScenarioFactory)->account();
        Player::query()->whereKey($fixture['player'])->update(['user_id' => $other->userId]);
        $this->dispatch($digest);

        Http::assertNothingSent();
        self::assertSame(DeliveryStatus::Cancelled, NotificationDelivery::findOrFail($route)->status);
        self::assertSame(0, NotificationDelivery::findOrFail($route)->attempt_count);
    }

    public function test_digest_reauthorizes_each_member_without_sending_revoked_officer_content(): void
    {
        $fixture = $this->fixture(true);
        $officer = $this->publish('officer', $fixture);
        $intelligence = $this->publish('intelligence', $fixture);
        AllianceMembership::query()->where('player_id', $fixture['player'])->where('alliance_id', $fixture['alliance'])
            ->update(['rank' => AllianceRank::R1->value]);
        $this->dispatch(true);

        self::assertSame(DeliveryStatus::Cancelled, NotificationDelivery::findOrFail($officer)->status);
        self::assertSame(DeliveryStatus::Sent, NotificationDelivery::findOrFail($intelligence)->status);
        Http::assertSentCount(1);
        Http::assertSent(static fn ($request): bool => str_contains((string) $request['content'], 'Public-to-members observation')
            && ! str_contains((string) $request['content'], 'Daily Officer Brief'));
        self::assertFalse(DB::table('notification_digest_members')->where('notification_delivery_id', $officer)->exists());
    }

    public static function workers(): iterable
    {
        yield 'immediate' => [false];
        yield 'digest' => [true];
    }

    #[DataProvider('workers')]
    public function test_retry_rechecks_source_authority_after_a_provider_failure(bool $digest): void
    {
        $fixture = $this->fixture($digest);
        $route = $this->publish('officer', $fixture);
        $this->providerStatus = 429;
        $this->dispatch($digest);
        Http::assertSentCount(1);
        AllianceMembership::query()->where('player_id', $fixture['player'])->where('alliance_id', $fixture['alliance'])
            ->update(['rank' => AllianceRank::R1->value]);
        Carbon::setTestNow('2026-09-10T12:00:00Z');
        $processed = $digest ? app(ProcessNotificationDigests::class)->handle() : app(ProcessNotificationDeliveries::class)->handle();

        self::assertSame(0, $processed);
        self::assertSame(DeliveryStatus::Cancelled, NotificationDelivery::query()->findOrFail($route)->status);
        Http::assertSentCount(1);
    }

    public function test_source_authorization_infrastructure_failure_is_not_converted_into_success_or_cancellation(): void
    {
        $fixture = $this->fixture(false);
        $route = $this->publish('officer', $fixture);
        $this->app->instance(NotificationSourceAuthorization::class, new class implements NotificationSourceAuthorization
        {
            public function allows(NotificationSource $source): bool
            {
                throw new RuntimeException('Source database unavailable');
            }
        });
        try {
            $this->dispatch(false);
            self::fail('The caller must observe the infrastructure failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Source database unavailable', $exception->getMessage());
        }
        $delivery = NotificationDelivery::query()->findOrFail($route);
        self::assertSame(DeliveryStatus::Queued, $delivery->status);
        self::assertSame(0, $delivery->attempt_count);
        Http::assertNothingSent();
    }

    /** @return array{user:int,player:string,alliance:string} */
    private function fixture(bool $digest): array
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId);
        $alliance = $scenario->alliance($player);
        NotificationEndpoint::query()->create([
            'recipient_user_id' => $account->userId,
            'player_id' => null,
            'channel' => DeliveryChannel::Discord,
            'label' => 'Account destination',
            'configuration' => ['webhook_url' => 'https://discord.com/api/webhooks/123456789/secret-token_value'],
            'enabled' => true,
        ]);
        app(SetNotificationRoutingPolicy::class)->handle(
            recipientUserId: $account->userId, playerId: null, timezone: 'UTC',
            quietHoursEnabled: false, quietHoursStart: null, quietHoursEnd: null,
            allowUrgentDuringQuietHours: false, mutedUntil: null,
            digestCadence: $digest ? DigestCadence::Hourly : DigestCadence::Immediate,
        );

        return ['user' => $account->userId, 'player' => $player->playerId, 'alliance' => $alliance->allianceId];
    }

    /** @param array{user:int,player:string,alliance:string} $fixture */
    private function publish(string $source, array $fixture): string
    {
        $receipt = $source === 'officer'
            ? app(OfficerBriefNotificationPublisher::class)->publish($fixture['user'], $fixture['player'], $fixture['alliance'], [
                'group' => 'daily_officer', 'fingerprint' => hash('sha256', 'private brief'),
                'canonicalUrl' => '/dashboard', 'count' => 1, 'state' => 'needs_attention',
                'owner' => 'read_models.alliance_command',
            ])
            : app(IntelligenceSignalNotificationPublisher::class)->publish($fixture['user'], $fixture['player'], $fixture['alliance'], [
                'type' => 'observation_change', 'subjectType' => 'tracked_alliance', 'subjectId' => 'fixture-subject',
                'summary' => 'Public-to-members observation', 'observedAt' => '2026-09-10T09:00:00Z',
                'sourceClassification' => 'observation', 'sourceOwner' => 'Intelligence/Observations',
                'canonicalUrl' => '/alliance/kingdom-alliances/intelligence',
                'fingerprint' => hash('sha256', 'member observation'), 'ruleVersion' => '1',
            ]);

        return (string) NotificationDelivery::query()->where('notification_message_id', $receipt->messageId)
            ->where('channel', DeliveryChannel::Discord->value)->firstOrFail()->id;
    }

    private function dispatch(bool $digest): void
    {
        Carbon::setTestNow('2026-09-10T10:01:00Z');
        if ($digest) {
            app(BuildNotificationDigestDispatches::class)->handle();
            app(ProcessNotificationDigests::class)->handle();
        } else {
            app(ProcessNotificationDeliveries::class)->handle();
        }
    }
}
