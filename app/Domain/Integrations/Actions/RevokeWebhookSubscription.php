<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Integrations\Models\WebhookSubscription;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RevokeWebhookSubscription
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, WebhookSubscription $subscription): WebhookSubscription
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::AllianceManage)) {
            throw new AuthorizationException;
        }
        if ($subscription->alliance_id !== $alliance->id) {
            throw new InvalidArgumentException('Webhook subscription does not belong to the active alliance.');
        }

        return DB::transaction(function () use ($alliance, $actor, $subscription): WebhookSubscription {
            $currentAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $lockedActor = Player::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $this->authorization->allows($lockedActor, $currentAlliance, PermissionKey::AllianceManage)) {
                throw new AuthorizationException;
            }

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

            $this->audit->record('integration.webhook.revoked', $lockedActor, $locked, $currentAlliance, [
                'subscription_id' => $locked->id,
            ]);
            $this->outbox->record('integration.webhook.revoked', $currentAlliance->id, $locked, [
                'subscription_id' => $locked->id,
            ]);

            return $locked->refresh();
        });
    }
}
