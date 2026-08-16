<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Services;

use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Contexts\Platform\Models\AllianceUsageSnapshot;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;

final class PlatformUsageService
{
    /** @return array{activeMembers: int, storageBytes: int, activeApiCredentials: int, activeWebhookSubscriptions: int, pendingOutboxMessages: int} */
    public function current(Alliance $alliance): array
    {
        return [
            'activeMembers' => AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('status', MembershipStatus::Active->value)
                ->count(),
            'storageBytes' => (int) MediaAsset::query()
                ->where('alliance_id', $alliance->id)
                ->sum('size_bytes'),
            'activeApiCredentials' => ApiCredential::query()
                ->where('alliance_id', $alliance->id)
                ->whereNull('revoked_at')
                ->where(static function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count(),
            'activeWebhookSubscriptions' => WebhookSubscription::query()
                ->where('alliance_id', $alliance->id)
                ->where('is_active', true)
                ->whereNull('revoked_at')
                ->count(),
            'pendingOutboxMessages' => OutboxMessage::query()
                ->where('alliance_id', $alliance->id)
                ->whereNull('published_at')
                ->count(),
        ];
    }

    public function capture(Alliance $alliance): AllianceUsageSnapshot
    {
        $usage = $this->current($alliance);

        return AllianceUsageSnapshot::query()->create([
            'alliance_id' => $alliance->id,
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

        Alliance::query()->orderBy('id')->limit($limit)->each(function (Alliance $alliance) use (&$count): void {
            $this->capture($alliance);
            $count++;
        });

        return $count;
    }
}
