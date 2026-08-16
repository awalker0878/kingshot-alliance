<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence\Ingestion;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Ingestion\Actions\CreateKingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\Actions\PromoteKingdomIngestionPlayerSnapshot;
use App\Contexts\Intelligence\Ingestion\Actions\StageKingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Actions\StartKingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Contracts\KingdomIngestionAdapter;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionCandidateState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionTargetKind;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomIngestionPlayerPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('intelligence.ingestion_adapters', [PlayerPromotionFixtureAdapter::class]);
    }

    public function test_ingestion_provenance_migration_round_trips(): void
    {
        $migration = require database_path('migrations/2026_08_11_200000_add_ingestion_provenance_to_player_snapshots.php');
        self::assertInstanceOf(Migration::class, $migration);
        self::assertTrue(Schema::hasColumn('player_snapshots', 'source_identity_hash'));
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_candidates', 'promoted_record_id'));

        $migration->down();
        self::assertFalse(Schema::hasColumn('player_snapshots', 'source_identity_hash'));
        self::assertFalse(Schema::hasColumn('kingdom_ingestion_candidates', 'promoted_record_id'));

        $migration->up();
        self::assertTrue(Schema::hasColumn('player_snapshots', 'source_identity_hash'));
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_candidates', 'promoted_record_id'));
    }

    public function test_existing_roster_player_promotes_once_with_machine_provenance_and_later_capture_appends_history(): void
    {
        [$owner, $alliance, $entry, $subscription, $batch] = $this->context(
            6501,
            'k4-p2-success',
            'player-6501',
        );
        self::assertInstanceOf(AllianceRosterEntry::class, $entry);

        $capturedAt = now()->subMinutes(2)->startOfSecond();
        $candidate = $this->stagePlayer(
            $subscription,
            $batch,
            'player-6501',
            'source-player-6501',
            $capturedAt->toIso8601String(),
        );
        $promote = $this->app->make(PromoteKingdomIngestionPlayerSnapshot::class);

        $snapshot = $promote->handle((string) $subscription->id, (string) $candidate->id);
        self::assertInstanceOf(PlayerSnapshot::class, $snapshot);
        self::assertSame($entry->id, $snapshot->roster_entry_id);
        self::assertSame($entry->player_id, $snapshot->player_id);
        self::assertNull($snapshot->actor_player_id);
        self::assertSame('ingestion', $snapshot->source);
        self::assertSame($subscription->id, $snapshot->source_subscription_id);
        self::assertSame($batch->id, $snapshot->source_batch_id);
        self::assertSame('fixture.player-promotion', $snapshot->source_adapter_key);
        self::assertSame('2.0', $snapshot->source_adapter_version);
        self::assertSame('source-player-6501', $snapshot->source_record_id);
        self::assertSame($candidate->identity_hash, $snapshot->source_identity_hash);
        self::assertSame($candidate->payload_hash, $snapshot->source_payload_hash);

        $candidate->refresh();
        self::assertSame(KingdomIngestionCandidateState::Promoted, $candidate->state);
        self::assertSame('player_snapshot', $candidate->promoted_record_type);
        self::assertSame($snapshot->id, $candidate->promoted_record_id);
        self::assertNotNull($candidate->promoted_at);

        $retry = $promote->handle((string) $subscription->id, (string) $candidate->id);
        self::assertInstanceOf(PlayerSnapshot::class, $retry);
        self::assertSame($snapshot->id, $retry->id);
        self::assertSame(1, PlayerSnapshot::query()->count());
        self::assertSame(1, DB::table('audit_events')
            ->where('alliance_id', $alliance->id)
            ->where('event', 'intelligence.ingestion_candidate_promoted')
            ->count());
        self::assertSame(1, DB::table('outbox_messages')
            ->where('alliance_id', $alliance->id)
            ->where('event_type', 'intelligence.ingestion_candidate_promoted')
            ->count());

        $laterCandidate = $this->stagePlayer(
            $subscription,
            $batch,
            'player-6501',
            'source-player-6501',
            $capturedAt->copy()->addMinute()->toIso8601String(),
        );
        $later = $promote->handle((string) $subscription->id, (string) $laterCandidate->id);
        self::assertInstanceOf(PlayerSnapshot::class, $later);
        self::assertNotSame($snapshot->id, $later->id);
        self::assertSame(2, PlayerSnapshot::query()->count());

        $session = [(string) config('game_world.active_player_session_key') => (string) Player::query()->where('user_id', $owner->id)->sole()->id];
        $this->actingAs($owner)->withSession($session)
            ->get("/alliance/roster/{$entry->id}/history")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('snapshots.0.source', 'ingestion')
                ->where('snapshots.0.actorName', null)
                ->where('snapshots.0.sourceAdapterKey', 'fixture.player-promotion')
                ->where('snapshots.0.sourceRecordId', 'source-player-6501'));
    }

    public function test_cross_tenant_roster_target_quarantines_without_auto_enrollment(): void
    {
        [, $allianceA, , $subscriptionA, $batchA] = $this->context(
            6502,
            'k4-p2-tenant-a',
            null,
        );
        [$ownerB, $playerB, $allianceB] = $this->alliance(6502, 'k4-p2-tenant-b');
        $entryB = $this->app->make(SaveRosterEntry::class)->handle(
            $allianceB,
            $playerB,
            ['name' => 'Tenant B Player', 'game_player_id' => 'shared-player-6502'],
        );

        $candidate = $this->stagePlayer(
            $subscriptionA,
            $batchA,
            'shared-player-6502',
            'tenant-cross-target',
            now()->subMinute()->toIso8601String(),
        );
        $snapshot = $this->app->make(PromoteKingdomIngestionPlayerSnapshot::class)
            ->handle((string) $subscriptionA->id, (string) $candidate->id);

        self::assertNull($snapshot);
        self::assertSame(KingdomIngestionCandidateState::Quarantined, $candidate->refresh()->state);
        self::assertSame('roster_target_missing', $candidate->quarantine_code);
        self::assertSame(0, AllianceRosterEntry::query()->where('alliance_id', $allianceA->id)->count());
        self::assertSame($allianceB->id, $entryB->alliance_id);
        self::assertSame(0, PlayerSnapshot::query()->where('alliance_id', $allianceA->id)->count());
    }

    public function test_unknown_player_and_subscription_context_drift_quarantine_before_snapshot_mutation(): void
    {
        [, $alliance, , $subscription, $batch] = $this->context(6503, 'k4-p2-quarantine', null);
        $promote = $this->app->make(PromoteKingdomIngestionPlayerSnapshot::class);

        $unknown = $this->stagePlayer(
            $subscription,
            $batch,
            'unknown-player-6503',
            'unknown-player-record',
            now()->subMinutes(2)->toIso8601String(),
        );
        self::assertNull($promote->handle((string) $subscription->id, (string) $unknown->id));
        self::assertSame('unknown_player', $unknown->refresh()->quarantine_code);

        $drifted = $this->stagePlayer(
            $subscription,
            $batch,
            'unknown-player-drift',
            'drift-record',
            now()->subMinute()->toIso8601String(),
        );
        $newKingdom = Kingdom::query()->create(['number' => 6599, 'status' => 'active']);
        $subscription->forceFill(['kingdom_id' => $newKingdom->id])->save();
        self::assertNull($promote->handle((string) $subscription->id, (string) $drifted->id));
        self::assertSame('candidate_context_mismatch', $drifted->refresh()->quarantine_code);
        self::assertSame(0, PlayerSnapshot::query()->count());
    }

    public function test_source_revocation_quarantines_pending_candidate(): void
    {
        [, , , $subscription, $batch] = $this->context(6504, 'k4-p2-source-revoked', null);
        $candidate = $this->stagePlayer(
            $subscription,
            $batch,
            'unknown-player-source',
            'source-revoked-record',
            now()->subMinute()->toIso8601String(),
        );
        config()->set('intelligence.ingestion_adapters', []);

        $snapshot = $this->app->make(PromoteKingdomIngestionPlayerSnapshot::class)
            ->handle((string) $subscription->id, (string) $candidate->id);

        self::assertNull($snapshot);
        self::assertSame('source_version_unapproved', $candidate->refresh()->quarantine_code);
        self::assertSame(0, PlayerSnapshot::query()->count());
    }

    /**
     * @return array{User, Alliance, AllianceRosterEntry|null, KingdomIngestionSubscription, KingdomIngestionBatch}
     */
    private function context(int $kingdomNumber, string $slug, ?string $stableGameId): array
    {
        [$owner, $player, $alliance] = $this->alliance($kingdomNumber, $slug);
        $entry = $stableGameId === null
            ? null
            : $this->app->make(SaveRosterEntry::class)->handle(
                $alliance,
                $player,
                ['name' => 'Roster Player', 'game_player_id' => $stableGameId],
            );
        $subscription = $this->app->make(CreateKingdomIngestionSubscription::class)
            ->handle($alliance, $player, 'fixture.player-promotion');
        $batch = $this->app->make(StartKingdomIngestionBatch::class)
            ->handle((string) $subscription->id, 'window-'.$slug);

        return [$owner, $alliance, $entry, $subscription, $batch];
    }

    /** @return array{User, Player, Alliance} */
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
            'current_name' => 'K4 P2 '.str_replace('-', ' ', $slug).' Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle(
            $player,
            'K4 P2 '.str_replace('-', ' ', $slug),
            $slug,
        );

        return [$owner, $player, $alliance];
    }

    private function stagePlayer(
        KingdomIngestionSubscription $subscription,
        KingdomIngestionBatch $batch,
        string $stableGameId,
        string $sourceRecordId,
        string $capturedAt,
    ): KingdomIngestionCandidate {
        return $this->app->make(StageKingdomIngestionCandidate::class)->handle(
            (string) $subscription->id,
            (string) $batch->id,
            [
                'target_kind' => 'player_snapshot',
                'stable_game_id' => $stableGameId,
                'source_record_id' => $sourceRecordId,
                'captured_at' => $capturedAt,
                'payload' => [
                    'observed_name' => 'Fixture Player Observation',
                    'power' => '123456789',
                    'progression_level' => 'TC5',
                    'observed_alliance_tag' => 'FIX',
                ],
            ],
        );
    }
}

final class PlayerPromotionFixtureAdapter implements KingdomIngestionAdapter
{
    public function key(): string
    {
        return 'fixture.player-promotion';
    }

    public function version(): string
    {
        return '2.0';
    }

    public function label(): string
    {
        return 'Fixture player promotion source';
    }

    public function supportedTargetKinds(): array
    {
        return [KingdomIngestionTargetKind::PlayerSnapshot];
    }

    public function normalize(array $record): array
    {
        return $record;
    }
}
