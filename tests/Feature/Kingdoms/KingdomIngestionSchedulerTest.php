<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CreateKingdomIngestionSubscription;
use App\Domain\Kingdoms\Actions\PromoteKingdomIngestionPlayerSnapshot;
use App\Domain\Kingdoms\Actions\QueueDueKingdomIngestionSubscriptions;
use App\Domain\Kingdoms\Actions\RunKingdomIngestionSubscription;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Kingdoms\Actions\StageKingdomIngestionCandidate;
use App\Domain\Kingdoms\Actions\StartKingdomIngestionBatch;
use App\Domain\Kingdoms\Actions\StartTrackingKingdomAlliance;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Domain\Kingdoms\Data\KingdomIngestionAcquisitionPage;
use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use App\Domain\Kingdoms\Jobs\RunKingdomIngestionSubscriptionJob;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class KingdomIngestionSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('kingdoms.ingestion_adapters', [ScheduledIngestionFixtureAdapter::class]);
        ScheduledIngestionFixtureAdapter::$fail = false;
        ScheduledIngestionFixtureAdapter::$records = [];
    }

    public function test_scheduler_migration_round_trips(): void
    {
        $migration = require database_path('migrations/2026_08_11_220000_add_ingestion_scheduling.php');
        self::assertInstanceOf(Migration::class, $migration);
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_subscriptions', 'next_run_at'));
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_subscriptions', 'circuit_open_until'));
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_batches', 'next_source_cursor'));

        $migration->down();
        self::assertFalse(Schema::hasColumn('kingdom_ingestion_subscriptions', 'next_run_at'));
        self::assertFalse(Schema::hasColumn('kingdom_ingestion_batches', 'next_source_cursor'));

        $migration->up();
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_subscriptions', 'next_run_at'));
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_batches', 'next_source_cursor'));
    }

    public function test_due_scheduler_claims_subscription_once_and_dispatches_isolated_job(): void
    {
        [$owner, $alliance] = $this->alliance(6801, 'k4-p4-queue');
        $subscription = $this->subscription($owner, $alliance);
        $subscription->forceFill(['next_run_at' => now()->subMinute()])->save();
        Queue::fake();

        $queued = $this->app->make(QueueDueKingdomIngestionSubscriptions::class)->handle(100);
        self::assertSame(1, $queued);
        Queue::assertPushed(
            RunKingdomIngestionSubscriptionJob::class,
            fn (RunKingdomIngestionSubscriptionJob $job): bool => $job->subscriptionId === $subscription->id,
        );

        $subscription->refresh();
        self::assertNotNull($subscription->last_claimed_at);
        self::assertNotNull($subscription->next_run_at);
        self::assertTrue($subscription->next_run_at->isFuture());
        self::assertSame(0, $this->app->make(QueueDueKingdomIngestionSubscriptions::class)->handle(100));
    }

    public function test_scheduled_page_promotes_both_accepted_targets_and_exact_window_replay_is_idempotent(): void
    {
        [$owner, $alliance] = $this->alliance(6802, 'k4-p4-run');
        $player = $owner->players()->sole();
        $entry = $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            ['name' => 'Scheduled Player', 'game_player_id' => 'player-6802'],
        );
        $tracking = $this->app->make(StartTrackingKingdomAlliance::class)->handle(
            $alliance,
            $player,
            [
                'current_name' => 'Scheduled Alliance',
                'current_tag' => 'AUTO',
                'game_alliance_id' => 'alliance-6802',
            ],
        );
        $subscription = $this->subscription($owner, $alliance);
        $capturedAt = now()->subMinute()->startOfSecond()->toIso8601String();
        ScheduledIngestionFixtureAdapter::$records = [
            [
                'target_kind' => 'player_snapshot',
                'stable_game_id' => 'player-6802',
                'source_record_id' => 'scheduled-player-6802',
                'captured_at' => $capturedAt,
                'payload' => [
                    'observed_name' => 'Scheduled Player Observation',
                    'power' => '123456789',
                    'progression_level' => 'TC6',
                    'observed_alliance_tag' => 'AUTO',
                ],
            ],
            [
                'target_kind' => 'alliance_observation',
                'stable_game_id' => 'alliance-6802',
                'source_record_id' => 'scheduled-alliance-6802',
                'captured_at' => $capturedAt,
                'payload' => [
                    'observed_name' => 'Scheduled Alliance Observation',
                    'observed_tag' => 'AUTO',
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
        self::assertSame(1, PlayerSnapshot::query()->where('roster_entry_id', $entry->id)->count());
        self::assertSame(1, KingdomAllianceObservation::query()
            ->where('tracked_kingdom_alliance_id', $tracking->id)
            ->count());
        self::assertSame('cursor-1', $subscription->refresh()->source_cursor);
        self::assertSame(0, $subscription->consecutive_failures);

        $subscription->forceFill(['source_cursor' => null])->save();
        $retry = $run->handle((string) $subscription->id);
        self::assertInstanceOf(KingdomIngestionBatch::class, $retry);
        self::assertSame($batch->id, $retry->id);
        self::assertSame(1, KingdomIngestionBatch::query()->count());
        self::assertSame(2, KingdomIngestionCandidate::query()->count());
        self::assertSame(1, PlayerSnapshot::query()->count());
        self::assertSame(1, KingdomAllianceObservation::query()->count());
        self::assertSame('cursor-1', $subscription->refresh()->source_cursor);
    }

    public function test_repeated_acquisition_failures_open_bounded_circuit_without_persisting_exception_text(): void
    {
        [$owner, $alliance] = $this->alliance(6803, 'k4-p4-circuit');
        $subscription = $this->subscription($owner, $alliance);
        ScheduledIngestionFixtureAdapter::$fail = true;
        $run = $this->app->make(RunKingdomIngestionSubscription::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $run->handle((string) $subscription->id);
                self::fail('Expected acquisition failure.');
            } catch (RuntimeException $exception) {
                self::assertSame('fixture acquisition failure', $exception->getMessage());
            }
        }

        $subscription->refresh();
        self::assertSame(3, $subscription->consecutive_failures);
        self::assertSame('acquisition_failed', $subscription->last_failure_code);
        self::assertSame('acquisition_failed', $subscription->blocked_reason);
        self::assertNotSame('fixture acquisition failure', $subscription->last_failure_code);
        self::assertNotSame('fixture acquisition failure', $subscription->blocked_reason);
        self::assertNotNull($subscription->circuit_open_until);
        self::assertTrue($subscription->circuit_open_until->isFuture());
        self::assertSame(0, KingdomIngestionBatch::query()->count());
    }

    public function test_exhausted_queue_job_finalizes_started_pending_batch(): void
    {
        [$owner, $alliance] = $this->alliance(6804, 'k4-p4-exhausted');
        $subscription = $this->subscription($owner, $alliance);
        $batch = $this->app->make(StartKingdomIngestionBatch::class)
            ->handle((string) $subscription->id, 'exhausted-window-6804');
        self::assertSame(KingdomIngestionBatchState::Pending, $batch->state);

        $job = new RunKingdomIngestionSubscriptionJob((string) $subscription->id);
        $job->failed(new RuntimeException('raw upstream detail'));

        $batch->refresh();
        self::assertSame(KingdomIngestionBatchState::Failed, $batch->state);
        self::assertSame('retry_exhausted', $batch->failure_code);
        self::assertNotSame('raw upstream detail', $batch->failure_code);
    }

    public function test_manager_replay_requires_password_confirmation_and_re_drives_existing_promotion_action(): void
    {
        [$owner, $alliance] = $this->alliance(6805, 'k4-p4-replay');
        $player = $owner->players()->sole();
        $subscription = $this->subscription($owner, $alliance);
        $batch = $this->app->make(StartKingdomIngestionBatch::class)
            ->handle((string) $subscription->id, 'replay-window-6805');
        $candidate = $this->app->make(StageKingdomIngestionCandidate::class)->handle(
            (string) $subscription->id,
            (string) $batch->id,
            [
                'target_kind' => 'player_snapshot',
                'stable_game_id' => 'player-6805',
                'source_record_id' => 'replay-player-6805',
                'captured_at' => now()->subMinute()->toIso8601String(),
                'payload' => [
                    'observed_name' => 'Replay Player',
                    'power' => '5000',
                    'progression_level' => null,
                    'observed_alliance_tag' => null,
                ],
            ],
        );
        self::assertNull($this->app->make(PromoteKingdomIngestionPlayerSnapshot::class)
            ->handle((string) $subscription->id, (string) $candidate->id));
        self::assertSame(KingdomIngestionCandidateState::Quarantined, $candidate->refresh()->state);
        self::assertSame('unknown_player', $candidate->quarantine_code);

        $unconfirmed = [
            (string) config('identity.active_player_session_key') => (string) $player->id,
            'auth.password_confirmed_at' => 0,
        ];
        $path = "/alliance/kingdom-ingestion/subscriptions/{$subscription->id}/candidates/{$candidate->id}/replay";
        $this->actingAs($owner)->withSession($unconfirmed)
            ->post($path)
            ->assertRedirect(route('password.confirm'));
        self::assertSame(KingdomIngestionCandidateState::Quarantined, $candidate->refresh()->state);

        $entry = $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            ['name' => 'Replay Player', 'game_player_id' => 'player-6805'],
        );
        $this->withSession($this->confirmedSession($player))
            ->post($path)
            ->assertRedirect();

        self::assertSame(KingdomIngestionCandidateState::Promoted, $candidate->refresh()->state);
        self::assertSame(1, PlayerSnapshot::query()->where('roster_entry_id', $entry->id)->count());
        self::assertSame(0, $batch->refresh()->records_quarantined);
        self::assertSame(1, DB::table('audit_events')
            ->where('alliance_id', $alliance->id)
            ->where('event', 'kingdoms.ingestion_replay_requested')
            ->count());
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
            'current_name' => 'K4 P4 '.str_replace('-', ' ', $slug).' Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle(
            $player,
            'K4 P4 '.str_replace('-', ' ', $slug),
            $slug,
        );

        return [$owner, $alliance];
    }

    private function subscription(User $owner, Alliance $alliance): KingdomIngestionSubscription
    {
        return $this->app->make(CreateKingdomIngestionSubscription::class)
            ->handle($alliance, $owner->players()->sole(), 'fixture.scheduled-ingestion');
    }

    /** @return array<string, mixed> */
    private function confirmedSession(Player $player): array
    {
        return [
            (string) config('identity.active_player_session_key') => $player->id,
            'auth.password_confirmed_at' => time(),
        ];
    }
}

final class ScheduledIngestionFixtureAdapter implements KingdomIngestionAcquisitionAdapter
{
    public static bool $fail = false;

    /** @var list<array<string, mixed>> */
    public static array $records = [];

    public function key(): string
    {
        return 'fixture.scheduled-ingestion';
    }

    public function version(): string
    {
        return '4.0';
    }

    public function label(): string
    {
        return 'Fixture scheduled ingestion source';
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
        if (self::$fail) {
            throw new RuntimeException('fixture acquisition failure');
        }

        if ($limit < count(self::$records)) {
            throw new RuntimeException('fixture acquisition limit was not honored');
        }

        return new KingdomIngestionAcquisitionPage(
            $cursor === null ? 'window-root' : 'window-'.$cursor,
            $cursor === null ? 'cursor-1' : 'cursor-2',
            self::$records,
        );
    }

    public function normalize(array $record): array
    {
        return $record;
    }
}
