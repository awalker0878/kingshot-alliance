<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Services;

use App\Contexts\Alliance\Content\Queries\ContentStorageUsageQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\MembershipStatisticsQuery;
use App\Contexts\Platform\AllianceAdministration\Models\AllianceUsageSnapshot;
use App\Contexts\Platform\Integrations\Queries\IntegrationUsageQuery;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;

final readonly class PlatformUsageService
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private MembershipStatisticsQuery $memberships,
        private ContentStorageUsageQuery $storage,
        private IntegrationUsageQuery $integrations,
    ) {}

    /** @return array{activeMembers:int,storageBytes:int,activeApiCredentials:int,activeWebhookSubscriptions:int,pendingOutboxMessages:int} */
    public function current(string $allianceId): array
    {
        $this->alliances->require($allianceId);

        return [
            'activeMembers' => $this->memberships->activeCount($allianceId),
            'storageBytes' => $this->storage->bytes($allianceId),
            'activeApiCredentials' => $this->integrations->activeCredentials($allianceId),
            'activeWebhookSubscriptions' => $this->integrations->activeWebhooks($allianceId),
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
        return DB::transaction(function () use ($limit): int {
            DB::table('alliance_usage_capture_state')->insertOrIgnore(['id' => 'scheduled']);
            $state = DB::table('alliance_usage_capture_state')->where('id', 'scheduled')
                ->lock('for update skip locked')->first();
            if ($state === null) {
                return 0;
            }

            $batch = $this->alliances->after($state->last_alliance_id, max(1, min(500, $limit)));
            if ($batch === [] && $state->last_alliance_id !== null) {
                $batch = $this->alliances->after(null, max(1, min(500, $limit)));
            }
            $lastId = null;
            foreach ($batch as $alliance) {
                $this->capture($alliance->allianceId);
                $lastId = $alliance->allianceId;
            }
            DB::table('alliance_usage_capture_state')->where('id', 'scheduled')->update([
                'last_alliance_id' => $lastId, 'last_batch_at' => now(),
            ]);

            return count($batch);
        });
    }
}
