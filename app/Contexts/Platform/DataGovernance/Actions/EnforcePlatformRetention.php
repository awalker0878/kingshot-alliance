<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Actions;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EnforcePlatformRetention
{
    /** @return array{webhookPayloadsRedacted:int,credentialsPurged:int,usageSnapshotsPurged:int,exportMetadataPurged:int} */
    public function handle(int $limit = 500): array
    {
        $limit = max(1, min(500, $limit));
        $now = now()->toImmutable();
        $webhookPayloadsRedacted = DB::transaction(function () use ($limit, $now): int {
            $query = DB::table('webhook_deliveries')->whereNotNull('payload')
                ->whereIn('status', ['delivered', 'failed'])->where('updated_at', '<', $now->subDays(30));
            $ids = (clone $query)->orderBy('updated_at')->orderBy('id')->limit($limit)
                ->lock('for update skip locked')->pluck('id');

            // The lock and terminal predicate fence a concurrent manual retry.
            return $query->whereIn('id', $ids)->update([
                'payload' => null, 'response_excerpt' => null, 'last_error' => null, 'updated_at' => $now,
            ]);
        });
        $credentials = DB::table('api_credentials')->where('revoked_at', '<', $now->subDays(90));
        foreach (['external_actor_links', 'external_actor_action_receipts'] as $history) {
            $credentials->whereNotExists(static fn (Builder $query) => $query->select('id')->from($history)
                ->whereColumn($history.'.api_credential_id', 'api_credentials.id'));
        }
        $credentialsPurged = $this->purge($credentials, 'revoked_at', $limit);
        $usageSnapshotsPurged = $this->purge(DB::table('alliance_usage_snapshots')->where('captured_at', '<', $now->subDays(365)), 'captured_at', $limit);
        $exportMetadataPurged = $this->purge(DB::table('alliance_data_exports')->where('generated_at', '<', $now->subDays(365)), 'generated_at', $limit);

        return ['webhookPayloadsRedacted' => $webhookPayloadsRedacted, 'credentialsPurged' => $credentialsPurged, 'usageSnapshotsPurged' => $usageSnapshotsPurged, 'exportMetadataPurged' => $exportMetadataPurged];
    }

    private function purge(Builder $query, string $ageColumn, int $limit): int
    {
        return DB::transaction(static function () use ($query, $ageColumn, $limit): int {
            $ids = (clone $query)->orderBy($ageColumn)->orderBy('id')->limit($limit)
                ->lock('for update skip locked')->pluck('id');

            return $query->whereIn('id', $ids)->delete();
        });
    }
}
