<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Enums\WebhookDeliveryStatus;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookSubscription;
use App\Domain\Integrations\Services\WebhookEndpointPolicy;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class DeliverWebhook
{
    public function __construct(private WebhookEndpointPolicy $endpointPolicy) {}

    public function handle(WebhookDelivery $delivery): void
    {
        if ($delivery->status === WebhookDeliveryStatus::Delivered || $delivery->status === WebhookDeliveryStatus::Failed) {
            return;
        }

        $subscription = $delivery->subscription;
        if (! $subscription instanceof WebhookSubscription || ! $subscription->is_active || $subscription->revoked_at !== null) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'last_error' => 'Webhook subscription is no longer active.',
            ])->save();

            return;
        }

        if (! is_array($delivery->payload)) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'last_error' => 'Webhook payload is unavailable.',
            ])->save();

            return;
        }

        $this->endpointPolicy->assertAllowed((string) $subscription->url);
        $timestamp = (string) now()->getTimestamp();
        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) $subscription->signing_secret);
        $attempts = $delivery->attempts + 1;

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Delivering,
            'attempts' => $attempts,
            'last_attempt_at' => now(),
            'last_error' => null,
        ])->save();

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Kingshot-Alliance-Webhook/1.0',
                    'X-Kingshot-Delivery' => (string) $delivery->id,
                    'X-Kingshot-Event' => (string) $delivery->event_type,
                    'X-Kingshot-Timestamp' => $timestamp,
                    'X-Kingshot-Signature' => 'sha256='.$signature,
                ])
                ->post((string) $subscription->url, $delivery->payload);

            $excerpt = mb_substr($response->body(), 0, 1000);
            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => WebhookDeliveryStatus::Delivered,
                    'delivered_at' => now(),
                    'response_code' => $response->status(),
                    'response_excerpt' => $excerpt,
                    'last_error' => null,
                ])->save();

                return;
            }

            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Pending,
                'available_at' => now()->addSeconds($this->backoffSeconds($attempts)),
                'response_code' => $response->status(),
                'response_excerpt' => $excerpt,
                'last_error' => 'Webhook endpoint returned HTTP '.$response->status().'.',
            ])->save();

            throw new RuntimeException('Webhook endpoint returned HTTP '.$response->status().'.');
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Pending,
                'available_at' => now()->addSeconds($this->backoffSeconds($attempts)),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            throw new RuntimeException('Webhook delivery failed.', previous: $exception);
        }
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
