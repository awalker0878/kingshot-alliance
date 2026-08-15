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
use App\Contexts\GameWorld\Models\KingdomAlliance;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacy;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacyContact;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacyTransition;
use App\Contexts\GameWorld\Models\KingdomAllianceObservation;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Enums\KingdomAllianceDiplomacyState;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomAllianceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_whole_increment_preserves_identity_tenancy_history_privacy_and_drift_boundaries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 16:00:00 UTC'));

        [$ownerA, $ownerPlayerA, $allianceA, $sessionA] = $this->ownerAlliance('Acceptance A', 'acceptance-a', 6601);
        [$ownerB, $ownerPlayerB, $allianceB, $sessionB] = $this->ownerAlliance('Acceptance B', 'acceptance-b', 6601);

        $trackingSecret = 'PRIVATE-TRACKING-6601';
        $correctionSecret = 'PRIVATE-CORRECTION-6601';
        $termsSecret = 'PRIVATE-TERMS-6601';
        $rationaleSecret = 'PRIVATE-RATIONALE-6601';
        $contactNameSecret = 'PRIVATE-CONTACT-NAME-6601';
        $contactHandleSecret = 'PRIVATE-CONTACT-HANDLE-6601';
        $contactNoteSecret = 'PRIVATE-CONTACT-NOTE-6601';

        $this->actingAs($ownerA)->withSession($sessionA)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Northern Compact',
                'current_tag' => 'NC',
                'game_alliance_id' => 'shared-external-alliance-6601',
                'manager_notes' => $trackingSecret,
            ])->assertRedirect();

        $trackingA = TrackedKingdomAlliance::query()->where('alliance_id', $allianceA->id)->sole();

        $this->actingAs($ownerB)->withSession($sessionB)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Northern Compact',
                'current_tag' => 'NC',
                'game_alliance_id' => 'shared-external-alliance-6601',
                'manager_notes' => 'PRIVATE-TRACKING-B-6601',
            ])->assertRedirect();

        $trackingB = TrackedKingdomAlliance::query()->where('alliance_id', $allianceB->id)->sole();

        self::assertSame(1, KingdomAlliance::query()->where('game_alliance_id', 'shared-external-alliance-6601')->count());
        self::assertSame($trackingA->kingdom_alliance_id, $trackingB->kingdom_alliance_id);
        self::assertNotSame($trackingA->id, $trackingB->id);

        foreach ([
            [35, '100', 10, 'Northern Compact Historical'],
            [8, '150', 12, 'Northern Compact Recent'],
            [1, '999', 99, 'Northern Compact Incorrect'],
        ] as [$daysAgo, $power, $members, $name]) {
            $this->actingAs($ownerA)->withSession($sessionA)
                ->post("/alliance/kingdom-alliances/{$trackingA->id}/observations", [
                    'observed_name' => $name,
                    'observed_tag' => 'NC',
                    'power' => $power,
                    'member_count' => $members,
                    'captured_at' => now()->subDays($daysAgo)->toIso8601String(),
                ])->assertRedirect();
        }

        $incorrect = KingdomAllianceObservation::query()
            ->where('alliance_id', $allianceA->id)
            ->where('power', 999)
            ->sole();

        $this->actingAs($ownerA)->withSession($sessionA)
            ->post("/alliance/kingdom-alliances/{$trackingA->id}/observations", [
                'observed_name' => 'Northern Compact Corrected',
                'observed_tag' => 'NC',
                'power' => '200',
                'member_count' => 15,
                'captured_at' => now()->subHours(12)->toIso8601String(),
                'corrects_observation_id' => $incorrect->id,
                'correction_reason' => $correctionSecret,
            ])->assertRedirect();

        self::assertNotNull($incorrect->refresh()->invalidated_at);
        self::assertSame(4, KingdomAllianceObservation::query()->where('alliance_id', $allianceA->id)->count());
        self::assertSame(3, KingdomAllianceObservation::query()
            ->where('alliance_id', $allianceA->id)
            ->whereNull('invalidated_at')
            ->count());

        $this->actingAs($ownerA)->withSession($sessionA)
            ->post("/alliance/kingdom-alliances/{$trackingA->id}/diplomacy/transitions", [
                'state' => 'nap',
                'effective_at' => now()->subDays(10)->toIso8601String(),
                'review_at' => now()->subHour()->toIso8601String(),
                'expires_at' => now()->addDays(5)->toIso8601String(),
                'terms' => $termsSecret,
                'rationale' => $rationaleSecret,
            ])->assertRedirect();

        $this->actingAs($ownerA)->withSession($sessionA)
            ->post("/alliance/kingdom-alliances/{$trackingA->id}/diplomacy/contacts", [
                'display_name' => $contactNameSecret,
                'game_role' => 'Diplomat',
                'channel_type' => 'discord',
                'handle' => $contactHandleSecret,
                'last_verified_at' => now()->subDays(31)->toIso8601String(),
                'manager_notes' => $contactNoteSecret,
            ])->assertRedirect();

        self::assertSame(
            KingdomAllianceDiplomacyState::Nap,
            KingdomAllianceDiplomacy::query()->where('alliance_id', $allianceA->id)->sole()->current_state,
        );
        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->where('alliance_id', $allianceA->id)->count());
        self::assertSame(1, KingdomAllianceDiplomacyContact::query()->where('alliance_id', $allianceA->id)->count());

        $managerResponse = $this->actingAs($ownerA)->withSession($sessionA)
            ->get('/alliance/kingdom-alliances/intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomAllianceIntelligence')
                ->where('canManage', true)
                ->where('intelligence.summary.activeTrackedAlliances', 1)
                ->where('intelligence.summary.observationQuality.current', 1)
                ->where('intelligence.summary.diplomacyStates.nap', 1)
                ->where('intelligence.summary.relationshipsNeedingReview', 1)
                ->where('intelligence.managerSummary.trackedWithActiveContact', 1)
                ->where('intelligence.managerSummary.trackedWithVerificationDue', 1)
                ->has('intelligence.rows', 1)
                ->where('intelligence.rows.0.latestObservation.power', '200')
                ->where('intelligence.rows.0.sevenDayChange.powerChange', '50')
                ->where('intelligence.rows.0.thirtyDayChange.powerChange', '100')
                ->where('intelligence.rows.0.diplomacy.state', 'nap'));

        foreach ($this->privateStrings(
            $trackingSecret,
            $correctionSecret,
            $termsSecret,
            $rationaleSecret,
            $contactNameSecret,
            $contactHandleSecret,
            $contactNoteSecret,
        ) as $secret) {
            self::assertStringNotContainsString($secret, $managerResponse->getContent());
        }

        [$member, $memberPlayer] = $this->member($allianceA);
        $memberResponse = $this->actingAs($member)->withSession($this->activePlayerSession($memberPlayer))
            ->get('/alliance/kingdom-alliances/intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)
                ->where('intelligence.summary.activeTrackedAlliances', 1)
                ->where('intelligence.summary.diplomacyStates.nap', 1)
                ->where('intelligence.rows.0.latestObservation.power', '200')
                ->where('intelligence.managerSummary', null)
                ->missing('intelligence.rows.0.contactDiagnostics')
                ->missing('intelligence.rows.0.contactsUrl')
                ->missing('intelligence.rows.0.diplomacyUrl'));

        foreach ($this->privateStrings(
            $trackingSecret,
            $correctionSecret,
            $termsSecret,
            $rationaleSecret,
            $contactNameSecret,
            $contactHandleSecret,
            $contactNoteSecret,
        ) as $secret) {
            self::assertStringNotContainsString($secret, $memberResponse->getContent());
        }

        $otherTenantResponse = $this->actingAs($ownerB)->withSession($sessionB)
            ->get('/alliance/kingdom-alliances/intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', true)
                ->where('intelligence.summary.activeTrackedAlliances', 1)
                ->where('intelligence.summary.observationQuality.missing', 1)
                ->where('intelligence.summary.diplomacyStates.unknown', 1)
                ->where('intelligence.managerSummary.trackedWithActiveContact', 0)
                ->where('intelligence.rows.0.latestObservation', null));

        foreach ($this->privateStrings(
            $trackingSecret,
            $correctionSecret,
            $termsSecret,
            $rationaleSecret,
            $contactNameSecret,
            $contactHandleSecret,
            $contactNoteSecret,
        ) as $secret) {
            self::assertStringNotContainsString($secret, $otherTenantResponse->getContent());
        }

        $auditPayload = DB::table('audit_events')
            ->where('alliance_id', $allianceA->id)
            ->where('event', 'like', 'kingdoms.%')
            ->pluck('metadata')
            ->implode(' ');
        $outboxPayload = DB::table('outbox_messages')
            ->where('alliance_id', $allianceA->id)
            ->where('event_type', 'like', 'kingdoms.%')
            ->pluck('payload')
            ->implode(' ');

        foreach ($this->privateStrings(
            $trackingSecret,
            $correctionSecret,
            $termsSecret,
            $rationaleSecret,
            $contactNameSecret,
            $contactHandleSecret,
            $contactNoteSecret,
        ) as $secret) {
            self::assertStringNotContainsString($secret, $auditPayload);
            self::assertStringNotContainsString($secret, $outboxPayload);
        }

        $newKingdom = Kingdom::query()->create(['number' => 6699, 'status' => 'active']);
        $trackingA->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->actingAs($ownerA)->withSession($sessionA)
            ->get("/alliance/kingdom-alliances/{$trackingA->id}/history")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tracking.contextCurrent', false));
        $this->actingAs($ownerA)->withSession($sessionA)
            ->get("/alliance/kingdom-alliances/{$trackingA->id}/diplomacy")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tracking.contextCurrent', false));
        $this->actingAs($ownerA)->withSession($sessionA)
            ->get("/alliance/kingdom-alliances/{$trackingA->id}/diplomacy/contacts")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tracking.contextCurrent', false));

        $this->actingAs($ownerA)->withSession($sessionA)
            ->from("/alliance/kingdom-alliances/{$trackingA->id}/history")
            ->post("/alliance/kingdom-alliances/{$trackingA->id}/observations", [
                'observed_name' => 'Blocked after drift',
                'captured_at' => now()->toIso8601String(),
            ])->assertSessionHasErrors('observation');
        $this->actingAs($ownerA)->withSession($sessionA)
            ->from("/alliance/kingdom-alliances/{$trackingA->id}/diplomacy")
            ->post("/alliance/kingdom-alliances/{$trackingA->id}/diplomacy/transitions", [
                'state' => 'ally',
                'effective_at' => now()->toIso8601String(),
            ])->assertSessionHasErrors('diplomacy');
        $this->actingAs($ownerA)->withSession($sessionA)
            ->from("/alliance/kingdom-alliances/{$trackingA->id}/diplomacy/contacts")
            ->post("/alliance/kingdom-alliances/{$trackingA->id}/diplomacy/contacts", [
                'display_name' => 'Blocked Contact',
                'game_role' => 'Diplomat',
                'channel_type' => 'discord',
                'handle' => 'blocked-after-drift',
            ])->assertSessionHasErrors('contact');

        self::assertSame(4, KingdomAllianceObservation::query()->where('alliance_id', $allianceA->id)->count());
        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->where('alliance_id', $allianceA->id)->count());
        self::assertSame(1, KingdomAllianceDiplomacyContact::query()->where('alliance_id', $allianceA->id)->count());

        $this->actingAs($ownerA)->withSession($sessionA)
            ->post("/alliance/kingdom-alliances/{$trackingA->id}/archive")
            ->assertRedirect();

        self::assertSame(TrackedKingdomAllianceState::Archived, $trackingA->refresh()->state);
        self::assertNotNull($trackingA->archived_at);
    }

    /** @return array{0: User, 1: Player, 2: Alliance, 3: array<string, mixed>} */
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

        return [$owner, $player, $alliance, $this->confirmedSession($player)];
    }

    /** @return array{0: User, 1: Player} */
    private function member(Alliance $alliance): array
    {
        $member = User::factory()->create();
        $player = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $alliance->kingdom_id,
            'game_player_id' => 'member-'.$member->id,
            'current_name' => 'Acceptance Member',
        ]);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $player->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        return [$member, $player];
    }

    /** @return array<string, mixed> */
    private function confirmedSession(Player $player): array
    {
        return [
            (string) config('game_world.active_player_session_key') => $player->id,
            'auth.password_confirmed_at' => time(),
        ];
    }

    /** @return array<string, mixed> */
    private function activePlayerSession(Player $player): array
    {
        return [(string) config('game_world.active_player_session_key') => $player->id];
    }

    /** @return list<string> */
    private function privateStrings(string ...$values): array
    {
        return $values;
    }
}
