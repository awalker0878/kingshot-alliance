<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\KingdomAllianceObservation;
use App\Contexts\GameWorld\Models\KingdomIngestionCandidate;
use App\Contexts\GameWorld\Models\KingdomIngestionSubscription;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Models\PlayerSnapshot;
use App\Domain\Kingdoms\Actions\CompleteKingdomIngestionBatch;
use App\Domain\Kingdoms\Actions\StageKingdomIngestionCandidate;
use App\Domain\Kingdoms\Actions\StartKingdomIngestionBatch;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAdapter;
use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomIngestionFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('kingdoms.ingestion_adapters', [FixtureKingdomIngestionAdapter::class]);
    }

    public function test_manager_can_create_approved_subscription_without_network_or_secret_configuration(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('K4 Foundation', 'k4-foundation', 6401);

        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-ingestion/subscriptions', ['adapter_key' => 'fixture.game'])
            ->assertRedirect();

        $subscription = KingdomIngestionSubscription::query()->sole();
        self::assertSame($alliance->id, $subscription->alliance_id);
        self::assertSame($alliance->kingdom_id, $subscription->kingdom_id);
        self::assertSame('fixture.game', $subscription->adapter_key);
        self::assertSame('1.0', $subscription->adapter_version);
        self::assertSame(KingdomIngestionSubscriptionState::Active, $subscription->state);

        foreach (['url', 'endpoint', 'headers', 'credentials', 'secret', 'token', 'cookie', 'raw_payload'] as $forbiddenColumn) {
            self::assertFalse(Schema::hasColumn('kingdom_ingestion_subscriptions', $forbiddenColumn));
            self::assertFalse(Schema::hasColumn('kingdom_ingestion_candidates', $forbiddenColumn));
        }

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_player_id' => Player::query()->where('user_id', $owner->id)->sole()->id,
            'event' => 'kingdoms.ingestion_subscription_created',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.ingestion_subscription_created',
        ]);

        $this->get('/alliance/kingdom-ingestion/manage')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomIngestionManage')
                ->where('alliance.kingdom', '6401')
                ->where('adapters.0.key', 'fixture.game')
                ->where('adapters.0.version', '1.0')
                ->where('subscriptions.0.adapterKey', 'fixture.game')
                ->where('subscriptions.0.contextCurrent', true)
                ->missing('subscriptions.0.url')
                ->missing('subscriptions.0.credentials'));
    }

    public function test_unapproved_adapter_and_member_access_fail_closed(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('K4 Approval', 'k4-approval', 6402);
        config()->set('kingdoms.ingestion_adapters', []);

        $this->actingAs($owner)->withSession($session)
            ->from('/alliance/kingdom-ingestion/manage')
            ->post('/alliance/kingdom-ingestion/subscriptions', ['adapter_key' => 'fixture.game'])
            ->assertRedirect('/alliance/kingdom-ingestion/manage')
            ->assertSessionHasErrors('adapter_key');
        self::assertSame(0, KingdomIngestionSubscription::query()->count());

        config()->set('kingdoms.ingestion_adapters', [FixtureKingdomIngestionAdapter::class]);
        $member = $this->member($alliance);
        $memberSession = $this->confirmedSession(Player::query()->where('user_id', $member->id)->sole());

        $this->actingAs($member)->withSession($memberSession)
            ->get('/alliance/kingdom-ingestion/manage')
            ->assertForbidden();
        $this->withSession($memberSession)
            ->post('/alliance/kingdom-ingestion/subscriptions', ['adapter_key' => 'fixture.game'])
            ->assertForbidden();
    }

    public function test_subscription_mutations_require_recent_password_confirmation(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('K4 Password', 'k4-password', 6403);
        $player = Player::query()->where('user_id', $owner->id)->sole();
        $activeSession = [
            (string) config('game_world.active_player_session_key') => $player->id,
            'auth.password_confirmed_at' => 0,
        ];

        $this->actingAs($owner)->withSession($activeSession)
            ->post('/alliance/kingdom-ingestion/subscriptions', ['adapter_key' => 'fixture.game'])
            ->assertRedirect(route('password.confirm'));
        self::assertSame(0, KingdomIngestionSubscription::query()->count());

        $this->withSession($this->confirmedSession($player))
            ->post('/alliance/kingdom-ingestion/subscriptions', ['adapter_key' => 'fixture.game'])
            ->assertRedirect();
        $subscription = KingdomIngestionSubscription::query()->sole();

        $this->withSession($activeSession)
            ->patch("/alliance/kingdom-ingestion/subscriptions/{$subscription->id}/state", ['state' => 'paused'])
            ->assertRedirect(route('password.confirm'));
        self::assertSame(KingdomIngestionSubscriptionState::Active, $subscription->refresh()->state);
    }

    public function test_kingdom_drift_blocks_automated_work_and_reactivation_but_allows_disable(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('K4 Drift', 'k4-drift', 6404);
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-ingestion/subscriptions', ['adapter_key' => 'fixture.game'])
            ->assertRedirect();
        $subscription = KingdomIngestionSubscription::query()->sole();

        $newKingdom = Kingdom::query()->create(['number' => 6499, 'status' => 'active']);
        $subscription->forceFill(['kingdom_id' => $newKingdom->id])->save();

        try {
            $this->app->make(StartKingdomIngestionBatch::class)->handle((string) $subscription->id, 'drift-window');
            self::fail('Expected Kingdom drift to block batch creation.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('subscription', $exception->errors());
        }

        $this->withSession($session)
            ->patch("/alliance/kingdom-ingestion/subscriptions/{$subscription->id}/state", ['state' => 'disabled'])
            ->assertRedirect();
        self::assertSame(KingdomIngestionSubscriptionState::Disabled, $subscription->refresh()->state);

        $this->withSession($session)
            ->from('/alliance/kingdom-ingestion/manage')
            ->patch("/alliance/kingdom-ingestion/subscriptions/{$subscription->id}/state", ['state' => 'active'])
            ->assertRedirect('/alliance/kingdom-ingestion/manage')
            ->assertSessionHasErrors('state');
        self::assertSame(KingdomIngestionSubscriptionState::Disabled, $subscription->refresh()->state);
    }

    public function test_batch_and_candidate_staging_are_idempotent_and_do_not_promote_business_observations(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('K4 Staging', 'k4-staging', 6405);
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-ingestion/subscriptions', ['adapter_key' => 'fixture.game'])
            ->assertRedirect();
        $subscription = KingdomIngestionSubscription::query()->sole();

        $start = $this->app->make(StartKingdomIngestionBatch::class);
        $batch = $start->handle((string) $subscription->id, 'window-6405');
        $retryBatch = $start->handle((string) $subscription->id, 'window-6405');
        self::assertSame($batch->id, $retryBatch->id);

        $stage = $this->app->make(StageKingdomIngestionCandidate::class);
        $playerRecord = [
            'target_kind' => 'player_snapshot',
            'stable_game_id' => 'player-6405-1',
            'source_record_id' => 'source-player-1',
            'captured_at' => now()->subMinute()->toIso8601String(),
            'payload' => [
                'observed_name' => 'Fixture Player',
                'power' => '123456789',
                'progression_level' => 'TC4',
                'observed_alliance_tag' => 'FIX',
            ],
        ];
        $candidate = $stage->handle((string) $subscription->id, (string) $batch->id, $playerRecord);
        $retryCandidate = $stage->handle((string) $subscription->id, (string) $batch->id, $playerRecord);
        self::assertSame($candidate->id, $retryCandidate->id);
        self::assertSame(KingdomIngestionCandidateState::Pending, $candidate->state);

        $quarantined = $stage->handle((string) $subscription->id, (string) $batch->id, [
            'target_kind' => 'alliance_observation',
            'stable_game_id' => null,
            'source_record_id' => 'source-alliance-missing-id',
            'captured_at' => now()->subMinute()->toIso8601String(),
            'payload' => [
                'observed_name' => 'Unknown Alliance',
                'observed_tag' => 'UNK',
                'power' => '987654321',
                'member_count' => 80,
            ],
        ]);
        self::assertSame(KingdomIngestionCandidateState::Quarantined, $quarantined->state);
        self::assertSame('missing_stable_game_id', $quarantined->quarantine_code);

        $batch->refresh();
        self::assertSame(2, $batch->records_received);
        self::assertSame(2, $batch->records_staged);
        self::assertSame(1, $batch->records_quarantined);
        self::assertSame(2, KingdomIngestionCandidate::query()->count());
        self::assertSame(0, PlayerSnapshot::query()->count());
        self::assertSame(0, KingdomAllianceObservation::query()->count());

        try {
            $stage->handle((string) $subscription->id, (string) $batch->id, [
                'target_kind' => 'player_snapshot',
                'stable_game_id' => 'player-6405-2',
                'source_record_id' => 'unsafe-extra-field',
                'captured_at' => now()->subMinute()->toIso8601String(),
                'payload' => [
                    'observed_name' => 'Unsafe Extra',
                    'power' => '5',
                    'secret_token' => 'must-not-be-retained',
                ],
            ]);
            self::fail('Expected unapproved normalized payload fields to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('payload', $exception->errors());
        }
        self::assertSame(2, KingdomIngestionCandidate::query()->count());

        $completed = $this->app->make(CompleteKingdomIngestionBatch::class)->handle(
            (string) $subscription->id,
            (string) $batch->id,
            KingdomIngestionBatchState::Partial,
        );
        self::assertSame(KingdomIngestionBatchState::Partial, $completed->state);
        self::assertNotNull($completed->completed_at);

        $this->get('/alliance/kingdom-ingestion/manage')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('subscriptions.0.latestBatch.state', 'partial')
                ->where('subscriptions.0.pendingCandidates', 1)
                ->where('subscriptions.0.quarantinedCandidates', 1)
                ->where('candidates.0.adapterKey', 'fixture.game')
                ->missing('candidates.0.normalizedPayload'));
    }

    public function test_submitted_ids_are_tenant_scoped_and_manager_can_reject_only_own_quarantined_candidate(): void
    {
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('K4 Tenant A', 'k4-tenant-a', 6406);
        [$ownerB, $allianceB, $sessionB] = $this->ownerAlliance('K4 Tenant B', 'k4-tenant-b', 6407);

        $this->actingAs($ownerB)->withSession($sessionB)
            ->post('/alliance/kingdom-ingestion/subscriptions', ['adapter_key' => 'fixture.game'])
            ->assertRedirect();
        $subscriptionB = KingdomIngestionSubscription::query()->where('alliance_id', $allianceB->id)->sole();
        $batchB = $this->app->make(StartKingdomIngestionBatch::class)->handle((string) $subscriptionB->id, 'tenant-b-window');
        $candidateB = $this->app->make(StageKingdomIngestionCandidate::class)->handle(
            (string) $subscriptionB->id,
            (string) $batchB->id,
            [
                'target_kind' => 'player_snapshot',
                'stable_game_id' => null,
                'source_record_id' => 'tenant-b-candidate',
                'captured_at' => now()->subMinute()->toIso8601String(),
                'payload' => ['observed_name' => 'Tenant B player', 'power' => '10'],
            ],
        );

        $this->actingAs($ownerA)->withSession($sessionA)
            ->patch("/alliance/kingdom-ingestion/subscriptions/{$subscriptionB->id}/state", ['state' => 'paused'])
            ->assertNotFound();
        $this->withSession($sessionA)
            ->post("/alliance/kingdom-ingestion/subscriptions/{$subscriptionB->id}/candidates/{$candidateB->id}/reject")
            ->assertNotFound();
        self::assertSame(KingdomIngestionCandidateState::Quarantined, $candidateB->refresh()->state);

        $this->actingAs($ownerB)->withSession($sessionB)
            ->post("/alliance/kingdom-ingestion/subscriptions/{$subscriptionB->id}/candidates/{$candidateB->id}/reject")
            ->assertRedirect();
        self::assertSame(KingdomIngestionCandidateState::Rejected, $candidateB->refresh()->state);
        self::assertSame('manager_rejected', $candidateB->rejection_code);

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $allianceB->id,
            'actor_player_id' => Player::query()->where('user_id', $ownerB->id)->sole()->id,
            'event' => 'kingdoms.ingestion_candidate_rejected',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $allianceB->id,
            'event_type' => 'kingdoms.ingestion_candidate_rejected',
        ]);
        self::assertSame(0, KingdomIngestionSubscription::query()->where('alliance_id', $allianceA->id)->count());
    }

    /** @return array{0: User, 1: Alliance, 2: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $kingdomModel = Kingdom::query()->firstOrCreate(
            ['number' => $kingdom],
            ['status' => 'active'],
        );
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdomModel->id,
            'game_player_id' => 'owner-'.$slug,
            'current_name' => $name.' Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, $name, $slug);

        return [$owner, $alliance, $this->confirmedSession($player)];
    }

    private function member(Alliance $alliance): User
    {
        $member = User::factory()->create();
        $player = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $alliance->kingdom_id,
            'game_player_id' => 'member-'.$member->id,
            'current_name' => 'Ingestion Member',
        ]);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $player->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        return $member;
    }

    /** @return array<string, mixed> */
    private function confirmedSession(Player $player): array
    {
        return [
            (string) config('game_world.active_player_session_key') => $player->id,
            'auth.password_confirmed_at' => time(),
        ];
    }
}

final class FixtureKingdomIngestionAdapter implements KingdomIngestionAdapter
{
    public function key(): string
    {
        return 'fixture.game';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function label(): string
    {
        return 'Fixture game source';
    }

    public function supportedTargetKinds(): array
    {
        return [
            KingdomIngestionTargetKind::PlayerSnapshot,
            KingdomIngestionTargetKind::AllianceObservation,
        ];
    }

    public function normalize(array $record): array
    {
        return [
            'target_kind' => $record['target_kind'] ?? '',
            'stable_game_id' => $record['stable_game_id'] ?? null,
            'source_record_id' => $record['source_record_id'] ?? null,
            'captured_at' => is_string($record['captured_at'] ?? null) ? $record['captured_at'] : '',
            'payload' => is_array($record['payload'] ?? null) ? $record['payload'] : [],
        ];
    }
}
