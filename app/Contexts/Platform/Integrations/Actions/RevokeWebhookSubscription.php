<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RevokeWebhookSubscription
{
    public function __construct(
        private AllianceAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, WebhookSubscription $subscription): WebhookSubscription
    {
        if ((string) $subscription->alliance_id !== (string) $alliance->id) {
            throw new InvalidArgumentException('Webhook subscription does not belong to the active alliance.');
        }

        return DB::transaction(function () use ($alliance, $actor, $subscription): WebhookSubscription {
            $authority = $this->mutations->require($actor, $alliance, AlliancePermission::Manage);
            $currentAlliance = $authority->alliance;
            $currentActor = $authority->actor;

            $locked = WebhookSubscription::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            if ($locked->revoked_at !== null) {
                return $locked;
            }

            $locked->forceFill([
                'is_active' => false,
                'revoked_at' => now(),
            ])->save();

            $this->audit->record('integration.webhook.revoked', $currentActor, $locked, $currentAlliance, [
                'subscription_id' => $locked->id,
            ]);
            $this->outbox->record('integration.webhook.revoked', $currentAlliance->id, $locked, [
                'subscription_id' => $locked->id,
            ]);

            return $locked->refresh();
        });
    }
}
