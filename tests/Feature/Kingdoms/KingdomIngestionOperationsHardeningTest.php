<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\KingdomIngestionBatch;
use App\Contexts\GameWorld\Models\KingdomIngestionCandidate;
use App\Contexts\GameWorld\Models\KingdomIngestionSubscription;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Actions\CreateKingdomIngestionSubscription;
use App\Domain\Kingdoms\Actions\EnforceKingdomIngestionRetention;
use App\Domain\Kingdoms\Actions\ReconcileKingdomIngestionSources;
use App\Domain\Kingdoms\Actions\StageKingdomIngestionCandidate;
use App\Domain\Kingdoms\Actions\StartKingdomIngestionBatch;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Domain\Kingdoms\Data\KingdomIngestionAcquisitionPage;
use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use App\Domain\Kingdoms\Services\KingdomIngestionOperationalHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class KingdomIngestionOperationsHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('kingdoms.ingestion_adapters', [OperationsHardeningFixtureAdapter::class]);
        config()->set('kingdoms.ingestion_retention', [
            'payload_days' => 10,
            'terminal_candidate_days' => 30,
            'quarantined_candidate_days' => 60,
            'batch_days' => 30,
            'disabled_compaction_days' => 10,
        ]);
        config()->set('kingdoms.ingestion_health', [
            'overdue_minutes' => 5,
            'stale_pending_minutes' => 15,
            'quarantined_threshold' => 2,
            'recent_failure_minutes' => 60,
        ]);
    }

    public function test_retention_redacts_then_prunes_terminal_operational_rows_without_deleting_subscription(): void
    {
        [$owner, $alliance] = $this->alliance(6901, 'k4-p5-retention');
        $subscription = $this->subscription($owner, $alliance);
        $batch = $this->app->make(StartKingdomIngestionBatch::class)
            ->handle((string) $subscription->id, 'retention-window-6901');
        $candidate = $this->candidate($subscription, $batch, 'retention-player-6901');

        $candidate->forceFill([
            'state' => KingdomIngestionCandidateState::Rejected,
            'rejection_code' => 'operator_rejected',
        ])->save();
        DB::table('kingdom_ingestion_candidates')
            ->where('id', $candidate->id)
            ->update(['updated_at' => now()->subDays(20)]);
        $batch->forceFill([
            'state' => KingdomIngestionBatchState::Completed,
            'completed_at' => now()->subDays(20),
        ])->save();

        $retention = $this->app->make(EnforceKingdomIngestionRetention::class);
        $first = $retention->handle();
        self::assertSame(1, $first['payloadsRedacted']);
        self::assertSame(0, $first['terminalCandidatesPurged']);
        self::assertSame([], $candidate->refresh()->normalized_payload);

        DB::table('kingdom_ingestion_candidates')
            ->where('id', $candidate->id)
            ->update(['updated_at' => now()->subDays(40)]);
        $batch->forceFill(['completed_at' => now()->subDays(40)])->save();

        $second = $retention->handle();
        self::assertSame(1, $second['terminalCandidatesPurged']);
        self::assertSame(1, $second['batchesPurged']);
        self::assertFalse(KingdomIngestionCandidate::query()->whereKey($candidate->id)->exists());
        self::assertFalse(KingdomIngestionBatch::query()->whereKey($batch->id)->exists());
        self::assertTrue(KingdomIngestionSubscription::query()->whereKey($subscription->id)->exists());
    }

    public function test_retention_keeps_quarantined_rows_until_longer_review_window(): void
    {
        [$owner, $alliance] = $this->alliance(6902, 'k4-p5-quarantine');
        $subscription = $this->subscription($owner, $alliance);
        $batch = $this->app->make(StartKingdomIngestionBatch::class)
            ->handle((string) $subscription->id, 'quarantine-window-6902');
        $candidate = $this->candidate($subscription, $batch, 'quarantine-player-6902');
        $candidate->forceFill([
            'state' => KingdomIngestionCandidateState::Quarantined,
            'quarantine_code' => 'unknown_player',
        ])->save();
        DB::table('kingdom_ingestion_candidates')
            ->where('id', $candidate->id)
            ->update(['updated_at' => now()->subDays(40)]);
        $batch->forceFill([
            'state' => KingdomIngestionBatchState::Partial,
            'completed_at' => now()->subDays(40),
        ])->save();

        $retention = $this->app->make(EnforceKingdomIngestionRetention::class);
        $first = $retention->handle();
        self::assertSame(0, $first['quarantinedCandidatesPurged']);
        self::assertTrue(KingdomIngestionCandidate::query()->whereKey($candidate->id)->exists());

        DB::table('kingdom_ingestion_candidates')
            ->where('id', $candidate->id)
            ->update(['updated_at' => now()->subDays(70)]);
        $batch->forceFill(['completed_at' => now()->subDays(70)])->save();
        $second = $retention->handle();
        self::assertSame(1, $second['quarantinedCandidatesPurged']);
        self::assertSame(1, $second['batchesPurged']);
    }

    public function test_source_reconciliation_disables_subscription_when_approval_disappears(): void
    {
        [$owner, $alliance] = $this->alliance(6903, 'k4-p5-revoke');
        $subscription = $this->subscription($owner, $alliance);
        $subscription->forceFill(['next_run_at' => now()->addMinute()])->save();

        config()->set('kingdoms.ingestion_adapters', []);
        $reconcile = $this->app->make(ReconcileKingdomIngestionSources::class);
        self::assertSame(1, $reconcile->handle());

        $subscription->refresh();
        self::assertSame(KingdomIngestionSubscriptionState::Disabled, $subscription->state);
        self::assertSame('source_unapproved', $subscription->blocked_reason);
        self::assertSame('source_unapproved', $subscription->last_failure_code);
        self::assertNull($subscription->next_run_at);
        self::assertSame(0, $reconcile->handle());
    }

    public function test_operational_health_flags_bounded_attention_signals(): void
    {
        $health = $this->app->make(KingdomIngestionOperationalHealth::class);
        self::assertFalse($health->snapshot()['attentionRequired']);

        [$owner, $alliance] = $this->alliance(6904, 'k4-p5-health');
        $subscription = $this->subscription($owner, $alliance);
        $subscription->forceFill([
            'state' => KingdomIngestionSubscriptionState::Disabled,
            'blocked_reason' => 'source_unapproved',
            'last_failure_code' => 'source_unapproved',
        ])->save();

        $snapshot = $health->snapshot();
        self::assertSame(1, $snapshot['sourceRevokedSubscriptions']);
        self::assertTrue($snapshot['attentionRequired']);
    }

    /** @return array{User, Alliance} */
    private function alliance(int $kingdomNumber, string $slug): array
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->firstOrCreate(
            ['number' => $kingdomNumber],
            ['status' => 'active'],
        );
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-'.$slug,
            'current_name' => 'K4 P5 '.str_replace('-', ' ', $slug).' Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle(
            $player,
            'K4 P5 '.str_replace('-', ' ', $slug),
            $slug,
        );

        return [$owner, $alliance];
    }

    private function subscription(User $owner, Alliance $alliance): KingdomIngestionSubscription
    {
        return $this->app->make(CreateKingdomIngestionSubscription::class)
            ->handle($alliance, Player::query()->where('user_id', $owner->id)->sole(), 'fixture.operations-hardening');
    }

    private function candidate(
        KingdomIngestionSubscription $subscription,
        KingdomIngestionBatch $batch,
        string $stableGameId,
    ): KingdomIngestionCandidate {
        return $this->app->make(StageKingdomIngestionCandidate::class)->handle(
            (string) $subscription->id,
            (string) $batch->id,
            [
                'target_kind' => 'player_snapshot',
                'stable_game_id' => $stableGameId,
                'source_record_id' => 'source-'.$stableGameId,
                'captured_at' => now()->subMinute()->toIso8601String(),
                'payload' => [
                    'observed_name' => 'Operations fixture',
                    'power' => '1000',
                    'progression_level' => null,
                    'observed_alliance_tag' => null,
                ],
            ],
        );
    }
}

final class OperationsHardeningFixtureAdapter implements KingdomIngestionAcquisitionAdapter
{
    public function key(): string
    {
        return 'fixture.operations-hardening';
    }

    public function version(): string
    {
        return '5.0';
    }

    public function label(): string
    {
        return 'Fixture operations-hardening source';
    }

    public function supportedTargetKinds(): array
    {
        return [KingdomIngestionTargetKind::PlayerSnapshot];
    }

    public function pollIntervalSeconds(): int
    {
        return 300;
    }

    public function acquire(?string $cursor, int $limit): KingdomIngestionAcquisitionPage
    {
        return new KingdomIngestionAcquisitionPage('operations-window', $cursor, []);
    }

    public function normalize(array $record): array
    {
        return $record;
    }
}
