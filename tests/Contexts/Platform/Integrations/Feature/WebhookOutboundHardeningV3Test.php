<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Feature;

use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\DeliverWebhook;
use App\Contexts\Platform\Integrations\Actions\QueueDueWebhookDeliveries;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookTestDelivery;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Exceptions\WebhookAttemptFailed;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Services\WebhookEndpointPolicy;
use App\Contexts\Platform\Integrations\Services\WebhookHostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class WebhookOutboundHardeningV3Test extends TestCase
{
    use RefreshDatabase;

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

        try {
            app(DeliverWebhook::class)->handle((string) $delivery->id);
            self::fail('Redirect response should remain a failed provider attempt.');
        } catch (WebhookAttemptFailed) {
            // Expected: the transport records the response without following it.
        }

        Http::assertSentCount(1);
        $delivery->refresh();
        self::assertSame(WebhookDeliveryStatus::Pending, $delivery->status);
        self::assertSame(302, $delivery->response_code);
        self::assertNotNull($delivery->attempt_token);
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
        self::assertSame(0, $delivery->attempts);
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

    public function test_failed_callback_is_fenced_to_the_exact_transport_attempt(): void
    {
        $delivery = $this->delivery();
        $this->app->instance(WebhookHostResolver::class, $this->resolver(['203.10.20.30']));
        Http::fake(['hooks.example.test/*' => Http::response('', 503)]);

        try {
            app(DeliverWebhook::class)->handle((string) $delivery->id);
            self::fail('The provider failure should throw for queue retry.');
        } catch (WebhookAttemptFailed $failure) {
            $delivery->refresh();
            $delivery->forceFill(['attempt_token' => '00000000-0000-4000-8000-000000000002'])->save();
            (new DeliverWebhookJob((string) $delivery->id))->failed($failure);
            self::assertSame(WebhookDeliveryStatus::Pending, $delivery->fresh()->status);

            $delivery->forceFill(['attempt_token' => $failure->attemptToken])->save();
            (new DeliverWebhookJob((string) $delivery->id))->failed($failure);
            self::assertSame(WebhookDeliveryStatus::Failed, $delivery->fresh()->status);
            self::assertNull($delivery->fresh()->attempt_token);
        }
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
