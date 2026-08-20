<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteAuthorization;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Contexts\Platform\Integrations\ValueObjects\IssuedWebhookSubscription;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RotateWebhookSigningSecret
{
    public function __construct(
        private AllianceWriteAuthorization $allianceAuthority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $subscriptionId,
    ): IssuedWebhookSubscription {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $subscriptionId): IssuedWebhookSubscription {
            [$alliance, $actor] = $this->allianceAuthority->authorizeManagerExclusive($actorPlayerId, $allianceId);
            $subscription = WebhookSubscription::query()
                ->where('alliance_id', $alliance->allianceId)
                ->whereKey($subscriptionId)
                ->lockForUpdate()
                ->firstOrFail();
            if (! $subscription->is_active || $subscription->revoked_at !== null) {
                throw ValidationException::withMessages([
                    'webhook' => 'Only an active webhook subscription can rotate its signing secret.',
                ]);
            }

            $secret = bin2hex(random_bytes(32));
            $subscription->forceFill([
                'signing_secret' => $secret,
                'secret_rotated_at' => now(),
            ])->save();
            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'rotated_at' => $subscription->secret_rotated_at?->toIso8601String(),
            ];
            $this->audit->record(
                'integration.webhook.secret_rotated',
                $actor,
                $subscription,
                $alliance->allianceId,
                $metadata,
            );
            $this->outbox->record(
                'integration.webhook.secret_rotated',
                $alliance->allianceId,
                $subscription,
                $metadata,
                null,
                'alliance:'.$alliance->allianceId,
            );

            return new IssuedWebhookSubscription(
                (string) $subscription->id,
                (string) $subscription->name,
                $secret,
            );
        });
    }
}
