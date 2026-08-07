<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Integrations\Models\ApiCredential;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Platform\Models\AllianceUsageSnapshot;
use Illuminate\Support\Facades\DB;

final class EnforcePlatformRetention
{
    /** @return array{webhookPayloadsRedacted: int, credentialsPurged: int, usageSnapshotsPurged: int, exportMetadataPurged: int} */
    public function handle(): array
    {
        $webhookPayloadsRedacted = WebhookDelivery::query()
            ->whereNotNull('payload')
            ->whereIn('status', ['delivered', 'failed'])
            ->where('updated_at', '<', now()->subDays(30))
            ->update([
                'payload' => null,
                'response_excerpt' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]);

        $credentialsPurged = ApiCredential::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<', now()->subDays(90))
            ->delete();

        $usageSnapshotsPurged = AllianceUsageSnapshot::query()
            ->where('captured_at', '<', now()->subDays(365))
            ->delete();

        $exportMetadataPurged = DB::table('alliance_data_exports')
            ->where('generated_at', '<', now()->subDays(365))
            ->delete();

        return [
            'webhookPayloadsRedacted' => $webhookPayloadsRedacted,
            'credentialsPurged' => $credentialsPurged,
            'usageSnapshotsPurged' => $usageSnapshotsPurged,
            'exportMetadataPurged' => $exportMetadataPurged,
        ];
    }
}
