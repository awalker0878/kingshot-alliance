<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Integrations\Models\WebhookSubscription;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RevokeWebhookSubscription
{
    public function __construct(
        private AllianceMutationAuthority $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, WebhookSubscription $subscription): WebhookSubscription
    {
        if ((string) $subscription->alliance_id !== (string) $alliance->id) {
            throw new InvalidArgumentException('Webhook subscription does not belong to the active alliance.');
        }

        return DB::transaction(function () use ($alliance, $actor, $subscription): WebhookSubscription {
            $authority = $this->mutations->require($actor, $alliance, PermissionKey::AllianceManage);
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
