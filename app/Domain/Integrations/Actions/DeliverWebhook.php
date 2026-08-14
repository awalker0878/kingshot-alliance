<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Enums\WebhookDeliveryStatus;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookSubscription;
use App\Domain\Integrations\Services\WebhookEndpointPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class DeliverWebhook
{
    public function __construct(private WebhookEndpointPolicy $endpointPolicy) {}

    public function handle(WebhookDelivery $delivery): void
    {
        $claim = DB::transaction(function () use ($delivery): ?array {
            $locked = WebhookDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if ($locked->status !== WebhookDeliveryStatus::Pending) {
                return null;
            }

            $subscription = WebhookSubscription::query()
                ->lockForUpdate()
                ->find($locked->webhook_subscription_id);
            if (! $subscription instanceof WebhookSubscription || ! $subscription->is_active || $subscription->revoked_at !== null) {
                $locked->forceFill([
                    'status' => WebhookDeliveryStatus::Failed,
                    'last_error' => 'Webhook subscription is no longer active.',
                ])->save();

                return null;
            }

            $payload = $locked->payload;
            if (! is_array($payload)) {
                $locked->forceFill([
                    'status' => WebhookDeliveryStatus::Failed,
                    'last_error' => 'Webhook payload is unavailable.',
                ])->save();

                return null;
            }

            /** @var array<string, mixed> $payload */
            $this->endpointPolicy->assertAllowed((string) $subscription->url);
            $attempts = $locked->attempts + 1;
            $locked->forceFill([
                'status' => WebhookDeliveryStatus::Delivering,
                'attempts' => $attempts,
                'last_attempt_at' => now(),
                'last_error' => null,
            ])->save();

            return [
                'delivery_id' => (string) $locked->id,
                'event_type' => (string) $locked->event_type,
                'url' => (string) $subscription->url,
                'signing_secret' => (string) $subscription->signing_secret,
                'payload' => $payload,
                'attempts' => $attempts,
            ];
        });

        if ($claim === null) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = $claim['payload'];
        $timestamp = (string) now()->getTimestamp();
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) $claim['signing_secret']);
        $attempts = (int) $claim['attempts'];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Kingshot-Alliance-Webhook/1.0',
                    'X-Kingshot-Delivery' => (string) $claim['delivery_id'],
                    'X-Kingshot-Event' => (string) $claim['event_type'],
                    'X-Kingshot-Timestamp' => $timestamp,
                    'X-Kingshot-Signature' => 'sha256='.$signature,
                ])
                ->post((string) $claim['url'], $payload);

            $excerpt = mb_substr($response->body(), 0, 1000);
            if ($response->successful()) {
                $this->finishAttempt((string) $claim['delivery_id'], $attempts, [
                    'status' => WebhookDeliveryStatus::Delivered,
                    'delivered_at' => now(),
                    'response_code' => $response->status(),
                    'response_excerpt' => $excerpt,
                    'last_error' => null,
                ]);

                return;
            }

            $this->finishAttempt((string) $claim['delivery_id'], $attempts, [
                'status' => WebhookDeliveryStatus::Pending,
                'available_at' => now()->addSeconds($this->backoffSeconds($attempts)),
                'response_code' => $response->status(),
                'response_excerpt' => $excerpt,
                'last_error' => 'Webhook endpoint returned HTTP '.$response->status().'.',
            ]);

            throw new RuntimeException('Webhook endpoint returned HTTP '.$response->status().'.');
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->finishAttempt((string) $claim['delivery_id'], $attempts, [
                'status' => WebhookDeliveryStatus::Pending,
                'available_at' => now()->addSeconds($this->backoffSeconds($attempts)),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            throw new RuntimeException('Webhook delivery failed.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function finishAttempt(string $deliveryId, int $attempts, array $attributes): void
    {
        DB::transaction(function () use ($deliveryId, $attempts, $attributes): void {
            $locked = WebhookDelivery::query()->lockForUpdate()->findOrFail($deliveryId);
            if ($locked->status !== WebhookDeliveryStatus::Delivering || $locked->attempts !== $attempts) {
                return;
            }

            $locked->forceFill($attributes)->save();
        });
    }

    private function backoffSeconds(int $attempt): int
    {
        return match (true) {
            $attempt <= 1 => 60,
            $attempt === 2 => 300,
            $attempt === 3 => 1800,
            default => 7200,
        };
    }
}
