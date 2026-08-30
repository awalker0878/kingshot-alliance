<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Platform\Integrations;

use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookDeliveries;
use App\Contexts\Platform\Integrations\Contracts\WebhookEventCatalog;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;
use UnexpectedValueException;

final class WebhookContractBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_wildcard_subscription_receives_a_catalogued_event_once(): void
    {
        Queue::fake();
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $alliance = $scenarios->alliance($player);

        $issued = app(CreateWebhookSubscription::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            'Operations bot',
            'https://hooks.example.test/kingshot',
            ['*', 'event.created'],
        );

        $subscription = WebhookSubscription::query()->findOrFail($issued->subscriptionId);
        self::assertSame(['*'], $subscription->events);

        $event = new OutboxPublished(
            'message-01',
            $alliance->allianceId,
            'event.created',
            'event',
            'event-01',
            'event:event-01:created',
            ['scope' => 'alliance', 'target_id' => $alliance->allianceId],
            now()->toIso8601String(),
        );

        self::assertSame(1, app(QueueWebhookDeliveries::class)->handle($event));
        self::assertSame(0, app(QueueWebhookDeliveries::class)->handle($event));
        self::assertSame(1, WebhookDelivery::query()->count());
        self::assertSame('event.created', WebhookDelivery::query()->firstOrFail()->event_type);
        self::assertSame('1.0', WebhookDelivery::query()->firstOrFail()->payload['schema_version']);
        Queue::assertPushed(DeliverWebhookJob::class, 1);
    }

    public function test_removed_or_internal_events_are_not_public_contracts(): void
    {
        self::assertFalse(WebhookEventCatalog::isPublic('alliance.created'));
        self::assertFalse(WebhookEventCatalog::isPublic('member.joined'));
        self::assertFalse(WebhookEventCatalog::isPublic('integration.webhook.created'));
        self::assertTrue(WebhookEventCatalog::isPublic('member.updated'));
        self::assertTrue(WebhookEventCatalog::isPublic('member.left'));
        self::assertTrue(WebhookEventCatalog::isPublic('gift_code.status_changed'));
        self::assertTrue(WebhookEventCatalog::isPublic('gift_code.expiry_changed'));
        self::assertTrue(WebhookEventCatalog::isPublic('broadcast.delivery.failed'));

        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $alliance = $scenarios->alliance($player);

        $this->expectException(ValidationException::class);
        app(CreateWebhookSubscription::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            'Dead selector',
            'https://hooks.example.test/kingshot',
            ['member.joined'],
        );
    }

    public function test_global_catalogue_event_is_scoped_to_each_subscriber_alliance(): void
    {
        Queue::fake();
        $scenarios = app(ScenarioFactory::class);
        $firstAccount = $scenarios->account();
        $firstPlayer = $scenarios->player($firstAccount->userId);
        $firstAlliance = $scenarios->alliance($firstPlayer);
        $secondAccount = $scenarios->account();
        $secondPlayer = $scenarios->player($secondAccount->userId);
        $secondAlliance = $scenarios->alliance($secondPlayer);

        app(CreateWebhookSubscription::class)->handle(
            $firstAlliance->allianceId,
            $firstPlayer->playerId,
            'Gift Code listener one',
            'https://hooks.example.test/gift-codes/one',
            ['gift_code.created'],
        );
        app(CreateWebhookSubscription::class)->handle(
            $secondAlliance->allianceId,
            $secondPlayer->playerId,
            'Gift Code listener two',
            'https://hooks.example.test/gift-codes/two',
            ['gift_code.created'],
        );

        $event = new OutboxPublished(
            'message-global-01',
            null,
            'gift_code.created',
            'gift_code',
            'gift-code-01',
            'gift-code:gift-code-01:created',
            [
                'version' => 1,
                'gift_code_id' => 'gift-code-01',
                'code' => 'REALM2026',
                'status' => 'pending',
                'status_revision' => 0,
            ],
            now()->toIso8601String(),
        );

        self::assertSame(2, app(QueueWebhookDeliveries::class)->handle($event));
        self::assertEqualsCanonicalizing(
            [$firstAlliance->allianceId, $secondAlliance->allianceId],
            WebhookDelivery::query()->pluck('alliance_id')->all(),
        );
        self::assertTrue(WebhookDelivery::query()->get()->every(
            static fn (WebhookDelivery $delivery): bool => $delivery->payload['alliance_id'] === $delivery->alliance_id,
        ));
    }

    public function test_public_event_with_an_invalid_payload_fails_closed(): void
    {
        $this->expectException(UnexpectedValueException::class);

        app(QueueWebhookDeliveries::class)->handle(new OutboxPublished(
            'message-invalid-01',
            'alliance-01',
            'member.updated',
            'membership',
            'membership-01',
            'member:membership-01:updated',
            ['member_id' => 'membership-01'],
            now()->toIso8601String(),
        ));
    }
}
