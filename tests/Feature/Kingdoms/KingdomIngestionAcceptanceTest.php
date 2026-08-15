<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Contexts\Accounts\Models\User;
use App\Domain\Kingdoms\Actions\CreateKingdomIngestionSubscription;
use App\Domain\Kingdoms\Actions\EnforceKingdomIngestionRetention;
use App\Domain\Kingdoms\Actions\ReconcileKingdomIngestionSources;
use App\Domain\Kingdoms\Actions\RunKingdomIngestionSubscription;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Kingdoms\Actions\StartTrackingKingdomAlliance;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Domain\Kingdoms\Data\KingdomIngestionAcquisitionPage;
use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use App\Contexts\GameWorld\Models\KingdomAllianceObservation;
use App\Contexts\GameWorld\Models\KingdomIngestionBatch;
use App\Contexts\GameWorld\Models\KingdomIngestionCandidate;
use App\Contexts\GameWorld\Models\KingdomIngestionSubscription;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Models\PlayerSnapshot;
use App\Domain\Kingdoms\Services\KingdomIngestionOperationalHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class KingdomIngestionAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_whole_increment_preserves_source_tenancy_idempotency_revocation_and_canonical_history_boundaries(): void
    {
        self::assertSame([], config('kingdoms.ingestion_adapters'));

        config()->set('kingdoms.ingestion_adapters', [KingdomIngestionAcceptanceFixtureAdapter::class]);
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

        [$owner, $alliance] = $this->alliance(7001, 'k4-p6-acceptance');
        $player = \App\Contexts\GameWorld\Models\Player::query()->where('user_id', $owner->id)->sole();
        $entry = $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            [
                'name' => 'Acceptance Player',
                'game_player_id' => 'player-7001',
            ],
        );
        $tracking = $this->app->make(StartTrackingKingdomAlliance::class)->handle(
            $alliance,
            $player,
            [
                'current_name' => 'Acceptance Alliance',
                'current_tag' => 'K4A',
                'game_alliance_id' => 'alliance-7001',
            ],
        );
        $subscription = $this->app->make(CreateKingdomIngestionSubscription::class)
            ->handle($alliance, $player, 'fixture.acceptance');

        $capturedAt = now()->subMinute()->startOfSecond()->toIso8601String();
        KingdomIngestionAcceptanceFixtureAdapter::$records = [
            [
                'target_kind' => 'player_snapshot',
                'stable_game_id' => 'player-7001',
                'source_record_id' => 'acceptance-player-7001',
                'captured_at' => $capturedAt,
                'payload' => [
                    'observed_name' => 'Acceptance Player Observation',
                    'power' => '123456789',
                    'progression_level' => 'TC6',
                    'observed_alliance_tag' => 'K4A',
                ],
            ],
            [
                'target_kind' => 'alliance_observation',
                'stable_game_id' => 'alliance-7001',
                'source_record_id' => 'acceptance-alliance-7001',
                'captured_at' => $capturedAt,
                'payload' => [
                    'observed_name' => 'Acceptance Alliance Observation',
                    'observed_tag' => 'K4A',
                    'power' => '987654321',
                    'member_count' => 88,
                ],
            ],
        ];

        $run = $this->app->make(RunKingdomIngestionSubscription::class);
        $batch = $run->handle((string) $subscription->id);

        self::assertInstanceOf(KingdomIngestionBatch::class, $batch);
        self::assertSame(KingdomIngestionBatchState::Completed, $batch->state);
        self::assertSame('cursor-1', $batch->next_source_cursor);
        self::assertSame(2, $batch->records_received);
        self::assertSame(2, KingdomIngestionCandidate::query()
            ->where('state', KingdomIngestionCandidateState::Promoted)
            ->count());

        $snapshot = PlayerSnapshot::query()->where('roster_entry_id', $entry->id)->sole();
        $observation = KingdomAllianceObservation::query()
            ->where('tracked_kingdom_alliance_id', $tracking->id)
            ->sole();

        $this->assertCanonicalProvenance(
            $snapshot->source,
            $snapshot->source_subscription_id,
            $snapshot->source_batch_id,
            $snapshot->source_adapter_key,
            $snapshot->source_adapter_version,
            $snapshot->source_record_id,
            $snapshot->source_identity_hash,
            $snapshot->source_payload_hash,
            (string) $subscription->id,
            (string) $batch->id,
            'acceptance-player-7001',
        );
        $this->assertCanonicalProvenance(
            $observation->source,
            $observation->source_subscription_id,
            $observation->source_batch_id,
            $observation->source_adapter_key,
            $observation->source_adapter_version,
            $observation->source_record_id,
            $observation->source_identity_hash,
            $observation->source_payload_hash,
            (string) $subscription->id,
            (string) $batch->id,
            'acceptance-alliance-7001',
        );
        self::assertSame('cursor-1', $subscription->refresh()->source_cursor);

        $subscription->forceFill(['source_cursor' => null])->save();
        $retry = $run->handle((string) $subscription->id);

        self::assertInstanceOf(KingdomIngestionBatch::class, $retry);
        self::assertSame($batch->id, $retry->id);
        self::assertSame(1, KingdomIngestionBatch::query()->count());
        self::assertSame(2, KingdomIngestionCandidate::query()->count());
        self::assertSame(1, PlayerSnapshot::query()->count());
        self::assertSame(1, KingdomAllianceObservation::query()->count());
        self::assertSame('cursor-1', $subscription->refresh()->source_cursor);

        config()->set('kingdoms.ingestion_adapters', []);
        $reconcile = $this->app->make(ReconcileKingdomIngestionSources::class);
        self::assertSame(1, $reconcile->handle());
        self::assertSame(0, $reconcile->handle());

        $subscription->refresh();
        self::assertSame(KingdomIngestionSubscriptionState::Disabled, $subscription->state);
        self::assertSame('source_unapproved', $subscription->blocked_reason);
        self::assertSame('source_unapproved', $subscription->last_failure_code);
        self::assertNull($subscription->next_run_at);
        self::assertTrue($this->app->make(KingdomIngestionOperationalHealth::class)->snapshot()['attentionRequired']);

        DB::table('kingdom_ingestion_candidates')->update(['updated_at' => now()->subDays(20)]);
        $batch->forceFill(['completed_at' => now()->subDays(20)])->save();

        $retention = $this->app->make(EnforceKingdomIngestionRetention::class);
        $redaction = $retention->handle();
        self::assertSame(2, $redaction['payloadsRedacted']);
        self::assertSame(0, $redaction['terminalCandidatesPurged']);
        self::assertSame(2, KingdomIngestionCandidate::query()->count());

        foreach (KingdomIngestionCandidate::query()->get() as $candidate) {
            self::assertSame([], $candidate->normalized_payload);
        }

        DB::table('kingdom_ingestion_candidates')->update(['updated_at' => now()->subDays(40)]);
        $batch->forceFill(['completed_at' => now()->subDays(40)])->save();

        $pruning = $retention->handle();
        self::assertSame(2, $pruning['terminalCandidatesPurged']);
        self::assertSame(1, $pruning['batchesPurged']);
        self::assertSame(0, KingdomIngestionCandidate::query()->count());
        self::assertSame(0, KingdomIngestionBatch::query()->count());
        self::assertTrue(KingdomIngestionSubscription::query()->whereKey($subscription->id)->exists());

        $snapshot->refresh();
        $observation->refresh();
        self::assertSame(1, PlayerSnapshot::query()->count());
        self::assertSame(1, KingdomAllianceObservation::query()->count());
        self::assertSame((string) $subscription->id, $snapshot->source_subscription_id);
        self::assertSame((string) $batch->id, $snapshot->source_batch_id);
        self::assertSame('fixture.acceptance', $snapshot->source_adapter_key);
        self::assertNotNull($snapshot->source_identity_hash);
        self::assertNotNull($snapshot->source_payload_hash);
        self::assertSame((string) $subscription->id, $observation->source_subscription_id);
        self::assertSame((string) $batch->id, $observation->source_batch_id);
        self::assertSame('fixture.acceptance', $observation->source_adapter_key);
        self::assertNotNull($observation->source_identity_hash);
        self::assertNotNull($observation->source_payload_hash);
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
            'current_name' => 'K4 P6 Acceptance Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle(
            $player,
            'K4 P6 Acceptance',
            $slug,
        );

        return [$owner, $alliance];
    }

    private function assertCanonicalProvenance(
        string $source,
        ?string $subscriptionId,
        ?string $batchId,
        ?string $adapterKey,
        ?string $adapterVersion,
        ?string $sourceRecordId,
        ?string $identityHash,
        ?string $payloadHash,
        string $expectedSubscriptionId,
        string $expectedBatchId,
        string $expectedSourceRecordId,
    ): void {
        self::assertSame('ingestion', $source);
        self::assertSame($expectedSubscriptionId, $subscriptionId);
        self::assertSame($expectedBatchId, $batchId);
        self::assertSame('fixture.acceptance', $adapterKey);
        self::assertSame('6.0', $adapterVersion);
        self::assertSame($expectedSourceRecordId, $sourceRecordId);
        self::assertNotNull($identityHash);
        self::assertNotNull($payloadHash);
    }
}

final class KingdomIngestionAcceptanceFixtureAdapter implements KingdomIngestionAcquisitionAdapter
{
    /** @var list<array<string, mixed>> */
    public static array $records = [];

    public function key(): string
    {
        return 'fixture.acceptance';
    }

    public function version(): string
    {
        return '6.0';
    }

    public function label(): string
    {
        return 'Fixture K4 whole-increment acceptance source';
    }

    public function supportedTargetKinds(): array
    {
        return [
            KingdomIngestionTargetKind::PlayerSnapshot,
            KingdomIngestionTargetKind::AllianceObservation,
        ];
    }

    public function pollIntervalSeconds(): int
    {
        return 60;
    }

    public function acquire(?string $cursor, int $limit): KingdomIngestionAcquisitionPage
    {
        if ($limit < count(self::$records)) {
            throw new RuntimeException('fixture acquisition limit was not honored');
        }

        return new KingdomIngestionAcquisitionPage(
            $cursor === null ? 'acceptance-window-root' : 'acceptance-window-'.$cursor,
            $cursor === null ? 'cursor-1' : 'cursor-2',
            self::$records,
        );
    }

    public function normalize(array $record): array
    {
        return $record;
    }
}
