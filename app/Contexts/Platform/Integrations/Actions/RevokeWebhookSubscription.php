<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteAuthorization;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RevokeWebhookSubscription
{
    public function __construct(
        private AllianceWriteAuthorization $allianceAuthority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $subscriptionId): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $subscriptionId): string {
            [$currentAlliance, $currentActor] = $this->allianceAuthority->authorizeManagerActive($actorPlayerId, $allianceId);

            $locked = WebhookSubscription::query()
                ->where('alliance_id', $currentAlliance->allianceId)
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            if ($locked->revoked_at !== null) {
                return (string) $locked->id;
            }

            $locked->forceFill([
                'is_active' => false,
                'revoked_at' => now(),
            ])->save();

            $this->audit->record('integration.webhook.revoked', $currentActor, $locked, $currentAlliance->allianceId, [
                'subscription_id' => $locked->id,
            ]);
            $this->outbox->record('integration.webhook.revoked', $currentAlliance->allianceId, $locked, [
                'subscription_id' => $locked->id,
            ]);

            return (string) $locked->id;
        });
    }
}
