<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Exceptions\WebhookAttemptFailed;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Contexts\Platform\Integrations\Services\WebhookEndpointPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class DeliverWebhook
{
    public function __construct(private WebhookEndpointPolicy $endpointPolicy) {}

    public function handle(string $deliveryId): void
    {
        $delivery = WebhookDelivery::query()->whereKey($deliveryId)->firstOrFail();

        $claim = DB::transaction(function () use ($delivery): ?array {
            $locked = WebhookDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if (! in_array($locked->status, [WebhookDeliveryStatus::Pending, WebhookDeliveryStatus::Queued], true)
                || $locked->available_at->isFuture()) {
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
            try {
                $endpoint = $this->endpointPolicy->resolveAllowed((string) $subscription->url);
            } catch (ValidationException) {
                $locked->forceFill([
                    'status' => WebhookDeliveryStatus::Failed,
                    'attempt_token' => null,
                    'last_error' => 'Webhook destination failed the current outbound security policy.',
                ])->save();

                return null;
            }
            $attempts = $locked->attempts + 1;
            $attemptToken = (string) Str::uuid();
            $locked->forceFill([
                'status' => WebhookDeliveryStatus::Delivering,
                'attempts' => $attempts,
                'attempt_token' => $attemptToken,
                'last_attempt_at' => now(),
                'last_error' => null,
            ])->save();

            return [
                'delivery_id' => (string) $locked->id,
                'event_type' => (string) $locked->event_type,
                'url' => (string) $subscription->url,
                'curl_resolution' => $endpoint->curlResolution(),
                'signing_secret' => (string) $subscription->signing_secret,
                'payload' => $payload,
                'attempts' => $attempts,
                'attempt_token' => $attemptToken,
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
                ->withoutRedirecting()
                ->withOptions([
                    'verify' => true,
                    'curl' => [
                        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                        CURLOPT_RESOLVE => [(string) $claim['curl_resolution']],
                    ],
                ])
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
                $this->finishAttempt((string) $claim['delivery_id'], (string) $claim['attempt_token'], [
                    'status' => WebhookDeliveryStatus::Delivered,
                    'delivered_at' => now(),
                    'response_code' => $response->status(),
                    'response_excerpt' => $excerpt,
                    'last_error' => null,
                    'attempt_token' => null,
                ]);

                return;
            }

            $this->finishAttempt((string) $claim['delivery_id'], (string) $claim['attempt_token'], [
                'status' => WebhookDeliveryStatus::Pending,
                'available_at' => now()->addSeconds($this->backoffSeconds($attempts)),
                'response_code' => $response->status(),
                'response_excerpt' => $excerpt,
                'last_error' => 'Webhook endpoint returned HTTP '.$response->status().'.',
            ]);

            throw new WebhookAttemptFailed((string) $claim['delivery_id'], (string) $claim['attempt_token'], 'Webhook endpoint returned HTTP '.$response->status().'.');
        } catch (WebhookAttemptFailed $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->finishAttempt((string) $claim['delivery_id'], (string) $claim['attempt_token'], [
                'status' => WebhookDeliveryStatus::Pending,
                'available_at' => now()->addSeconds($this->backoffSeconds($attempts)),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            throw new WebhookAttemptFailed((string) $claim['delivery_id'], (string) $claim['attempt_token'], 'Webhook delivery failed.', $exception);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function finishAttempt(string $deliveryId, string $attemptToken, array $attributes): void
    {
        DB::transaction(function () use ($deliveryId, $attemptToken, $attributes): void {
            $locked = WebhookDelivery::query()->lockForUpdate()->findOrFail($deliveryId);
            if ($locked->status !== WebhookDeliveryStatus::Delivering || ! hash_equals((string) $locked->attempt_token, $attemptToken)) {
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
