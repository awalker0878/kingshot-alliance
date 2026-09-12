<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Contexts\Platform\Integrations\Policies\IntegrationRuntimePolicy;
use App\Contexts\Platform\Integrations\Services\WebhookEndpointPolicy;
use App\Contexts\Platform\Integrations\Services\WebhookTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class DeliverWebhook
{
    public function __construct(
        private WebhookEndpointPolicy $endpointPolicy,
        private WebhookTransport $transport,
        private IntegrationRuntimePolicy $availability,
    ) {}

    public function handle(string $deliveryId, ?string $reservationToken = null): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Webhook delivery cannot run inside a caller transaction.');
        }
        $delivery = WebhookDelivery::query()->whereKey($deliveryId)->first();
        if (! $delivery instanceof WebhookDelivery) {
            return;
        }

        $claim = DB::transaction(function () use ($delivery, $reservationToken): ?array {
            $locked = WebhookDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $eligible = $locked->status === WebhookDeliveryStatus::Pending
                ? $reservationToken === null
                : ($locked->status === WebhookDeliveryStatus::Queued && $reservationToken !== null
                    && hash_equals((string) $locked->attempt_token, $reservationToken));
            if (! $eligible || $locked->available_at->isFuture()) {
                return null;
            }

            if ($locked->attempts >= $locked->max_attempts) {
                $locked->forceFill([
                    'status' => WebhookDeliveryStatus::Failed,
                    'attempt_token' => null,
                    'last_error' => 'Webhook delivery exhausted its attempt budget.',
                ])->save();

                return null;
            }

            $subscription = WebhookSubscription::query()
                ->lockForUpdate()
                ->find($locked->webhook_subscription_id);
            if (! $subscription instanceof WebhookSubscription || ! $subscription->is_active || $subscription->revoked_at !== null
                || $subscription->alliance_id !== $locked->alliance_id || ! $this->availability->allowsWebhooks((string) $locked->alliance_id)) {
                $locked->forceFill([
                    'status' => WebhookDeliveryStatus::Failed,
                    'attempt_token' => null,
                    'last_error' => 'Webhook subscription or Alliance integration is no longer available.',
                ])->save();

                return null;
            }

            $payload = $locked->payload;
            if (! is_array($payload)) {
                $locked->forceFill([
                    'status' => WebhookDeliveryStatus::Failed,
                    'attempt_token' => null,
                    'last_error' => 'Webhook payload is unavailable.',
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
                'subscription_id' => (string) $subscription->id,
                'alliance_id' => (string) $locked->alliance_id,
                'url' => (string) $subscription->url,
                'signing_secret' => (string) $subscription->signing_secret,
                'payload' => $payload,
                'attempts' => $attempts,
                'max_attempts' => (int) $locked->max_attempts,
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

        $attributes = [
            'status' => $attempts >= (int) $claim['max_attempts'] ? WebhookDeliveryStatus::Failed : WebhookDeliveryStatus::Pending,
            'available_at' => now()->addSeconds($this->backoffSeconds($attempts)),
            'attempt_token' => null,
            'response_code' => null,
            'response_excerpt' => null,
            'last_error' => 'Webhook transport failed; provider acknowledgement is unknown.',
        ];
        try {
            $endpoint = $this->endpointPolicy->resolveAllowed((string) $claim['url']);
            if (! $this->authorizeHandoff($claim)) {
                return;
            }
            $response = $this->transport->send($endpoint, $body, [
                'User-Agent' => 'Kingshot-Alliance-Webhook/1.0',
                'X-Kingshot-Delivery' => (string) $claim['delivery_id'],
                'X-Kingshot-Event' => (string) $claim['event_type'],
                'X-Kingshot-Timestamp' => $timestamp,
                'X-Kingshot-Signature' => 'sha256='.$signature,
            ]);
            $attributes['response_code'] = $response->status();
            $attributes['last_error'] = 'Webhook endpoint returned HTTP '.$response->status().'.';
            if ($response->successful()) {
                $attributes['status'] = WebhookDeliveryStatus::Delivered;
                $attributes['delivered_at'] = now();
                $attributes['last_error'] = null;
            }
        } catch (ValidationException) {
            $attributes['status'] = WebhookDeliveryStatus::Failed;
            $attributes['last_error'] = 'Webhook destination failed the current outbound security policy.';
        } catch (\Throwable) {
            // No raw exception, URL credential, request or provider response is persisted.
        }
        // Persistence failures remain visible to worker recovery; they must not
        // turn a known provider success into a fabricated provider failure.
        $this->finishAttempt((string) $claim['delivery_id'], (string) $claim['attempt_token'], $attributes);
    }

    /** @param array<string, mixed> $claim */
    private function authorizeHandoff(array $claim): bool
    {
        return DB::transaction(function () use ($claim): bool {
            $delivery = WebhookDelivery::query()->lockForUpdate()->find($claim['delivery_id']);
            if (! $delivery instanceof WebhookDelivery || $delivery->status !== WebhookDeliveryStatus::Delivering
                || $delivery->attempt_token !== $claim['attempt_token']) {
                return false;
            }
            $subscription = WebhookSubscription::query()->lockForUpdate()->find($claim['subscription_id']);
            if (! $subscription instanceof WebhookSubscription || ! $subscription->is_active || $subscription->revoked_at !== null
                || $subscription->alliance_id !== $claim['alliance_id'] || $subscription->url !== $claim['url']
                || ! hash_equals((string) $subscription->signing_secret, (string) $claim['signing_secret'])
                || ! $this->availability->allowsWebhooks((string) $claim['alliance_id'])) {
                $delivery->forceFill([
                    'status' => WebhookDeliveryStatus::Failed, 'attempt_token' => null,
                    'last_error' => 'Webhook subscription changed before provider handoff.',
                ])->save();

                return false;
            }

            return true;
        });
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
