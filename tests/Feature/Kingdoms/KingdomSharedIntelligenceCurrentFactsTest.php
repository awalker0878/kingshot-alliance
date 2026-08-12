<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\AddKingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\InvalidateKingdomAllianceObservation;
use App\Domain\Kingdoms\Actions\RecordKingdomAllianceObservation;
use App\Domain\Kingdoms\Actions\RemoveKingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Actions\RevokeKingdomIntelligenceShare;
use App\Domain\Kingdoms\Actions\StartTrackingKingdomAlliance;
use App\Domain\Kingdoms\Actions\UpdateAllianceKingdom;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareTargetState;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Queries\SharedKingdomIntelligenceCurrentQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class KingdomSharedIntelligenceCurrentFactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_sees_only_explicit_safe_current_fact_projection(): void
    {
        $asOf = now()->startOfSecond();
        [$sourceOwner, $source] = $this->ownerAlliance('Current Source', 'current-source', 7601);
        [$recipientOwner, $recipient] = $this->ownerAlliance('Current Recipient', 'current-recipient', 7601);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);

        $sharedTracking = $this->tracking($sourceOwner, $source, 'ga-7601-a', 'Target Alpha', 'ALP');
        $sharedTracking->forceFill(['manager_notes' => 'SECRET MANAGER NOTE'])->save();
        $unsharedTracking = $this->tracking($sourceOwner, $source, 'ga-7601-b', 'Target Bravo', 'BRV');

        $capturedAt = $asOf->copy()->subDay();
        $this->observation(
            $sourceOwner,
            $source,
            $sharedTracking,
            'Observed Alpha',
            'OA',
            '123456789',
            88,
            $capturedAt,
        );
        $this->observation(
            $sourceOwner,
            $source,
            $unsharedTracking,
            'Observed Bravo',
            'OB',
            '999999999',
            99,
            $capturedAt,
        );

        $target = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $sharedTracking->id);

        $rows = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)
            ->forRecipient($recipient, $asOf);

        self::assertCount(1, $rows);
        self::assertSame(
            ['shareTargetId', 'sourceAlliance', 'gameAlliance', 'freshness', 'latestObservation'],
            array_keys($rows[0]),
        );
        self::assertSame(['id', 'name'], array_keys($rows[0]['sourceAlliance']));
        self::assertSame(['name', 'tag'], array_keys($rows[0]['gameAlliance']));
        self::assertSame(
            ['observedName', 'observedTag', 'power', 'memberCount', 'capturedAt'],
            array_keys($rows[0]['latestObservation']),
        );
        self::assertSame((string) $target->id, $rows[0]['shareTargetId']);
        self::assertSame(['id' => (string) $source->id, 'name' => 'Current Source'], $rows[0]['sourceAlliance']);
        self::assertSame(['name' => 'Observed Alpha', 'tag' => 'OA'], $rows[0]['gameAlliance']);
        self::assertSame('current', $rows[0]['freshness']);
        self::assertSame([
            'observedName' => 'Observed Alpha',
            'observedTag' => 'OA',
            'power' => '123456789',
            'memberCount' => 88,
            'capturedAt' => $capturedAt->toIso8601String(),
        ], $rows[0]['latestObservation']);

        $encoded = json_encode($rows, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('SECRET MANAGER NOTE', $encoded);
        self::assertStringNotContainsString((string) $sharedTracking->id, $encoded);
        self::assertStringNotContainsString((string) $unsharedTracking->id, $encoded);
        foreach (['manager_notes', 'actor_user_id', 'source_subscription_id', 'source_batch_id', 'source_adapter_key', 'invalidation_reason'] as $privateField) {
            self::assertStringNotContainsString($privateField, $encoded);
        }

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $source->id,
            'actor_user_id' => $sourceOwner->id,
            'event' => 'kingdoms.shared_intelligence_target_shared',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $recipient->id,
            'actor_user_id' => null,
            'event' => 'kingdoms.shared_intelligence_target_shared',
        ]);

        self::assertFalse(Route::has('alliance.kingdom-sharing.current.index'));
        self::assertFalse(Route::has('alliance.kingdom-sharing.history.index'));
    }

    public function test_current_projection_uses_latest_accepted_observation_and_existing_freshness_semantics(): void
    {
        $asOf = now()->startOfSecond();
        [$sourceOwner, $source] = $this->ownerAlliance('Accepted Source', 'accepted-source', 7602);
        [$recipientOwner, $recipient] = $this->ownerAlliance('Accepted Recipient', 'accepted-recipient', 7602);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $tracking = $this->tracking($sourceOwner, $source, 'ga-7602', 'Accepted Target', 'ACC');

        $old = $this->observation(
            $sourceOwner,
            $source,
            $tracking,
            'Old Accepted',
            'OLD',
            '100',
            10,
            $asOf->copy()->subDays(40),
        );
        $latest = $this->observation(
            $sourceOwner,
            $source,
            $tracking,
            'Latest Accepted',
            'NEW',
            '200',
            20,
            $asOf->copy()->subDay(),
        );
        $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);

        $query = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class);
        $before = $query->forRecipient($recipient, $asOf);
        self::assertSame('current', $before[0]['freshness']);
        self::assertSame('Latest Accepted', $before[0]['latestObservation']['observedName']);

        $this->app->make(InvalidateKingdomAllianceObservation::class)
            ->handle($source, $sourceOwner, (string) $tracking->id, (string) $latest->id, 'PRIVATE INVALIDATION REASON');

        $after = $query->forRecipient($recipient, $asOf);
        self::assertSame('stale', $after[0]['freshness']);
        self::assertSame('Old Accepted', $after[0]['latestObservation']['observedName']);
        self::assertSame($old->captured_at->toIso8601String(), $after[0]['latestObservation']['capturedAt']);
        self::assertStringNotContainsString('PRIVATE INVALIDATION REASON', json_encode($after, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('history', $after[0]);
    }

    public function test_target_mutations_are_source_scoped_and_removal_revocation_and_kingdom_drift_fail_closed(): void
    {
        [$sourceOwner, $source, $sourceSession] = $this->ownerAllianceWithSession('Boundary Source', 'boundary-source', 7603);
        [$recipientOwner, $recipient, $recipientSession] = $this->ownerAllianceWithSession('Boundary Recipient', 'boundary-recipient', 7603);
        [$otherOwner, $other, $otherSession] = $this->ownerAllianceWithSession('Boundary Other', 'boundary-other', 7603);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $tracking = $this->tracking($sourceOwner, $source, 'ga-7603', 'Boundary Target', 'BND');
        $this->observation($sourceOwner, $source, $tracking, 'Boundary Target', 'BND', '300', 30, now()->subDay());

        $staleSession = $sourceSession;
        $staleSession['auth.password_confirmed_at'] = 0;
        $this->actingAs($sourceOwner)->withSession($staleSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/targets/{$tracking->id}")
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/targets/{$tracking->id}")
            ->assertNotFound();
        $this->actingAs($otherOwner)->withSession($otherSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/targets/{$tracking->id}")
            ->assertNotFound();

        $this->actingAs($sourceOwner)->withSession($sourceSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/targets/{$tracking->id}")
            ->assertRedirect();
        $target = KingdomIntelligenceShareTarget::query()->sole();
        self::assertSame(KingdomIntelligenceShareTargetState::Active, $target->state);

        $query = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class);
        self::assertCount(1, $query->forRecipient($recipient));

        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/targets/{$target->id}/remove")
            ->assertNotFound();
        $this->actingAs($sourceOwner)->withSession($sourceSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/targets/{$target->id}/remove")
            ->assertRedirect();
        self::assertSame(KingdomIntelligenceShareTargetState::Removed, $target->refresh()->state);
        self::assertSame([], $query->forRecipient($recipient));

        $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);
        self::assertCount(1, $query->forRecipient($recipient));

        $this->app->make(RevokeKingdomIntelligenceShare::class)
            ->handle($source, $sourceOwner, (string) $share->id);
        self::assertSame([], $query->forRecipient($recipient));

        $replacement = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $replacement->id, (string) $tracking->id);
        self::assertCount(1, $query->forRecipient($recipient));

        $this->app->make(UpdateAllianceKingdom::class)->handle($recipient, $recipientOwner, 7604);
        self::assertSame(KingdomIntelligenceShareState::Declined, $replacement->refresh()->state);
        self::assertSame([], $query->forRecipient($recipient));

        $this->app->make(UpdateAllianceKingdom::class)->handle($recipient, $recipientOwner, 7603);
        self::assertSame(KingdomIntelligenceShareState::Declined, $replacement->refresh()->state);
        self::assertSame([], $query->forRecipient($recipient));

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $recipient->id,
            'actor_user_id' => $recipientOwner->id,
            'event' => 'kingdoms.shared_intelligence_context_invalidated',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $source->id,
            'actor_user_id' => null,
            'event' => 'kingdoms.shared_intelligence_context_invalidated',
        ]);
    }

    public function test_current_projection_is_bounded_to_two_select_queries_for_multiple_explicit_targets(): void
    {
        $asOf = now()->startOfSecond();
        [$sourceOwner, $source] = $this->ownerAlliance('Bounded Source', 'bounded-source', 7605);
        [$recipientOwner, $recipient] = $this->ownerAlliance('Bounded Recipient', 'bounded-recipient', 7605);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);

        for ($index = 1; $index <= 12; $index++) {
            $tracking = $this->tracking(
                $sourceOwner,
                $source,
                'ga-7605-'.$index,
                'Bounded Target '.$index,
                'B'.$index,
            );
            $this->observation(
                $sourceOwner,
                $source,
                $tracking,
                'Observed '.$index,
                'O'.$index,
                (string) (1000 + $index),
                50 + $index,
                $asOf->copy()->subDays($index),
            );
            $this->app->make(AddKingdomIntelligenceShareTarget::class)
                ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);
        }

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $rows = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)->forRecipient($recipient, $asOf);
        $queries = DB::connection()->getQueryLog();
        DB::connection()->disableQueryLog();

        $selects = array_values(array_filter(
            $queries,
            static fn (array $query): bool => preg_match('/^\s*select\b/i', (string) $query['query']) === 1,
        ));

        self::assertCount(12, $rows);
        self::assertLessThanOrEqual(2, count($selects));
        self::assertLessThanOrEqual(SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT, count($rows));
    }

    private function activeShare(
        User $sourceOwner,
        Alliance $source,
        User $recipientOwner,
        Alliance $recipient,
    ): KingdomIntelligenceShare {
        $issued = $this->app->make(CreateKingdomIntelligenceShareInvitation::class)->handle($source, $sourceOwner);

        return $this->app->make(AcceptKingdomIntelligenceShareInvitation::class)
            ->handle($recipient, $recipientOwner, $issued->token);
    }

    private function tracking(
        User $owner,
        Alliance $source,
        string $gameAllianceId,
        string $name,
        string $tag,
    ): TrackedKingdomAlliance {
        return $this->app->make(StartTrackingKingdomAlliance::class)->handle($source, $owner, [
            'game_alliance_id' => $gameAllianceId,
            'current_name' => $name,
            'current_tag' => $tag,
        ]);
    }

    private function observation(
        User $owner,
        Alliance $source,
        TrackedKingdomAlliance $tracking,
        string $name,
        string $tag,
        string $power,
        int $memberCount,
        Carbon $capturedAt,
    ): KingdomAllianceObservation {
        return $this->app->make(RecordKingdomAllianceObservation::class)->handle(
            $source,
            $owner,
            (string) $tracking->id,
            [
                'observed_name' => $name,
                'observed_tag' => $tag,
                'power' => $power,
                'member_count' => $memberCount,
                'captured_at' => $capturedAt->toIso8601String(),
            ],
        );
    }

    /** @return array{0: User, 1: Alliance} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance];
    }

    /** @return array{0: User, 1: Alliance, 2: array<string, mixed>} */
    private function ownerAllianceWithSession(string $name, string $slug, int $kingdom): array
    {
        [$owner, $alliance] = $this->ownerAlliance($name, $slug, $kingdom);

        return [$owner, $alliance, [
            (string) config('identity.active_alliance_session_key') => $alliance->id,
            'auth.password_confirmed_at' => time(),
        ]];
    }
}
