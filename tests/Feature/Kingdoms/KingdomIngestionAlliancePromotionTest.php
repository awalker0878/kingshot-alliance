<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CreateKingdomIngestionSubscription;
use App\Domain\Kingdoms\Actions\PromoteKingdomIngestionAllianceObservation;
use App\Domain\Kingdoms\Actions\StageKingdomIngestionCandidate;
use App\Domain\Kingdoms\Actions\StartKingdomIngestionBatch;
use App\Domain\Kingdoms\Actions\StartTrackingKingdomAlliance;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAdapter;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomIngestionAlliancePromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('kingdoms.ingestion_adapters', [AlliancePromotionFixtureAdapter::class]);
    }

    public function test_alliance_observation_provenance_migration_round_trips(): void
    {
        $migration = require database_path('migrations/2026_08_11_210000_add_ingestion_provenance_to_alliance_observations.php');
        self::assertInstanceOf(Migration::class, $migration);
        self::assertTrue(Schema::hasColumn('kingdom_alliance_observations', 'source_identity_hash'));

        $migration->down();
        self::assertFalse(Schema::hasColumn('kingdom_alliance_observations', 'source_identity_hash'));

        $migration->up();
        self::assertTrue(Schema::hasColumn('kingdom_alliance_observations', 'source_identity_hash'));
    }

    public function test_existing_active_tracking_promotes_once_with_machine_provenance_and_later_capture_appends(): void
    {
        [$owner, $alliance, $tracking, $subscription, $batch] = $this->context(
            6701,
            'k4-p3-success',
            'game-alliance-6701',
        );
        $capturedAt = now()->subMinutes(2)->startOfSecond();
        $candidate = $this->stageAlliance(
            $subscription,
            $batch,
            'game-alliance-6701',
            'alliance-source-6701',
            $capturedAt->toIso8601String(),
            'Observed Machine Alliance',
        );
        $promote = $this->app->make(PromoteKingdomIngestionAllianceObservation::class);

        $observation = $promote->handle((string) $subscription->id, (string) $candidate->id);
        self::assertInstanceOf(KingdomAllianceObservation::class, $observation);
        self::assertSame($tracking->id, $observation->tracked_kingdom_alliance_id);
        self::assertSame($tracking->kingdom_alliance_id, $observation->kingdom_alliance_id);
        self::assertNull($observation->actor_user_id);
        self::assertSame('ingestion', $observation->source);
        self::assertSame($subscription->id, $observation->source_subscription_id);
        self::assertSame($batch->id, $observation->source_batch_id);
        self::assertSame('fixture.alliance-promotion', $observation->source_adapter_key);
        self::assertSame('3.0', $observation->source_adapter_version);
        self::assertSame('alliance-source-6701', $observation->source_record_id);
        self::assertSame($candidate->identity_hash, $observation->source_identity_hash);
        self::assertSame($candidate->payload_hash, $observation->source_payload_hash);
        self::assertNull($observation->corrects_observation_id);
        self::assertNull($observation->invalidated_at);

        $candidate->refresh();
        self::assertSame(KingdomIngestionCandidateState::Promoted, $candidate->state);
        self::assertSame('alliance_observation', $candidate->promoted_record_type);
        self::assertSame($observation->id, $candidate->promoted_record_id);

        $retry = $promote->handle((string) $subscription->id, (string) $candidate->id);
        self::assertInstanceOf(KingdomAllianceObservation::class, $retry);
        self::assertSame($observation->id, $retry->id);
        self::assertSame(1, KingdomAllianceObservation::query()->count());
        self::assertSame(1, DB::table('audit_events')
            ->where('alliance_id', $alliance->id)
            ->where('event', 'kingdoms.ingestion_candidate_promoted')
            ->count());

        $laterCandidate = $this->stageAlliance(
            $subscription,
            $batch,
            'game-alliance-6701',
            'alliance-source-6701',
            $capturedAt->copy()->addMinute()->toIso8601String(),
            'Observed Machine Alliance Updated',
        );
        $later = $promote->handle((string) $subscription->id, (string) $laterCandidate->id);
        self::assertInstanceOf(KingdomAllianceObservation::class, $later);
        self::assertNotSame($observation->id, $later->id);
        self::assertSame(2, KingdomAllianceObservation::query()->count());
        self::assertSame('Observed Machine Alliance Updated', $tracking->kingdomAlliance()->firstOrFail()->current_name);

        $session = [(string) config('identity.active_alliance_session_key') => (string) $alliance->id];
        $this->actingAs($owner)->withSession($session)
            ->get("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('history.0.source', 'ingestion')
                ->where('history.0.actorName', null)
                ->where('history.0.sourceAdapterKey', 'fixture.alliance-promotion')
                ->where('history.0.sourceRecordId', 'alliance-source-6701'));
    }

    public function test_shared_neutral_reference_does_not_auto_create_tracking_for_another_tenant(): void
    {
        [$ownerB, $allianceB] = $this->alliance(6702, 'k4-p3-tenant-b');
        $trackingB = $this->app->make(StartTrackingKingdomAlliance::class)->handle(
            $allianceB,
            $ownerB,
            [
                'current_name' => 'Shared Neutral Alliance',
                'current_tag' => 'SHR',
                'game_alliance_id' => 'shared-game-alliance-6702',
            ],
        );
        [$ownerA, $allianceA] = $this->alliance(6702, 'k4-p3-tenant-a');
        $subscriptionA = $this->app->make(CreateKingdomIngestionSubscription::class)
            ->handle($allianceA, $ownerA, 'fixture.alliance-promotion');
        $batchA = $this->app->make(StartKingdomIngestionBatch::class)
            ->handle((string) $subscriptionA->id, 'window-k4-p3-tenant-a');
        $candidate = $this->stageAlliance(
            $subscriptionA,
            $batchA,
            'shared-game-alliance-6702',
            'cross-tenant-alliance-record',
            now()->subMinute()->toIso8601String(),
            'Should Not Promote',
        );

        $observation = $this->app->make(PromoteKingdomIngestionAllianceObservation::class)
            ->handle((string) $subscriptionA->id, (string) $candidate->id);

        self::assertNull($observation);
        self::assertSame('tracking_target_missing', $candidate->refresh()->quarantine_code);
        self::assertSame(0, TrackedKingdomAlliance::query()->where('alliance_id', $allianceA->id)->count());
        self::assertSame($allianceB->id, $trackingB->alliance_id);
        self::assertSame(0, KingdomAllianceObservation::query()->where('alliance_id', $allianceA->id)->count());
    }

    public function test_inactive_tracking_quarantines_without_observation_or_reactivation(): void
    {
        [, , $tracking, $subscription, $batch] = $this->context(
            6703,
            'k4-p3-inactive',
            'game-alliance-6703',
        );
        $tracking->forceFill([
            'state' => TrackedKingdomAllianceState::Archived,
            'archived_at' => now(),
        ])->save();
        $candidate = $this->stageAlliance(
            $subscription,
            $batch,
            'game-alliance-6703',
            'inactive-tracking-record',
            now()->subMinute()->toIso8601String(),
            'Inactive Tracking Observation',
        );

        $observation = $this->app->make(PromoteKingdomIngestionAllianceObservation::class)
            ->handle((string) $subscription->id, (string) $candidate->id);

        self::assertNull($observation);
        self::assertSame('tracking_target_inactive', $candidate->refresh()->quarantine_code);
        self::assertSame(TrackedKingdomAllianceState::Archived, $tracking->refresh()->state);
        self::assertSame(0, KingdomAllianceObservation::query()->count());
    }

    public function test_unknown_reference_and_source_revocation_quarantine_before_business_history(): void
    {
        [$owner, $alliance] = $this->alliance(6704, 'k4-p3-quarantine');
        $subscription = $this->app->make(CreateKingdomIngestionSubscription::class)
            ->handle($alliance, $owner, 'fixture.alliance-promotion');
        $batch = $this->app->make(StartKingdomIngestionBatch::class)
            ->handle((string) $subscription->id, 'window-k4-p3-quarantine');
        $unknown = $this->stageAlliance(
            $subscription,
            $batch,
            'unknown-game-alliance-6704',
            'unknown-alliance-record',
            now()->subMinutes(2)->toIso8601String(),
            'Unknown Alliance',
        );
        $promote = $this->app->make(PromoteKingdomIngestionAllianceObservation::class);

        self::assertNull($promote->handle((string) $subscription->id, (string) $unknown->id));
        self::assertSame('unknown_game_alliance', $unknown->refresh()->quarantine_code);

        $tracking = $this->app->make(StartTrackingKingdomAlliance::class)->handle(
            $alliance,
            $owner,
            [
                'current_name' => 'Revoked Source Alliance',
                'game_alliance_id' => 'revoked-game-alliance-6704',
            ],
        );
        $revoked = $this->stageAlliance(
            $subscription,
            $batch,
            'revoked-game-alliance-6704',
            'revoked-source-record',
            now()->subMinute()->toIso8601String(),
            'Revoked Source Observation',
        );
        config()->set('kingdoms.ingestion_adapters', []);

        self::assertNull($promote->handle((string) $subscription->id, (string) $revoked->id));
        self::assertSame('source_version_unapproved', $revoked->refresh()->quarantine_code);
        self::assertSame($alliance->id, $tracking->alliance_id);
        self::assertSame(0, KingdomAllianceObservation::query()->count());
    }

    /**
     * @return array{User, Alliance, TrackedKingdomAlliance, KingdomIngestionSubscription, KingdomIngestionBatch}
     */
    private function context(int $kingdomNumber, string $slug, string $stableGameId): array
    {
        [$owner, $alliance] = $this->alliance($kingdomNumber, $slug);
        $tracking = $this->app->make(StartTrackingKingdomAlliance::class)->handle(
            $alliance,
            $owner,
            [
                'current_name' => 'Tracked '.$stableGameId,
                'current_tag' => 'K4P3',
                'game_alliance_id' => $stableGameId,
            ],
        );
        $subscription = $this->app->make(CreateKingdomIngestionSubscription::class)
            ->handle($alliance, $owner, 'fixture.alliance-promotion');
        $batch = $this->app->make(StartKingdomIngestionBatch::class)
            ->handle((string) $subscription->id, 'window-'.$slug);

        return [$owner, $alliance, $tracking, $subscription, $batch];
    }

    /** @return array{User, Alliance} */
    private function alliance(int $kingdomNumber, string $slug): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle(
            $owner,
            'K4 P3 '.str_replace('-', ' ', $slug),
            $slug,
            $kingdomNumber,
        );

        return [$owner, $alliance];
    }

    private function stageAlliance(
        KingdomIngestionSubscription $subscription,
        KingdomIngestionBatch $batch,
        string $stableGameId,
        string $sourceRecordId,
        string $capturedAt,
        string $observedName,
    ): KingdomIngestionCandidate {
        return $this->app->make(StageKingdomIngestionCandidate::class)->handle(
            (string) $subscription->id,
            (string) $batch->id,
            [
                'target_kind' => 'alliance_observation',
                'stable_game_id' => $stableGameId,
                'source_record_id' => $sourceRecordId,
                'captured_at' => $capturedAt,
                'payload' => [
                    'observed_name' => $observedName,
                    'observed_tag' => 'AUTO',
                    'power' => '987654321',
                    'member_count' => 91,
                ],
            ],
        );
    }
}

final class AlliancePromotionFixtureAdapter implements KingdomIngestionAdapter
{
    public function key(): string
    {
        return 'fixture.alliance-promotion';
    }

    public function version(): string
    {
        return '3.0';
    }

    public function label(): string
    {
        return 'Fixture alliance promotion source';
    }

    public function supportedTargetKinds(): array
    {
        return [KingdomIngestionTargetKind::AllianceObservation];
    }

    public function normalize(array $record): array
    {
        return $record;
    }
}
