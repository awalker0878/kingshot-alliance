<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Services;

use App\Contexts\Alliance\Content\Queries\ContentStorageUsageQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\MembershipStatisticsQuery;
use App\Contexts\Platform\AllianceAdministration\Models\AllianceUsageSnapshot;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;

final readonly class PlatformUsageService
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private MembershipStatisticsQuery $memberships,
        private ContentStorageUsageQuery $storage,
    ) {}

    /** @return array{activeMembers:int,storageBytes:int,activeApiCredentials:int,activeWebhookSubscriptions:int,pendingOutboxMessages:int} */
    public function current(string $allianceId): array
    {
        $this->alliances->require($allianceId);

        return [
            'activeMembers' => $this->memberships->activeCount($allianceId),
            'storageBytes' => $this->storage->bytes($allianceId),
            'activeApiCredentials' => ApiCredential::query()
                ->where('alliance_id', $allianceId)
                ->whereNull('revoked_at')
                ->where(static function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count(),
            'activeWebhookSubscriptions' => WebhookSubscription::query()
                ->where('alliance_id', $allianceId)
                ->where('is_active', true)
                ->whereNull('revoked_at')
                ->count(),
            'pendingOutboxMessages' => OutboxMessage::query()
                ->where('alliance_id', $allianceId)
                ->whereNull('published_at')
                ->count(),
        ];
    }

    public function capture(string $allianceId): void
    {
        $usage = $this->current($allianceId);
        AllianceUsageSnapshot::query()->create([
            'alliance_id' => $allianceId,
            'active_members' => $usage['activeMembers'],
            'storage_bytes' => $usage['storageBytes'],
            'active_api_credentials' => $usage['activeApiCredentials'],
            'active_webhook_subscriptions' => $usage['activeWebhookSubscriptions'],
            'pending_outbox_messages' => $usage['pendingOutboxMessages'],
            'captured_at' => now(),
        ]);
    }

    public function captureAll(int $limit = 500): int
    {
        $count = 0;
        foreach ($this->alliances->all($limit) as $alliance) {
            $this->capture($alliance->allianceId);
            $count++;
        }

        return $count;
    }
}
