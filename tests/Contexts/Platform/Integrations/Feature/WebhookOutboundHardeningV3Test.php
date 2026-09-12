<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Feature;

use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\DeliverWebhook;
use App\Contexts\Platform\Integrations\Actions\QueueDueWebhookDeliveries;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookTestDelivery;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Contexts\Platform\Integrations\Services\WebhookEndpointPolicy;
use App\Contexts\Platform\Integrations\Services\WebhookHostResolver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class WebhookOutboundHardeningV3Test extends TestCase
{
    use DatabaseTruncation;

    public function test_dns_answers_must_be_entirely_public_and_the_selected_address_is_pinned(): void
    {
        $public = new WebhookEndpointPolicy($this->resolver(['203.10.20.30', '2001:4860:4860::8888']));
        $resolved = $public->resolveAllowed('https://hooks.example.com/events');
        self::assertSame('hooks.example.com:443:203.10.20.30', $resolved->curlResolution());
        self::assertSame(
            'hooks.example.com:8443:[2001:4860:4860::8888]',
            (new WebhookEndpointPolicy($this->resolver(['2001:4860:4860::8888'])))
                ->resolveAllowed('https://hooks.example.com:8443/events')->curlResolution(),
        );

        foreach ([['127.0.0.1'], ['10.0.0.7'], ['203.10.20.30', '169.254.169.254'], ['2001:db8::1']] as $answers) {
            try {
                (new WebhookEndpointPolicy($this->resolver($answers)))
                    ->resolveAllowed('https://hooks.example.com/events');
                self::fail('A non-public DNS answer was accepted.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('url', $exception->errors());
            }
        }
    }

    public function test_delivery_revalidates_dns_and_never_follows_a_redirect(): void
    {
        $delivery = $this->delivery();
        $this->app->instance(WebhookHostResolver::class, $this->resolver(['203.10.20.30']));
        Http::preventStrayRequests();
        Http::fake(['hooks.example.test/*' => Http::response('', 302, ['Location' => 'https://127.0.0.1/internal'])]);

        app(DeliverWebhook::class)->handle((string) $delivery->id);

        Http::assertSentCount(1);
        $delivery->refresh();
        self::assertSame(WebhookDeliveryStatus::Pending, $delivery->status);
        self::assertSame(302, $delivery->response_code);
        self::assertNull($delivery->attempt_token);
    }

    public function test_private_rebinding_fails_closed_before_transport(): void
    {
        $delivery = $this->delivery();
        $this->app->instance(WebhookHostResolver::class, $this->resolver(['169.254.169.254']));
        Http::preventStrayRequests();

        app(DeliverWebhook::class)->handle((string) $delivery->id);

        Http::assertNothingSent();
        $delivery->refresh();
        self::assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        self::assertSame(1, $delivery->attempts);
        self::assertNull($delivery->attempt_token);
    }

    public function test_future_delivery_cannot_be_claimed_by_an_early_job(): void
    {
        $delivery = $this->delivery();
        $delivery->forceFill(['available_at' => now()->addMinute()])->save();
        Http::preventStrayRequests();

        app(DeliverWebhook::class)->handle((string) $delivery->id);

        Http::assertNothingSent();
        self::assertSame(WebhookDeliveryStatus::Pending, $delivery->fresh()->status);
        self::assertSame(0, $delivery->fresh()->attempts);
    }

    public function test_durable_attempt_budget_exhausts_even_with_new_jobs(): void
    {
        $delivery = $this->delivery();
        $this->app->instance(WebhookHostResolver::class, $this->resolver(['203.10.20.30']));
        Http::fake(['hooks.example.test/*' => Http::response('private-provider-body', 503)]);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $delivery->forceFill(['available_at' => now()])->save();
            (new DeliverWebhookJob((string) $delivery->id))->handle(app(DeliverWebhook::class));
            $delivery->refresh();
            self::assertSame($attempt, $delivery->attempts);
            self::assertSame($attempt === 5 ? WebhookDeliveryStatus::Failed : WebhookDeliveryStatus::Pending, $delivery->status);
            self::assertNull($delivery->response_excerpt);
        }
        (new DeliverWebhookJob((string) $delivery->id))->handle(app(DeliverWebhook::class));
        Http::assertSentCount(5);
    }

    public function test_old_queued_job_cannot_consume_a_replacement_reservation(): void
    {
        $delivery = $this->delivery();
        Queue::fake();
        app(QueueDueWebhookDeliveries::class)->handle(1);
        $oldToken = $delivery->fresh()->attempt_token;
        $delivery->refresh()->forceFill(['status' => WebhookDeliveryStatus::Pending, 'attempt_token' => null])->save();
        app(QueueDueWebhookDeliveries::class)->handle(1);
        $newToken = $delivery->fresh()->attempt_token;
        self::assertNotSame($oldToken, $newToken);
        Http::preventStrayRequests();
        app(DeliverWebhook::class)->handle((string) $delivery->id, $oldToken);
        Http::assertNothingSent();
        self::assertSame($newToken, $delivery->fresh()->attempt_token);
        self::assertSame(0, $delivery->fresh()->attempts);
    }

    public function test_recovery_and_due_fanout_are_bounded_and_rows_are_reserved(): void
    {
        $deliveries = collect(range(1, 8))->map(fn (): WebhookDelivery => $this->delivery());
        Queue::fake();
        foreach ($deliveries->take(4) as $delivery) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Delivering,
                'last_attempt_at' => now()->subMinutes(6),
                'attempt_token' => '00000000-0000-4000-8000-'.str_pad((string) $delivery->attempts, 12, '0', STR_PAD_LEFT),
            ])->save();
        }

        self::assertSame(2, app(QueueDueWebhookDeliveries::class)->handle(2));
        self::assertSame(2, WebhookDelivery::query()->where('status', WebhookDeliveryStatus::Delivering->value)->count());
        self::assertSame(2, WebhookDelivery::query()->where('status', WebhookDeliveryStatus::Queued->value)->count());
        Queue::assertPushed(DeliverWebhookJob::class, 2);

        self::assertSame(2, app(QueueDueWebhookDeliveries::class)->handle(2));
        self::assertSame(0, WebhookDelivery::query()->where('status', WebhookDeliveryStatus::Delivering->value)->count());
        self::assertSame(4, WebhookDelivery::query()->where('status', WebhookDeliveryStatus::Queued->value)->count());
        Queue::assertPushed(DeliverWebhookJob::class, 4);
    }

    public function test_revocation_during_dns_prevents_handoff_and_dns_holds_no_transaction(): void
    {
        $delivery = $this->delivery();
        $this->app->instance(WebhookHostResolver::class, new class((string) $delivery->webhook_subscription_id) extends WebhookHostResolver
        {
            public function __construct(private string $subscriptionId) {}

            public function resolve(string $host): array
            {
                WebhookOutboundHardeningV3Test::assertSame(0, DB::transactionLevel());
                WebhookSubscription::query()->whereKey($this->subscriptionId)->update(['is_active' => false, 'revoked_at' => now()]);

                return ['203.10.20.30'];
            }
        });
        Http::preventStrayRequests();
        app(DeliverWebhook::class)->handle((string) $delivery->id);
        Http::assertNothingSent();
        self::assertSame(WebhookDeliveryStatus::Failed, $delivery->fresh()->status);
        self::assertNull($delivery->fresh()->attempt_token);
    }

    public function test_late_provider_response_cannot_overwrite_a_recovered_attempt(): void
    {
        $delivery = $this->delivery();
        $this->app->instance(WebhookHostResolver::class, $this->resolver(['203.10.20.30']));
        $requests = 0;
        Http::fake(function () use ($delivery, &$requests) {
            self::assertSame(0, DB::transactionLevel());
            $requests++;
            if ($requests === 1) {
                $oldToken = $delivery->fresh()->attempt_token;
                $this->travel(6)->minutes();
                app(QueueDueWebhookDeliveries::class)->handle(1);
                $replacement = $delivery->fresh()->attempt_token;
                self::assertNotSame($oldToken, $replacement);
                app(DeliverWebhook::class)->handle((string) $delivery->id, $replacement);

                return Http::response('old attempt', 500);
            }

            return Http::response('new attempt', 204);
        });
        app(DeliverWebhook::class)->handle((string) $delivery->id);
        self::assertSame(2, $requests);
        self::assertSame(2, $delivery->fresh()->attempts);
        self::assertSame(WebhookDeliveryStatus::Delivered, $delivery->fresh()->status);
        self::assertSame(204, $delivery->fresh()->response_code);
        self::assertNull($delivery->fresh()->attempt_token);
        $this->travelBack();
    }

    /** @param list<string> $answers */
    private function resolver(array $answers): WebhookHostResolver
    {
        return new class($answers) extends WebhookHostResolver
        {
            /** @param list<string> $answers */
            public function __construct(private readonly array $answers) {}

            public function resolve(string $host): array
            {
                return $this->answers;
            }
        };
    }

    private function delivery(): WebhookDelivery
    {
        Queue::fake();
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $alliance = $scenarios->alliance($player);
        $issued = app(CreateWebhookSubscription::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            'Hardened endpoint '.fake()->uuid(),
            'https://hooks.example.test/events',
            ['event.created'],
        );
        $deliveryId = app(QueueWebhookTestDelivery::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            $issued->subscriptionId,
        );

        return WebhookDelivery::query()->findOrFail($deliveryId);
    }
}
