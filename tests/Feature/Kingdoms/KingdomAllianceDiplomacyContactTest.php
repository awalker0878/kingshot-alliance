<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomAllianceContactState;
use App\Domain\Kingdoms\Enums\KingdomAllianceDiplomacyState;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\KingdomAllianceDiplomacy;
use App\Domain\Kingdoms\Models\KingdomAllianceDiplomacyContact;
use App\Domain\Kingdoms\Models\KingdomAllianceDiplomacyTransition;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomAllianceDiplomacyContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_update_and_exact_update_retry_contact_idempotently(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Save', 'contact-save', 6401);
        $tracking = $this->track($owner, $alliance, $session, 'Contact Target', 'contact-target-6401');
        $verified = now()->subHour()->startOfSecond();
        $payload = $this->contactPayload('Coordinator One', 'discord', '@coordinator-one', $verified->toIso8601String());

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            $payload,
        )->assertRedirect();
        $contact = KingdomAllianceDiplomacyContact::query()->sole();

        $this->withSession($session)->patch(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts/{$contact->id}",
            $payload,
        )->assertRedirect();

        self::assertSame(1, KingdomAllianceDiplomacyContact::query()->count());
        self::assertSame(
            1,
            $this->eventCount('audit_events', 'event', 'kingdoms.diplomacy_contact_saved', $alliance->id),
        );

        $payload['game_role'] = 'R4 diplomat';
        $this->withSession($session)->patch(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts/{$contact->id}",
            $payload,
        )->assertRedirect();

        $contact->refresh();
        self::assertSame('R4 diplomat', $contact->game_role);
        self::assertSame('@coordinator-one', $contact->handle);
        self::assertSame(KingdomAllianceContactState::Active, $contact->state);
        self::assertSame(2, $this->eventCount('audit_events', 'event', 'kingdoms.diplomacy_contact_saved', $alliance->id));

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomAllianceDiplomacyContacts')
                ->where('contacts.0.displayName', 'Coordinator One')
                ->where('contacts.0.channelType', 'discord')
                ->where('contacts.0.handle', '@coordinator-one')
                ->where('contacts.0.gameRole', 'R4 diplomat')
                ->where('contacts.0.state', 'active'));
    }

    public function test_names_and_handles_do_not_merge_or_create_platform_or_player_identity(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Identity', 'contact-identity', 6402);
        $tracking = $this->track($owner, $alliance, $session, 'Identity Target', 'identity-target-6402');
        $userCount = User::query()->count();
        $membershipCount = AllianceMembership::query()->count();
        $playerCount = DB::table('players')->count();
        $payload = $this->contactPayload('Same Name', 'in_game', 'same-handle');

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            $payload,
        )->assertRedirect();
        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            $payload,
        )->assertRedirect();

        self::assertSame(2, KingdomAllianceDiplomacyContact::query()->count());
        self::assertSame($userCount, User::query()->count());
        self::assertSame($membershipCount, AllianceMembership::query()->count());
        self::assertSame($playerCount, DB::table('players')->count());
    }

    public function test_contact_changes_never_infer_or_transition_diplomacy(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Diplomacy', 'contact-diplomacy', 6403);
        $tracking = $this->track($owner, $alliance, $session, 'Diplomacy Target', 'diplomacy-target-6403');

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            [
                'state' => 'nap',
                'effective_at' => now()->subDay()->toIso8601String(),
            ],
        )->assertRedirect();

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            $this->contactPayload('Diplomat', 'discord', 'diplomat-handle'),
        )->assertRedirect();

        self::assertSame(KingdomAllianceDiplomacyState::Nap, KingdomAllianceDiplomacy::query()->sole()->current_state);
        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->count());
    }

    public function test_contact_directory_is_manager_private_and_member_payload_has_no_contact_data(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Privacy', 'contact-privacy', 6404);
        $tracking = $this->track($owner, $alliance, $session, 'Private Target', 'private-target-6404');
        $secretHandle = 'PRIVATE-HANDLE-6404';
        $secretNote = 'PRIVATE-CONTACT-NOTE-6404';

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            [
                ...$this->contactPayload('Private Contact', 'discord', $secretHandle),
                'manager_notes' => $secretNote,
            ],
        )->assertRedirect();

        $member = $this->member($alliance);
        $memberSession = $this->activePlayerSession($member->players()->sole());

        $this->actingAs($member)->withSession($memberSession)
            ->get('/alliance/kingdom-alliances')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)
                ->missing('tracking.0.contacts')
                ->missing('tracking.0.contactUrl')
                ->missing('tracking.0.contactHandle')
                ->missing('tracking.0.contactNotes'));

        $this->actingAs($member)->withSession($memberSession)
            ->get("/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts")
            ->assertForbidden();
    }

    public function test_private_contact_text_never_enters_audit_or_outbox_payloads(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Events', 'contact-events', 6405);
        $tracking = $this->track($owner, $alliance, $session, 'Event Target', 'event-target-6405');
        $secretName = 'PRIVATE-CONTACT-NAME-6405';
        $secretHandle = 'PRIVATE-HANDLE-6405';
        $secretNote = 'PRIVATE-NOTE-6405';

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            [
                ...$this->contactPayload($secretName, 'other_handle', $secretHandle),
                'manager_notes' => $secretNote,
            ],
        )->assertRedirect();

        $auditPayload = DB::table('audit_events')
            ->where('event', 'kingdoms.diplomacy_contact_saved')
            ->pluck('metadata')
            ->implode(' ');
        $outboxPayload = DB::table('outbox_messages')
            ->where('event_type', 'kingdoms.diplomacy_contact_saved')
            ->pluck('payload')
            ->implode(' ');

        foreach ([$secretName, $secretHandle, $secretNote] as $secret) {
            self::assertStringNotContainsString($secret, $auditPayload);
            self::assertStringNotContainsString($secret, $outboxPayload);
        }
    }

    public function test_cross_tenant_tracking_and_contact_ids_fail_closed(): void
    {
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('Contact Tenant A', 'contact-tenant-a', 6406);
        [$ownerB, $allianceB, $sessionB] = $this->ownerAlliance('Contact Tenant B', 'contact-tenant-b', 6406);
        $trackingA = $this->track($ownerA, $allianceA, $sessionA, 'Tenant A Target', 'tenant-a-target-6406');
        $trackingB = $this->track($ownerB, $allianceB, $sessionB, 'Tenant B Target', 'tenant-b-target-6406');

        $this->actingAs($ownerB)->withSession($sessionB)->post(
            "/alliance/kingdom-alliances/{$trackingB->id}/diplomacy/contacts",
            $this->contactPayload('Tenant B Contact', 'discord', 'tenant-b-handle'),
        )->assertRedirect();
        $contactB = KingdomAllianceDiplomacyContact::query()->where('alliance_id', $allianceB->id)->sole();

        $this->actingAs($ownerA)->withSession($sessionA)
            ->get("/alliance/kingdom-alliances/{$trackingB->id}/diplomacy/contacts")
            ->assertNotFound();
        $this->actingAs($ownerA)->withSession($sessionA)->patch(
            "/alliance/kingdom-alliances/{$trackingA->id}/diplomacy/contacts/{$contactB->id}",
            $this->contactPayload('Tampered', 'discord', 'tampered'),
        )->assertNotFound();
        $this->actingAs($ownerA)->withSession($sessionA)->post(
            "/alliance/kingdom-alliances/{$trackingA->id}/diplomacy/contacts/{$contactB->id}/deactivate",
        )->assertNotFound();

        $contactB->refresh();
        self::assertSame('Tenant B Contact', $contactB->display_name);
        self::assertSame(KingdomAllianceContactState::Active, $contactB->state);
    }

    public function test_deactivation_preserves_history_is_idempotent_and_inactive_contact_cannot_be_edited(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Lifecycle', 'contact-lifecycle', 6407);
        $tracking = $this->track($owner, $alliance, $session, 'Lifecycle Target', 'lifecycle-target-6407');
        $payload = $this->contactPayload('Historical Contact', 'in_game', 'historical-handle');

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            $payload,
        )->assertRedirect();
        $contact = KingdomAllianceDiplomacyContact::query()->sole();

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts/{$contact->id}/deactivate",
        )->assertRedirect();
        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts/{$contact->id}/deactivate",
        )->assertRedirect();

        $contact->refresh();
        self::assertSame(KingdomAllianceContactState::Inactive, $contact->state);
        self::assertNotNull($contact->deactivated_at);
        self::assertSame(1, KingdomAllianceDiplomacyContact::query()->count());
        self::assertSame(
            1,
            $this->eventCount('audit_events', 'event', 'kingdoms.diplomacy_contact_deactivated', $alliance->id),
        );

        $payload['handle'] = 'changed-after-inactive';
        $this->withSession($session)
            ->from("/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts")
            ->patch(
                "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts/{$contact->id}",
                $payload,
            )->assertSessionHasErrors('contact');
        self::assertSame('historical-handle', $contact->refresh()->handle);
    }

    public function test_contact_mutations_require_recent_password_confirmation(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Password', 'contact-password', 6408);
        $tracking = $this->track($owner, $alliance, $session, 'Password Target', 'password-target-6408');
        $staleSession = [
            (string) config('identity.active_player_session_key') => $owner->players()->sole()->id,
            'auth.password_confirmed_at' => 0,
        ];

        $this->actingAs($owner)->withSession($staleSession)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            $this->contactPayload('Blocked Contact', 'discord', 'blocked-handle'),
        )->assertRedirect(route('password.confirm'));

        self::assertSame(0, KingdomAllianceDiplomacyContact::query()->count());
    }

    public function test_kingdom_drift_preserves_contact_read_but_blocks_mutation(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Drift', 'contact-drift', 6409);
        $tracking = $this->track($owner, $alliance, $session, 'Drift Target', 'drift-target-6409');
        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            $this->contactPayload('Drift Contact', 'discord', 'drift-handle'),
        )->assertRedirect();
        $contact = KingdomAllianceDiplomacyContact::query()->sole();

        $newKingdom = Kingdom::query()->create(['number' => 6499, 'status' => 'active']);
        $tracking->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tracking.contextCurrent', false)
                ->where('contacts.0.handle', 'drift-handle'));

        $this->withSession($session)
            ->from("/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts")
            ->post(
                "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts/{$contact->id}/deactivate",
            )->assertSessionHasErrors('contact');
        self::assertSame(KingdomAllianceContactState::Active, $contact->refresh()->state);
    }

    public function test_archived_tracking_preserves_contacts_but_blocks_contact_changes(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Archive', 'contact-archive', 6410);
        $tracking = $this->track($owner, $alliance, $session, 'Archive Target', 'archive-target-6410');
        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
            $this->contactPayload('Archive Contact', 'in_game', 'archive-handle'),
        )->assertRedirect();
        $contact = KingdomAllianceDiplomacyContact::query()->sole();

        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/archive")->assertRedirect();

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tracking.state', 'archived')
                ->where('contacts.0.handle', 'archive-handle'));

        $this->withSession($session)
            ->from("/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts")
            ->post(
                "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts/{$contact->id}/deactivate",
            )->assertSessionHasErrors('contact');
    }

    public function test_future_last_verified_time_is_rejected(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Contact Date', 'contact-date', 6411);
        $tracking = $this->track($owner, $alliance, $session, 'Date Target', 'date-target-6411');

        $this->withSession($session)
            ->from("/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts")
            ->post(
                "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/contacts",
                $this->contactPayload(
                    'Future Contact',
                    'discord',
                    'future-handle',
                    now()->addHour()->toIso8601String(),
                ),
            )->assertSessionHasErrors('last_verified_at');

        self::assertSame(0, KingdomAllianceDiplomacyContact::query()->count());
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

    /** @param array<string, mixed> $session */
    private function track(
        User $owner,
        Alliance $alliance,
        array $session,
        string $name,
        string $stableId,
    ): TrackedKingdomAlliance {
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => $name,
                'game_alliance_id' => $stableId,
            ])->assertRedirect();

        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $alliance->id)
            ->latest('created_at')
            ->firstOrFail();
    }

    private function member(Alliance $alliance): User
    {
        $member = User::factory()->create();
        $player = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $alliance->kingdom_id,
            'game_player_id' => 'member-'.$member->id,
            'current_name' => 'Diplomacy Contact Member',
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
            (string) config('identity.active_player_session_key') => $player->id,
            'auth.password_confirmed_at' => time(),
        ];
    }

    /** @return array<string, mixed> */
    private function activePlayerSession(Player $player): array
    {
        return [(string) config('identity.active_player_session_key') => $player->id];
    }

    /** @return array<string, string|null> */
    private function contactPayload(
        string $displayName,
        string $channel,
        string $handle,
        ?string $lastVerifiedAt = null,
    ): array {
        return [
            'display_name' => $displayName,
            'game_role' => 'Diplomat',
            'channel_type' => $channel,
            'handle' => $handle,
            'last_verified_at' => $lastVerifiedAt,
            'manager_notes' => 'Manager-only coordination note',
        ];
    }

    private function eventCount(string $table, string $eventColumn, string $event, string $allianceId): int
    {
        return DB::table($table)
            ->where('alliance_id', $allianceId)
            ->where($eventColumn, $event)
            ->count();
    }
}
