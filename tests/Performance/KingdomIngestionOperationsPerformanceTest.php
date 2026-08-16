<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionOperationalHealth;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KingdomIngestionOperationsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_health_uses_bounded_queries_at_realistic_ingestion_volume(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'K4 Operations Performance', 'k4-operations-performance', 6905);
        self::assertNotNull($alliance->kingdom_id);

        $now = now();
        $subscriptions = [];

        for ($index = 1; $index <= 250; $index++) {
            $state = match (true) {
                $index <= 150 => 'active',
                $index <= 200 => 'disabled',
                default => 'paused',
            };

            $subscriptions[] = [
                'id' => (string) Str::ulid(),
                'alliance_id' => $alliance->id,
                'kingdom_id' => $alliance->kingdom_id,
                'adapter_key' => sprintf('capacity-%03d', $index),
                'adapter_version' => '5.0',
                'state' => $state,
                'source_cursor' => null,
                'next_run_at' => $index <= 150 ? $now->copy()->subMinutes(10) : null,
                'last_claimed_at' => null,
                'last_succeeded_at' => null,
                'last_failed_at' => null,
                'consecutive_failures' => 0,
                'circuit_open_until' => $index > 100 && $index <= 150
                    ? $now->copy()->addMinutes(20)
                    : null,
                'last_failure_code' => $index > 150 && $index <= 200 ? 'source_unapproved' : null,
                'blocked_at' => $index > 150 && $index <= 200 ? $now : null,
                'blocked_reason' => $index > 150 && $index <= 200 ? 'source_unapproved' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($subscriptions, 100) as $chunk) {
            DB::table('kingdom_ingestion_subscriptions')->insert($chunk);
        }

        $batches = [];
        for ($index = 0; $index < 40; $index++) {
            $subscription = $subscriptions[$index];
            $batches[] = [
                'id' => (string) Str::ulid(),
                'subscription_id' => $subscription['id'],
                'alliance_id' => $alliance->id,
                'kingdom_id' => $alliance->kingdom_id,
                'adapter_key' => $subscription['adapter_key'],
                'adapter_version' => '5.0',
                'source_cursor' => null,
                'next_source_cursor' => null,
                'source_window_id' => sprintf('failed-window-%03d', $index),
                'state' => 'failed',
                'records_received' => 0,
                'records_staged' => 0,
                'records_quarantined' => 0,
                'records_rejected' => 0,
                'started_at' => $now->copy()->subMinutes(10),
                'completed_at' => $now->copy()->subMinutes(5),
                'failure_code' => 'acquisition_failed',
                'created_at' => $now->copy()->subMinutes(10),
                'updated_at' => $now->copy()->subMinutes(5),
            ];
        }
        DB::table('kingdom_ingestion_batches')->insert($batches);

        $candidateBatch = $batches[0];
        $candidates = [];
        for ($index = 1; $index <= 110; $index++) {
            $stalePending = $index <= 80;
            $identityHash = hash('sha256', 'capacity-candidate-'.$index);
            $candidates[] = [
                'id' => (string) Str::ulid(),
                'subscription_id' => $candidateBatch['subscription_id'],
                'batch_id' => $candidateBatch['id'],
                'alliance_id' => $alliance->id,
                'kingdom_id' => $alliance->kingdom_id,
                'target_kind' => 'player_snapshot',
                'stable_game_id' => 'capacity-player-'.$index,
                'source_record_id' => 'capacity-record-'.$index,
                'captured_at' => $stalePending ? $now->copy()->subMinutes(20) : $now,
                'normalized_payload' => json_encode(['power' => '1000'], JSON_THROW_ON_ERROR),
                'payload_hash' => hash('sha256', 'capacity-payload-'.$index),
                'identity_hash' => $identityHash,
                'state' => $stalePending ? 'pending' : 'quarantined',
                'quarantine_code' => $stalePending ? null : 'unknown_player',
                'rejection_code' => null,
                'promoted_record_type' => null,
                'promoted_record_id' => null,
                'promoted_at' => null,
                'created_at' => $stalePending ? $now->copy()->subMinutes(20) : $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($candidates, 100) as $chunk) {
            DB::table('kingdom_ingestion_candidates')->insert($chunk);
        }

        $selectQueries = 0;
        $collectQueries = false;
        DB::listen(static function (QueryExecuted $query) use (&$selectQueries, &$collectQueries): void {
            if ($collectQueries && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selectQueries++;
            }
        });

        $collectQueries = true;
        $snapshot = $this->app->make(KingdomIngestionOperationalHealth::class)->snapshot();
        $collectQueries = false;

        self::assertSame(150, $snapshot['activeSubscriptions']);
        self::assertSame(50, $snapshot['sourceRevokedSubscriptions']);
        self::assertSame(100, $snapshot['overdueSubscriptions']);
        self::assertSame(50, $snapshot['openCircuits']);
        self::assertSame(80, $snapshot['stalePendingCandidates']);
        self::assertSame(30, $snapshot['quarantinedCandidates']);
        self::assertSame(40, $snapshot['recentFailedBatches']);
        self::assertTrue($snapshot['attentionRequired']);
        self::assertLessThanOrEqual(
            8,
            $selectQueries,
            'K4 operational health must remain a bounded aggregate query set rather than growing with ingestion volume.',
        );
    }
}
