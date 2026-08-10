<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\KingdomAlliance;
use App\Domain\Kingdoms\Models\KingdomAllianceDiplomacy;
use App\Domain\Kingdoms\Models\KingdomAllianceDiplomacyContact;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Services\KingdomAllianceIntelligence;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomAllianceIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_derives_bounded_trends_from_accepted_history_and_preserves_missing_vs_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 16:00:00 UTC'));
        [$owner, $alliance] = $this->ownerAlliance('Trend Alliance', 'trend-alliance', 6501);
        $alpha = $this->tracking($alliance, 'Alpha', 'ALP');
        $missing = $this->tracking($alliance, 'Missing', 'MIS');
        $zero = $this->tracking($alliance, 'Zero', 'ZER');

        $this->observation($alliance, $alpha, 35, 100, 10, 'alpha-35');
        $this->observation($alliance, $alpha, 8, 150, 12, 'alpha-8');
        $this->observation($alliance, $alpha, 3, null, 14, 'alpha-3');
        $this->observation($alliance, $alpha, 1, 200, 15, 'alpha-1');
        $invalidated = $this->observation($alliance, $alpha, 0, 999, 99, 'alpha-invalid');
        $invalidated->update(['invalidated_at' => now()]);

        $this->observation($alliance, $zero, 8, 0, 0, 'zero-8');
        $this->observation($alliance, $zero, 1, 0, 0, 'zero-1');

        KingdomAllianceDiplomacy::query()->create([
            'alliance_id' => $alliance->id,
            'tracked_kingdom_alliance_id' => $alpha->id,
            'kingdom_alliance_id' => $alpha->kingdom_alliance_id,
            'current_state' => 'nap',
            'effective_at' => now()->subDays(20),
            'review_at' => now()->subHour(),
            'expires_at' => now()->addDay(),
            'last_transition_user_id' => $owner->id,
        ]);

        $metrics = $this->app->make(KingdomAllianceIntelligence::class)->forAlliance(
            $alliance,
            false,
            $this->filters(),
            now(),
        );

        self::assertSame(3, $metrics['summary']['activeTrackedAlliances']);
        self::assertSame(2, $metrics['summary']['observationQuality']['current']);
        self::assertSame(0, $metrics['summary']['observationQuality']['stale']);
        self::assertSame(1, $metrics['summary']['observationQuality']['missing']);
        self::assertSame(1, $metrics['summary']['relationshipsNeedingReview']);
        self::assertSame(1, $metrics['summary']['diplomacyStates']['nap']);
        self::assertSame(2, $metrics['summary']['diplomacyStates']['unknown']);
        self::assertCount(3, $metrics['rows']);

        $alphaRow = $metrics['rows'][0];
        self::assertSame('Alpha', $alphaRow['name']);
        self::assertSame('200', $alphaRow['latestObservation']['power']);
        self::assertSame(15, $alphaRow['latestObservation']['memberCount']);
        self::assertNull($alphaRow['priorChange']['powerChange']);
        self::assertSame(1, $alphaRow['priorChange']['memberChange']);
        self::assertSame('50', $alphaRow['sevenDayChange']['powerChange']);
        self::assertSame(3, $alphaRow['sevenDayChange']['memberChange']);
        self::assertSame('100', $alphaRow['thirtyDayChange']['powerChange']);
        self::assertSame(5, $alphaRow['thirtyDayChange']['memberChange']);
        self::assertSame('nap', $alphaRow['diplomacy']['state']);
        self::assertTrue($alphaRow['diplomacy']['needsReview']);

        $missingRow = $metrics['rows'][1];
        self::assertSame('Missing', $missingRow['name']);
        self::assertSame('missing', $missingRow['freshness']);
        self::assertNull($missingRow['latestObservation']);
        self::assertNull($missingRow['sevenDayChange']);

        $zeroRow = $metrics['rows'][2];
        self::assertSame('Zero', $zeroRow['name']);
        self::assertSame('0', $zeroRow['latestObservation']['power']);
        self::assertSame(0, $zeroRow['latestObservation']['memberCount']);
        self::assertSame('0', $zeroRow['sevenDayChange']['powerChange']);
        self::assertSame(0, $zeroRow['sevenDayChange']['memberChange']);
    }

    public function test_bounded_window_rejects_too_old_or_too_new_substitutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 16:00:00 UTC'));
        [, $alliance] = $this->ownerAlliance('Window Alliance', 'window-alliance', 6502);
        $tracking = $this->tracking($alliance, 'Window Target', 'WIN');

        $this->observation($alliance, $tracking, 61, 100, 10, 'window-61');
        $this->observation($alliance, $tracking, 6, 180, 18, 'window-6');
        $this->observation($alliance, $tracking, 1, 200, 20, 'window-1');

        $metrics = $this->app->make(KingdomAllianceIntelligence::class)->forAlliance(
            $alliance,
            false,
            $this->filters(),
            now(),
        );
        $row = $metrics['rows'][0];

        self::assertNull($row['sevenDayChange']);
        self::assertNull($row['thirtyDayChange']);
        self::assertSame('20', $row['priorChange']['powerChange']);
        self::assertSame(2, $row['priorChange']['memberChange']);
    }

    public function test_member_payload_excludes_private_contact_diagnostics_and_manager_sees_diagnostics_without_contact_text(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 16:00:00 UTC'));
        [$owner, $alliance] = $this->ownerAlliance('Privacy Alliance', 'privacy-alliance', 6503);
        $tracking = $this->tracking($alliance, 'Private Target', 'PVT');
        $secretName = 'PRIVATE-CONTACT-NAME-6503';
        $secretHandle = 'PRIVATE-HANDLE-6503';
        $secretNote = 'PRIVATE-NOTE-6503';

        KingdomAllianceDiplomacyContact::query()->create([
            'alliance_id' => $alliance->id,
            'tracked_kingdom_alliance_id' => $tracking->id,
            'kingdom_alliance_id' => $tracking->kingdom_alliance_id,
            'display_name' => $secretName,
            'game_role' => 'Diplomat',
            'channel_type' => 'discord',
            'handle' => $secretHandle,
            'state' => 'active',
            'last_verified_at' => now()->subDays(31),
            'manager_notes' => $secretNote,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);

        $session = $this->session($alliance);
        $managerResponse = $this->actingAs($owner)->withSession($session)
            ->get('/alliance/kingdom-alliances/intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomAllianceIntelligence')
                ->where('canManage', true)
                ->where('intelligence.managerSummary.trackedWithActiveContact', 1)
                ->where('intelligence.managerSummary.trackedWithVerificationDue', 1)
                ->where('intelligence.rows.0.contactDiagnostics.activeContacts', 1)
                ->where('intelligence.rows.0.contactDiagnostics.verificationDue', 1));

        $managerContent = $managerResponse->getContent();
        self::assertStringNotContainsString($secretName, $managerContent);
        self::assertStringNotContainsString($secretHandle, $managerContent);
        self::assertStringNotContainsString($secretNote, $managerContent);

        $member = $this->member($alliance);
        $this->actingAs($member)->withSession($session)
            ->get('/alliance/kingdom-alliances/intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)
                ->where('intelligence.managerSummary', null)
                ->missing('intelligence.rows.0.contactDiagnostics')
                ->missing('intelligence.rows.0.contactsUrl')
                ->missing('intelligence.rows.0.diplomacyUrl'));
    }

    public function test_dashboard_isolates_tenants_and_filters_rows_without_changing_active_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 16:00:00 UTC'));
        [$ownerA, $allianceA] = $this->ownerAlliance('Tenant A', 'tenant-a-intelligence', 6504);
        [, $allianceB] = $this->ownerAlliance('Tenant B', 'tenant-b-intelligence', 6504);
        $current = $this->tracking($allianceA, 'Current A', 'CRA');
        $missing = $this->tracking($allianceA, 'Missing A', 'MSA');
        $otherTenant = $this->tracking($allianceB, 'OTHER-TENANT-SECRET-6504', 'OTS');
        $this->observation($allianceA, $current, 1, 100, 10, 'tenant-a-current');
        $this->observation($allianceB, $otherTenant, 1, 999, 99, 'tenant-b-current');

        $response = $this->actingAs($ownerA)->withSession($this->session($allianceA))
            ->get('/alliance/kingdom-alliances/intelligence?tracking=active&freshness=missing&sort=power&direction=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('intelligence.summary.activeTrackedAlliances', 2)
                ->where('intelligence.summary.observationQuality.current', 1)
                ->where('intelligence.summary.observationQuality.missing', 1)
                ->has('intelligence.rows', 1)
                ->where('intelligence.rows.0.name', 'Missing A')
                ->where('intelligence.filters.freshness', 'missing')
                ->where('intelligence.filters.sort', 'power')
                ->where('intelligence.filters.direction', 'desc'));

        self::assertStringNotContainsString('OTHER-TENANT-SECRET-6504', $response->getContent());
        self::assertSame('Missing A', $missing->kingdomAlliance->current_name);
    }

    /** @return array{0: User, 1: Alliance} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance];
    }

    private function tracking(Alliance $alliance, string $name, string $tag): TrackedKingdomAlliance
    {
        self::assertNotNull($alliance->kingdom_id);
        $reference = KingdomAlliance::query()->create([
            'kingdom_id' => $alliance->kingdom_id,
            'game_alliance_id' => strtolower($tag).'-'.$alliance->id,
            'current_name' => $name,
            'current_tag' => $tag,
            'status' => 'active',
        ]);

        return TrackedKingdomAlliance::query()->create([
            'alliance_id' => $alliance->id,
            'kingdom_alliance_id' => $reference->id,
            'kingdom_id' => $alliance->kingdom_id,
            'state' => 'active',
        ]);
    }

    private function observation(
        Alliance $alliance,
        TrackedKingdomAlliance $tracking,
        int $daysAgo,
        ?int $power,
        ?int $memberCount,
        string $key,
    ): KingdomAllianceObservation {
        return KingdomAllianceObservation::query()->create([
            'alliance_id' => $alliance->id,
            'tracked_kingdom_alliance_id' => $tracking->id,
            'kingdom_alliance_id' => $tracking->kingdom_alliance_id,
            'observed_name' => $tracking->kingdomAlliance->current_name,
            'observed_tag' => $tracking->kingdomAlliance->current_tag,
            'power' => $power,
            'member_count' => $memberCount,
            'captured_at' => now()->subDays($daysAgo),
            'source' => 'manual',
            'idempotency_key' => hash('sha256', $alliance->id.'|'.$key),
        ]);
    }

    private function member(Alliance $alliance): User
    {
        $member = User::factory()->create();
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $member->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $role = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($role->id, ['alliance_id' => $alliance->id]);

        return $member;
    }

    /** @return array<string, mixed> */
    private function session(Alliance $alliance): array
    {
        return [(string) config('identity.active_alliance_session_key') => $alliance->id];
    }

    /** @return array{tracking: string, freshness: string, diplomacy: string, sort: string, direction: string} */
    private function filters(): array
    {
        return [
            'tracking' => 'active',
            'freshness' => 'all',
            'diplomacy' => 'all',
            'sort' => 'name',
            'direction' => 'asc',
        ];
    }
}
