<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\WebhookSubscription;
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

    public function handle(Alliance $alliance, User $actor, WebhookSubscription $subscription): WebhookSubscription
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::AllianceManage)) {
            throw new AuthorizationException;
        }
        if ($subscription->alliance_id !== $alliance->id) {
            throw new InvalidArgumentException('Webhook subscription does not belong to the active alliance.');
        }
        if ($subscription->revoked_at !== null) {
            return $subscription;
        }

        return DB::transaction(function () use ($alliance, $actor, $subscription): WebhookSubscription {
            $subscription->forceFill([
                'is_active' => false,
                'revoked_at' => now(),
            ])->save();

            $this->audit->record('integration.webhook.revoked', $actor, $subscription, $alliance, [
                'subscription_id' => $subscription->id,
            ]);
            $this->outbox->record('integration.webhook.revoked', $alliance->id, $subscription, [
                'subscription_id' => $subscription->id,
            ]);

            return $subscription->refresh();
        });
    }
}
