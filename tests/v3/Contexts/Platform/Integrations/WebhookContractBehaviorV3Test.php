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
        Queue::assertPushed(DeliverWebhookJob::class, 1);
    }

    public function test_removed_or_internal_events_are_not_public_contracts(): void
    {
        self::assertFalse(WebhookEventCatalog::isPublic('alliance.created'));
        self::assertFalse(WebhookEventCatalog::isPublic('member.joined'));
        self::assertFalse(WebhookEventCatalog::isPublic('integration.webhook.created'));

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
}
