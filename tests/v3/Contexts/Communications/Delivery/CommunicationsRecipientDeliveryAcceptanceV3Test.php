<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Communications\Delivery;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Communications\Delivery\Actions\BuildNotificationDigestDispatches;
use App\Contexts\Communications\Delivery\Actions\DeleteNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDigests;
use App\Contexts\Communications\Delivery\Actions\QueueNotificationEndpointTest;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\SetNotificationEndpointState;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Actions\SetNotificationRoutingPolicy;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Enums\EndpointHealthStatus;
use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationDigestDispatch;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Communications\Delivery\Queries\NotificationInboxQuery;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\ReadModels\PlatformAdministration\PlatformAdministrationQuery;
use App\ReadModels\ProductionLaunch\ProductionLaunchReadiness;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class CommunicationsRecipientDeliveryAcceptanceV3Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_preferences_and_routing_policy_inherit_with_quiet_hours_urgency_and_temporary_mute(): void
    {
        Carbon::setTestNow('2026-09-04T22:30:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $other = $scenario->account();
        $player = $scenario->player($account->userId, 91001);
        $endpointId = $this->saveDiscordEndpoint($account->userId, $player->playerId, 'Primary Discord', '91001');
        $preferences = app(SetNotificationPreference::class);
        $delivery = app(NotificationDeliveryService::class);

        $preferences->handle($account->userId, null, 'event.reminder', DeliveryChannel::Discord, false);
        $accountDisabled = $delivery->queue($this->intent($account->userId, $player->playerId, 'account-disabled'));
        self::assertSame([DeliveryChannel::InApp->value], $accountDisabled->channels);

        $preferences->handle($account->userId, $player->playerId, 'event.reminder', DeliveryChannel::Discord, true);
        $governorEnabled = $delivery->queue($this->intent($account->userId, $player->playerId, 'governor-enabled'));
        self::assertContains(DeliveryChannel::Discord->value, $governorEnabled->channels);
        $preferences->resetGovernorOverride($account->userId, $player->playerId, 'event.reminder', DeliveryChannel::Discord);
        $inheritedDisabled = $delivery->queue($this->intent($account->userId, $player->playerId, 'inherited-disabled'));
        self::assertSame([DeliveryChannel::InApp->value], $inheritedDisabled->channels);
        $preferences->handle($account->userId, null, 'event.reminder', DeliveryChannel::Discord, true);

        $policies = app(SetNotificationRoutingPolicy::class);
        $policies->handle(
            recipientUserId: $account->userId,
            playerId: null,
            timezone: 'UTC',
            quietHoursEnabled: true,
            quietHoursStart: '22:00',
            quietHoursEnd: '07:00',
            allowUrgentDuringQuietHours: false,
            mutedUntil: null,
            digestCadence: DigestCadence::Immediate,
        );
        $quiet = $delivery->queue($this->intent($account->userId, $player->playerId, 'quiet-normal'));
        $quietRoute = $this->route($quiet->messageId, DeliveryChannel::Discord);
        self::assertSame($endpointId, $quietRoute->notification_endpoint_id);
        self::assertSame('quiet_hours', $quietRoute->routing_reason);
        self::assertSame('2026-09-05T07:00:00+00:00', $quietRoute->due_at->toIso8601String());
        self::assertSame(DeliveryStatus::Sent, $this->route($quiet->messageId, DeliveryChannel::InApp)->status);

        $urgentBlocked = $delivery->queue($this->intent(
            $account->userId,
            $player->playerId,
            'urgent-blocked',
            NotificationUrgency::Urgent,
        ));
        self::assertSame('quiet_hours', $this->route($urgentBlocked->messageId, DeliveryChannel::Discord)->routing_reason);

        $policies->handle(
            recipientUserId: $account->userId,
            playerId: null,
            timezone: 'UTC',
            quietHoursEnabled: true,
            quietHoursStart: '22:00',
            quietHoursEnd: '07:00',
            allowUrgentDuringQuietHours: true,
            mutedUntil: null,
            digestCadence: DigestCadence::Immediate,
        );
        $urgentAllowed = $delivery->queue($this->intent(
            $account->userId,
            $player->playerId,
            'urgent-allowed',
            NotificationUrgency::Urgent,
        ));
        $urgentRoute = $this->route($urgentAllowed->messageId, DeliveryChannel::Discord);
        self::assertSame('immediate', $urgentRoute->routing_reason);
        self::assertSame('2026-09-04T22:30:00+00:00', $urgentRoute->due_at->toIso8601String());

        $policies->handle(
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            timezone: 'UTC',
            quietHoursEnabled: false,
            quietHoursStart: null,
            quietHoursEnd: null,
            allowUrgentDuringQuietHours: false,
            mutedUntil: Carbon::now('UTC')->addHours(2),
            digestCadence: DigestCadence::Immediate,
        );
        $muted = $delivery->queue($this->intent($account->userId, $player->playerId, 'governor-muted'));
        $mutedRoute = $this->route($muted->messageId, DeliveryChannel::Discord);
        self::assertSame('temporary_mute', $mutedRoute->routing_reason);
        self::assertSame('2026-09-05T00:30:00+00:00', $mutedRoute->due_at->toIso8601String());

        $policies->resetGovernorOverride($account->userId, $player->playerId);
        $inheritedQuiet = $delivery->queue($this->intent($account->userId, $player->playerId, 'governor-reset'));
        self::assertSame('quiet_hours', $this->route($inheritedQuiet->messageId, DeliveryChannel::Discord)->routing_reason);

        try {
            $policies->handle(
                recipientUserId: $other->userId,
                playerId: $player->playerId,
                timezone: 'UTC',
                quietHoursEnabled: false,
                quietHoursStart: null,
                quietHoursEnd: null,
                allowUrgentDuringQuietHours: false,
                mutedUntil: null,
                digestCadence: DigestCadence::Immediate,
            );
            self::fail('Routing policy writes must recheck current Governor ownership.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
    }

    public function test_multiple_named_endpoints_have_independent_health_lifecycle_and_audit_evidence(): void
    {
        Carbon::setTestNow('2026-09-04T15:00:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId, 91002);
        $firstId = $this->saveDiscordEndpoint($account->userId, $player->playerId, 'Officer alerts', '91002');
        $secondId = $this->saveDiscordEndpoint($account->userId, $player->playerId, 'Personal alerts', '91003');

        $receipt = app(NotificationDeliveryService::class)->queue($this->intent(
            $account->userId,
            $player->playerId,
            'multi-endpoint',
            availableAt: Carbon::now('UTC')->addHour(),
        ));
        $external = NotificationDelivery::query()
            ->where('notification_message_id', $receipt->messageId)
            ->where('channel', DeliveryChannel::Discord->value)
            ->get();
        self::assertCount(2, $external);
        self::assertEqualsCanonicalizing([$firstId, $secondId], $external->pluck('notification_endpoint_id')->all());

        Http::fake([
            'discord.com/*' => Http::sequence()
                ->push(null, 204)
                ->push([], 429, ['Retry-After' => '60']),
        ]);
        $tests = app(QueueNotificationEndpointTest::class);
        $firstTestId = $tests->handle($account->userId, $player->playerId, $firstId);
        self::assertSame(1, app(ProcessNotificationDeliveries::class)->handle());
        self::assertSame(DeliveryStatus::Sent, NotificationDelivery::query()->findOrFail($firstTestId)->status);
        $firstEndpoint = NotificationEndpoint::query()->findOrFail($firstId);
        self::assertSame(EndpointHealthStatus::Healthy, $firstEndpoint->health_status);
        self::assertNotNull($firstEndpoint->last_verified_at);

        $failedTestId = $tests->handle($account->userId, $player->playerId, $firstId);
        self::assertSame(1, app(ProcessNotificationDeliveries::class)->handle());
        $failedTest = NotificationDelivery::query()->findOrFail($failedTestId);
        self::assertSame(DeliveryStatus::Failed, $failedTest->status);
        self::assertNotNull($failedTest->next_attempt_at);
        $firstEndpoint->refresh();
        self::assertSame(EndpointHealthStatus::Degraded, $firstEndpoint->health_status);
        self::assertTrue($firstEndpoint->enabled);

        $state = app(SetNotificationEndpointState::class);
        $state->handle($account->userId, $player->playerId, $firstId, false);
        $firstEndpoint->refresh();
        self::assertFalse($firstEndpoint->enabled);
        self::assertSame(EndpointHealthStatus::Paused, $firstEndpoint->health_status);
        try {
            $tests->handle($account->userId, $player->playerId, $firstId);
            self::fail('Paused endpoints must not accept test deliveries.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $state->handle($account->userId, $player->playerId, $firstId, true);
        self::assertSame(EndpointHealthStatus::NeverTested, NotificationEndpoint::query()->findOrFail($firstId)->health_status);
        $tests->handle($account->userId, $player->playerId, $firstId);
        app(DeleteNotificationEndpoint::class)->handle($account->userId, $player->playerId, $firstId);
        self::assertNull(NotificationEndpoint::query()->find($firstId));
        self::assertNotNull(NotificationEndpoint::query()->find($secondId));

        foreach ([
            'notification.endpoint.saved',
            'notification.endpoint.test_queued',
            'notification.endpoint.paused',
            'notification.endpoint.resumed',
            'notification.endpoint.deleted',
        ] as $event) {
            self::assertTrue(AuditEvent::query()->where('event', $event)->exists(), $event.' audit evidence is missing.');
        }
    }

    public function test_web_push_rejects_unsafe_destinations_supports_multiple_devices_and_exhausts_stale_subscriptions(): void
    {
        Carbon::setTestNow('2026-09-04T16:00:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId, 91003);
        $material = $this->webPushSubscriptionMaterial();

        try {
            app(SaveNotificationEndpoint::class)->handle(
                $account->userId,
                $player->playerId,
                DeliveryChannel::WebPush,
                'Unsafe browser',
                [
                    'endpoint' => 'https://127.0.0.1/push',
                    'p256dh' => $material['p256dh'],
                    'auth' => $material['auth'],
                ],
            );
            self::fail('Web Push must reject private or unapproved push-service hosts.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $save = app(SaveNotificationEndpoint::class);
        $firstId = $save->handle(
            $account->userId,
            $player->playerId,
            DeliveryChannel::WebPush,
            'Chrome desktop',
            [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-a',
                'p256dh' => $material['p256dh'],
                'auth' => $material['auth'],
            ],
        );
        $secondMaterial = $this->webPushSubscriptionMaterial();
        $secondId = $save->handle(
            $account->userId,
            $player->playerId,
            DeliveryChannel::WebPush,
            'Phone browser',
            [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-b',
                'p256dh' => $secondMaterial['p256dh'],
                'auth' => $secondMaterial['auth'],
            ],
        );
        $vapid = $this->vapidConfiguration();
        config()->set('services.webpush.public_key', $vapid['public']);
        config()->set('services.webpush.private_key', $vapid['private']);
        config()->set('services.webpush.subject', $vapid['subject']);

        $receipt = app(NotificationDeliveryService::class)->queue($this->intent(
            $account->userId,
            $player->playerId,
            'web-push-stale',
            body: str_repeat('bounded payload ', 500),
        ));
        $pushRoutes = NotificationDelivery::query()
            ->where('notification_message_id', $receipt->messageId)
            ->where('channel', DeliveryChannel::WebPush->value)
            ->get();
        self::assertCount(2, $pushRoutes);
        self::assertEqualsCanonicalizing([$firstId, $secondId], $pushRoutes->pluck('notification_endpoint_id')->all());

        Http::fake(['fcm.googleapis.com/*' => Http::response('', 410)]);
        self::assertSame(2, app(ProcessNotificationDeliveries::class)->handle());
        $pushRoutes->each(function (NotificationDelivery $route): void {
            $route->refresh();
            self::assertSame(DeliveryStatus::Failed, $route->status);
            self::assertNull($route->next_attempt_at);
            self::assertSame(1, $route->attempt_count);
        });
        self::assertSame(2, NotificationEndpoint::query()
            ->whereIn('id', [$firstId, $secondId])
            ->where('health_status', EndpointHealthStatus::Degraded->value)
            ->where('enabled', true)
            ->count());
        Http::assertSent(static fn ($request): bool => strlen($request->body()) < 4096);
    }

    public function test_notification_center_pages_logical_messages_once_with_cursor_filters_and_route_details(): void
    {
        Carbon::setTestNow('2026-09-04T17:00:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId, 91004);
        $this->saveDiscordEndpoint($account->userId, $player->playerId, 'First route', '91004');
        $this->saveDiscordEndpoint($account->userId, $player->playerId, 'Second route', '91005');
        $delivery = app(NotificationDeliveryService::class);

        $governorOne = $delivery->queue($this->intent($account->userId, $player->playerId, 'inbox-one'));
        Carbon::setTestNow('2026-09-04T17:00:01Z');
        $governorTwo = $delivery->queue($this->intent($account->userId, $player->playerId, 'inbox-two'));
        Carbon::setTestNow('2026-09-04T17:00:02Z');
        $accountOnly = $delivery->queue(NotificationIntent::fromScalars(
            notificationType: 'account.security',
            recipientUserId: $account->userId,
            playerId: null,
            availableAt: Carbon::now('UTC'),
            idempotencyKey: 'inbox-account',
            title: 'Security notice',
            actionUrl: '/settings/security',
        ));

        $inbox = app(NotificationInboxQuery::class);
        $firstPage = $inbox->handle($account->userId, $player->playerId, ['limit' => 2]);
        self::assertCount(2, $firstPage['items']);
        self::assertTrue($firstPage['hasMore']);
        self::assertNotNull($firstPage['nextCursor']);
        self::assertSame(2, count(array_unique(array_column($firstPage['items'], 'id'))));

        $secondPage = $inbox->handle($account->userId, $player->playerId, [
            'limit' => 2,
            'cursor' => $firstPage['nextCursor'],
        ]);
        self::assertCount(1, $secondPage['items']);
        self::assertFalse($secondPage['hasMore']);
        $allIds = array_merge(array_column($firstPage['items'], 'id'), array_column($secondPage['items'], 'id'));
        self::assertEqualsCanonicalizing(
            [$governorOne->messageId, $governorTwo->messageId, $accountOnly->messageId],
            $allIds,
        );

        $governor = $inbox->handle($account->userId, $player->playerId, [
            'scope' => NotificationInboxQuery::SCOPE_GOVERNOR,
            'type' => 'event.reminder',
            'limit' => 10,
        ]);
        self::assertCount(2, $governor['items']);
        foreach ($governor['items'] as $item) {
            self::assertSame(3, $item['deliverySummary']['total']);
            self::assertCount(3, $item['deliveries']);
            self::assertSame('governor', $item['scope']);
        }

        $accountScope = $inbox->handle($account->userId, $player->playerId, [
            'scope' => NotificationInboxQuery::SCOPE_ACCOUNT,
            'type' => 'account.security',
        ]);
        self::assertSame([$accountOnly->messageId], array_column($accountScope['items'], 'id'));
    }

    public function test_digest_dispatches_are_idempotent_retryable_and_do_not_advance_the_due_window(): void
    {
        Carbon::setTestNow('2026-09-04T10:15:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId, 91005);
        $this->saveDiscordEndpoint($account->userId, $player->playerId, 'Digest route', '91006');
        app(SetNotificationRoutingPolicy::class)->handle(
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            timezone: 'UTC',
            quietHoursEnabled: false,
            quietHoursStart: null,
            quietHoursEnd: null,
            allowUrgentDuringQuietHours: false,
            mutedUntil: null,
            digestCadence: DigestCadence::Hourly,
        );
        $delivery = app(NotificationDeliveryService::class);
        $first = $delivery->queue($this->intent($account->userId, $player->playerId, 'digest-one'));
        $second = $delivery->queue($this->intent($account->userId, $player->playerId, 'digest-two'));
        self::assertSame('2026-09-04T11:00:00+00:00', $this->route($first->messageId, DeliveryChannel::Discord)->due_at->toIso8601String());
        self::assertSame('2026-09-04T11:00:00+00:00', $this->route($second->messageId, DeliveryChannel::Discord)->due_at->toIso8601String());

        Carbon::setTestNow('2026-09-04T11:01:00Z');
        $builder = app(BuildNotificationDigestDispatches::class);
        self::assertSame(1, $builder->handle());
        self::assertSame(0, $builder->handle());
        $dispatch = NotificationDigestDispatch::query()->firstOrFail();
        self::assertSame(2, DB::table('notification_digest_members')
            ->where('notification_digest_dispatch_id', $dispatch->id)
            ->count());
        self::assertSame(2, NotificationMessage::query()->count());

        Http::fake([
            'discord.com/*' => Http::sequence()
                ->push(['error' => 'temporary'], 500)
                ->push(null, 204),
        ]);
        self::assertSame(1, app(ProcessNotificationDigests::class)->handle());
        $dispatch->refresh();
        self::assertSame(DeliveryStatus::Failed, $dispatch->status);
        self::assertNotNull($dispatch->next_attempt_at);
        self::assertSame(2, NotificationDelivery::query()
            ->where('channel', DeliveryChannel::Discord->value)
            ->where('status', DeliveryStatus::Queued->value)
            ->count());

        Carbon::setTestNow('2026-09-04T11:02:00Z');
        self::assertSame(1, app(ProcessNotificationDigests::class)->handle());
        $dispatch->refresh();
        self::assertSame(DeliveryStatus::Sent, $dispatch->status);
        self::assertSame(2, NotificationDelivery::query()
            ->where('channel', DeliveryChannel::Discord->value)
            ->where('status', DeliveryStatus::Sent->value)
            ->count());
        self::assertSame(0, app(ProcessNotificationDigests::class)->handle());
    }

    public function test_digest_builder_caps_each_dispatch_at_twenty_routes(): void
    {
        Carbon::setTestNow('2026-09-04T10:15:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId, 91006);
        $this->saveDiscordEndpoint($account->userId, $player->playerId, 'Bounded digest', '91007');
        app(SetNotificationRoutingPolicy::class)->handle(
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            timezone: 'UTC',
            quietHoursEnabled: false,
            quietHoursStart: null,
            quietHoursEnd: null,
            allowUrgentDuringQuietHours: false,
            mutedUntil: null,
            digestCadence: DigestCadence::Hourly,
        );
        $delivery = app(NotificationDeliveryService::class);
        for ($index = 0; $index < 21; $index++) {
            $delivery->queue($this->intent($account->userId, $player->playerId, 'bounded-digest-'.$index));
        }

        Carbon::setTestNow('2026-09-04T11:01:00Z');
        self::assertSame(2, app(BuildNotificationDigestDispatches::class)->handle());
        self::assertSame(2, NotificationDigestDispatch::query()->count());
        $memberCounts = DB::table('notification_digest_members')
            ->selectRaw('notification_digest_dispatch_id, count(*) as members')
            ->groupBy('notification_digest_dispatch_id')
            ->pluck('members')
            ->map(static fn ($count): int => (int) $count)
            ->all();
        self::assertSame(21, array_sum($memberCounts));
        self::assertLessThanOrEqual(20, max($memberCounts));
        self::assertSame(0, app(BuildNotificationDigestDispatches::class)->handle());
    }

    public function test_email_routes_require_verified_accounts_and_recheck_verification_before_send(): void
    {
        Carbon::setTestNow('2026-09-04T18:00:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId, 91007);
        app(SetNotificationPreference::class)->handle(
            $account->userId,
            null,
            'event.reminder',
            DeliveryChannel::Email,
            true,
        );
        $delivery = app(NotificationDeliveryService::class);

        User::query()->whereKey($account->userId)->update(['email_verified_at' => null]);
        $unverified = $delivery->queue($this->intent($account->userId, $player->playerId, 'email-unverified'));
        self::assertNotContains(DeliveryChannel::Email->value, $unverified->channels);

        User::query()->whereKey($account->userId)->update(['email_verified_at' => now()]);
        $verified = $delivery->queue($this->intent($account->userId, $player->playerId, 'email-verified'));
        self::assertContains(DeliveryChannel::Email->value, $verified->channels);
        $verifiedRoute = $this->route($verified->messageId, DeliveryChannel::Email);
        self::assertNull($verifiedRoute->notification_endpoint_id);
        self::assertSame('Verified account email', $verifiedRoute->route_target_label);

        User::query()->whereKey($account->userId)->update(['email_verified_at' => null]);
        self::assertSame(0, app(ProcessNotificationDeliveries::class)->handle());
        $verifiedRoute->refresh();
        self::assertSame(DeliveryStatus::Cancelled, $verifiedRoute->status);

        User::query()->whereKey($account->userId)->update(['email_verified_at' => now()]);
        config()->set('mail.default', 'array');
        $sendable = $delivery->queue($this->intent($account->userId, $player->playerId, 'email-sendable'));
        self::assertSame(1, app(ProcessNotificationDeliveries::class)->handle());
        self::assertSame(DeliveryStatus::Sent, $this->route($sendable->messageId, DeliveryChannel::Email)->status);
    }

    public function test_delivery_worker_rechecks_preferences_and_current_governor_ownership(): void
    {
        Carbon::setTestNow('2026-09-04T19:00:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $other = $scenario->account();
        $player = $scenario->player($account->userId, 91008);
        $this->saveDiscordEndpoint($account->userId, $player->playerId, 'Recheck route', '91008');
        $delivery = app(NotificationDeliveryService::class);
        $preferences = app(SetNotificationPreference::class);

        $disabledLater = $delivery->queue($this->intent($account->userId, $player->playerId, 'policy-recheck'));
        $preferences->handle($account->userId, $player->playerId, 'event.reminder', DeliveryChannel::Discord, false);
        self::assertSame(0, app(ProcessNotificationDeliveries::class)->handle());
        self::assertSame(DeliveryStatus::Cancelled, $this->route($disabledLater->messageId, DeliveryChannel::Discord)->status);

        $preferences->handle($account->userId, $player->playerId, 'event.reminder', DeliveryChannel::Discord, true);
        $revokedOwner = $delivery->queue($this->intent($account->userId, $player->playerId, 'owner-recheck'));
        DB::table('players')->where('id', $player->playerId)->update(['user_id' => $other->userId]);
        self::assertSame(0, app(ProcessNotificationDeliveries::class)->handle());
        self::assertSame(DeliveryStatus::Cancelled, $this->route($revokedOwner->messageId, DeliveryChannel::Discord)->status);
        self::assertSame(0, app(ProcessNotificationDeliveries::class)->handle());
    }

    public function test_action_urls_diagnostics_and_launch_checks_are_privacy_safe_and_channel_aware(): void
    {
        Carbon::setTestNow('2026-09-04T20:00:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $player = $scenario->player($account->userId, 91009);
        $delivery = app(NotificationDeliveryService::class);

        try {
            $delivery->queue(NotificationIntent::fromScalars(
                notificationType: 'event.reminder',
                recipientUserId: $account->userId,
                playerId: $player->playerId,
                availableAt: Carbon::now('UTC'),
                idempotencyKey: 'unsafe-action-url',
                title: 'Unsafe',
                actionUrl: 'https://evil.example.test/phish',
            ));
            self::fail('External action URLs must not cross the application-relative handoff boundary.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $this->saveDiscordEndpoint($account->userId, $player->playerId, 'Diagnostic route', '91009');
        $failed = $delivery->queue($this->intent($account->userId, $player->playerId, 'diagnostic-failure'));
        Http::fake(['discord.com/*' => Http::response(['secret' => 'provider-body'], 500)]);
        self::assertSame(1, app(ProcessNotificationDeliveries::class)->handle());
        self::assertSame(DeliveryStatus::Failed, $this->route($failed->messageId, DeliveryChannel::Discord)->status);

        $dashboard = app(PlatformAdministrationQuery::class)->dashboard();
        $failure = $dashboard['diagnostics']['notificationFailures'][0] ?? null;
        self::assertIsArray($failure);
        self::assertSame('event.reminder', $failure['notificationType'] ?? null);
        self::assertNotNull($failure['errorFingerprint'] ?? null);
        self::assertArrayNotHasKey('recipientUserId', $failure);
        self::assertArrayNotHasKey('body', $failure);
        self::assertArrayNotHasKey('lastError', $failure);

        $checks = collect(app(ProductionLaunchReadiness::class)->checks())->keyBy('key');
        self::assertTrue($checks->get('notification_delivery_schedule')['passed'] ?? false);

        $pushMaterial = $this->webPushSubscriptionMaterial();
        app(SaveNotificationEndpoint::class)->handle(
            $account->userId,
            $player->playerId,
            DeliveryChannel::WebPush,
            'Readiness browser',
            [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/readiness',
                'p256dh' => $pushMaterial['p256dh'],
                'auth' => $pushMaterial['auth'],
            ],
        );
        config()->set('services.webpush.public_key', null);
        config()->set('services.webpush.private_key', null);
        config()->set('services.webpush.subject', null);
        $checks = collect(app(ProductionLaunchReadiness::class)->checks())->keyBy('key');
        self::assertFalse($checks->get('notification_web_push_configuration')['passed'] ?? true);
        $vapid = $this->vapidConfiguration();
        config()->set('services.webpush.public_key', $vapid['public']);
        config()->set('services.webpush.private_key', $vapid['private']);
        config()->set('services.webpush.subject', $vapid['subject']);
        $checks = collect(app(ProductionLaunchReadiness::class)->checks())->keyBy('key');
        self::assertTrue($checks->get('notification_web_push_configuration')['passed'] ?? false);

        app(SetNotificationPreference::class)->handle($account->userId, null, 'event.reminder', DeliveryChannel::Email, true);
        config()->set('mail.default', 'array');
        $checks = collect(app(ProductionLaunchReadiness::class)->checks())->keyBy('key');
        self::assertFalse($checks->get('notification_email_configuration')['passed'] ?? true);
        config()->set('mail.default', 'smtp');
        config()->set('mail.from.address', 'notifications@example.test');
        $checks = collect(app(ProductionLaunchReadiness::class)->checks())->keyBy('key');
        self::assertTrue($checks->get('notification_email_configuration')['passed'] ?? false);
    }

    private function saveDiscordEndpoint(int $userId, string $playerId, string $label, string $id): string
    {
        return app(SaveNotificationEndpoint::class)->handle(
            $userId,
            $playerId,
            DeliveryChannel::Discord,
            $label,
            ['webhook_url' => 'https://discord.com/api/webhooks/'.$id.'/abcdefghijklmnopqrstuvwxyz_ABCDEFGHIJKLMNOPQRSTUVWXYZ'],
        );
    }

    private function intent(
        int $userId,
        string $playerId,
        string $key,
        NotificationUrgency $urgency = NotificationUrgency::Normal,
        ?Carbon $availableAt = null,
        ?string $body = 'Acceptance delivery body.',
    ): NotificationIntent {
        return NotificationIntent::fromScalars(
            notificationType: 'event.reminder',
            recipientUserId: $userId,
            playerId: $playerId,
            availableAt: $availableAt ?? Carbon::now('UTC'),
            idempotencyKey: $key,
            title: 'Acceptance notification '.$key,
            body: $body,
            actionUrl: '/events',
            urgency: $urgency,
        );
    }

    private function route(string $messageId, DeliveryChannel $channel): NotificationDelivery
    {
        return NotificationDelivery::query()
            ->where('notification_message_id', $messageId)
            ->where('channel', $channel->value)
            ->firstOrFail();
    }

    /** @return array{p256dh:string,auth:string} */
    private function webPushSubscriptionMaterial(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('Unable to create test Web Push key.');
        }
        $details = openssl_pkey_get_details($key);
        $x = is_array($details) && is_array($details['ec'] ?? null) ? ($details['ec']['x'] ?? null) : null;
        $y = is_array($details) && is_array($details['ec'] ?? null) ? ($details['ec']['y'] ?? null) : null;
        if (! is_string($x) || ! is_string($y)) {
            throw new RuntimeException('Unable to export test Web Push public key.');
        }
        $public = "\x04".str_pad($x, 32, "\0", STR_PAD_LEFT).str_pad($y, 32, "\0", STR_PAD_LEFT);

        return [
            'p256dh' => $this->base64Url($public),
            'auth' => $this->base64Url(random_bytes(16)),
        ];
    }

    /** @return array{public:string,private:string,subject:string} */
    private function vapidConfiguration(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('Unable to create test VAPID key.');
        }
        $details = openssl_pkey_get_details($key);
        $x = is_array($details) && is_array($details['ec'] ?? null) ? ($details['ec']['x'] ?? null) : null;
        $y = is_array($details) && is_array($details['ec'] ?? null) ? ($details['ec']['y'] ?? null) : null;
        $private = '';
        if (! is_string($x) || ! is_string($y) || ! openssl_pkey_export($key, $private)) {
            throw new RuntimeException('Unable to export test VAPID key.');
        }
        $public = "\x04".str_pad($x, 32, "\0", STR_PAD_LEFT).str_pad($y, 32, "\0", STR_PAD_LEFT);

        return [
            'public' => $this->base64Url($public),
            'private' => $private,
            'subject' => 'mailto:notifications@example.test',
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
