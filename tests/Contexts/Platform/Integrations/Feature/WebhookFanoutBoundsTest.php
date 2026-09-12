<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Feature;

use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\ProcessWebhookFanouts;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookDeliveries;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookFanout;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;
use UnexpectedValueException;

final class WebhookFanoutBoundsTest extends TestCase
{
    use DatabaseTruncation;

    public function test_global_fanout_resumes_in_bounded_pages_and_rechecks_recipients(): void
    {
        Queue::fake();
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        $issued = app(CreateWebhookSubscription::class)->handle($alliance->allianceId, $player->playerId,
            'Global', 'https://hooks.example.com/events', ['gift_code.created']);
        $original = WebhookSubscription::query()->findOrFail($issued->subscriptionId);
        for ($i = 1; $i < 63; $i++) {
            $copy = $original->replicate();
            $copy->save();
        }
        $loaded = 0;
        WebhookSubscription::retrieved(static function () use (&$loaded): void {
            $loaded++;
        });
        $event = new OutboxPublished('fanout-global', null, 'gift_code.created', 'gift_code', 'code-id', 'fanout-global',
            ['version' => 1, 'gift_code_id' => 'code-id', 'code' => 'BOUND2026', 'status' => 'pending', 'status_revision' => 0], now()->toIso8601String());
        self::assertSame(25, app(QueueWebhookDeliveries::class)->handle($event));
        self::assertSame(25, $loaded);
        self::assertSame(25, WebhookDelivery::query()->count());
        $checkpoint = WebhookFanout::query()->firstOrFail();
        self::assertNull($checkpoint->completed_at);
        $revokedId = WebhookSubscription::query()->where('id', '>', $checkpoint->after_subscription_id)->orderBy('id')->value('id');
        WebhookSubscription::query()->whereKey($revokedId)->update(['revoked_at' => now(), 'is_active' => false]);
        $loaded = 0;
        self::assertSame(24, app(ProcessWebhookFanouts::class)->handle(25));
        self::assertSame(25, $loaded);
        self::assertSame(13, app(ProcessWebhookFanouts::class)->handle(25));
        self::assertSame(62, WebhookDelivery::query()->count());
        self::assertNotNull($checkpoint->fresh()->completed_at);
        self::assertNull($checkpoint->fresh()->payload);
        self::assertSame(0, app(QueueWebhookDeliveries::class)->handle($event));
        self::assertSame(62, WebhookDelivery::query()->count());
    }

    public function test_reusing_a_source_identity_with_different_facts_is_rejected(): void
    {
        $event = new OutboxPublished('conflict', null, 'gift_code.created', 'gift_code', 'code-id', 'conflict',
            ['version' => 1, 'gift_code_id' => 'code-id', 'code' => 'BOUND2026', 'status' => 'pending', 'status_revision' => 0], now()->toIso8601String());
        app(QueueWebhookDeliveries::class)->handle($event);
        $changed = new OutboxPublished('conflict', null, 'gift_code.created', 'gift_code', 'code-id', 'conflict',
            [...$event->payload, 'code' => 'CHANGED'], $event->occurredAt);
        $this->expectException(UnexpectedValueException::class);
        app(QueueWebhookDeliveries::class)->handle($changed);
    }
}
