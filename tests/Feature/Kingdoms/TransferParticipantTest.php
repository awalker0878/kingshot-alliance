<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\KingdomPlayer;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_staying_participant_uses_same_alliance_roster_identity_and_home_kingdom(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Staying Transfer', 'staying-transfer', 5201);
        $plan = $this->plan($alliance, $owner, 'Staying plan');
        $roster = $this->roster($alliance, $owner, 'Alpha', 'alpha-1');

        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'staying',
                'roster_entry_id' => $roster->id,
                'manager_notes' => 'Private coordination note',
            ])
            ->assertRedirect();

        $participant = TransferParticipant::query()->sole();
        self::assertSame('staying', $participant->direction->value);
        self::assertSame($roster->id, $participant->roster_entry_id);
        self::assertSame($roster->kingdom_player_id, $participant->kingdom_player_id);
        self::assertSame($plan->home_kingdom_id, $participant->source_kingdom_id);
        self::assertNull($participant->destination_kingdom_id);
    }

    public function test_outgoing_destination_may_be_undecided_then_set_to_another_active_kingdom(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Outgoing Transfer', 'outgoing-transfer', 5202);
        $plan = $this->plan($alliance, $owner, 'Outgoing plan');
        $roster = $this->roster($alliance, $owner, 'Bravo', 'bravo-1');

        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'outgoing',
                'roster_entry_id' => $roster->id,
            ])
            ->assertRedirect();

        $participant = TransferParticipant::query()->sole();
        self::assertNull($participant->destination_kingdom_id);

        $this->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}", [
                'direction' => 'outgoing',
                'roster_entry_id' => $roster->id,
                'destination_kingdom' => 5299,
            ])
            ->assertRedirect();

        $destination = Kingdom::query()->where('number', 5299)->sole();
        self::assertSame($destination->id, $participant->refresh()->destination_kingdom_id);
        self::assertSame($plan->home_kingdom_id, $participant->source_kingdom_id);
    }

    public function test_outgoing_destination_cannot_be_the_plan_home_kingdom(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Same Destination', 'same-destination', 5203);
        $plan = $this->plan($alliance, $owner, 'Same destination plan');
        $roster = $this->roster($alliance, $owner, 'Charlie');

        $this->actingAs($owner)
            ->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'outgoing',
                'roster_entry_id' => $roster->id,
                'destination_kingdom' => 5203,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('destination_kingdom');

        self::assertSame(0, TransferParticipant::query()->count());
    }

    public function test_incoming_participant_can_exist_without_roster_or_site_membership(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Incoming Transfer', 'incoming-transfer', 5204);
        $plan = $this->plan($alliance, $owner, 'Incoming plan');

        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Delta',
            ])
            ->assertRedirect();

        $participant = TransferParticipant::query()->sole();
        self::assertNull($participant->roster_entry_id);
        self::assertNull($participant->membership_id);
        self::assertNull($participant->kingdom_player_id);
        self::assertNull($participant->source_kingdom_id);
        self::assertSame($plan->home_kingdom_id, $participant->destination_kingdom_id);
    }

    public function test_incoming_source_and_stable_id_resolve_neutral_identity_without_changing_destination(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Known Incoming', 'known-incoming', 5205);
        $plan = $this->plan($alliance, $owner, 'Known incoming plan');

        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Echo',
                'game_player_id' => 'echo-77',
                'source_kingdom' => 5277,
                'destination_kingdom' => 9999,
            ])
            ->assertRedirect();

        $source = Kingdom::query()->where('number', 5277)->sole();
        $player = KingdomPlayer::query()
            ->where('kingdom_id', $source->id)
            ->where('game_player_id', 'echo-77')
            ->sole();
        $participant = TransferParticipant::query()->sole();

        self::assertSame($player->id, $participant->kingdom_player_id);
        self::assertSame($source->id, $participant->source_kingdom_id);
        self::assertSame($plan->home_kingdom_id, $participant->destination_kingdom_id);
        self::assertSame($source->id, $player->kingdom_id);
    }

    public function test_display_name_alone_never_merges_incoming_identity(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Name Collision', 'name-collision', 5206);
        $plan = $this->plan($alliance, $owner, 'Collision plan');

        $this->actingAs($owner)->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Same Name',
                'source_kingdom' => 5266,
            ])->assertRedirect();

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Same Name',
                'source_kingdom' => 5266,
            ])->assertRedirect();

        self::assertSame(2, TransferParticipant::query()->where('observed_name', 'Same Name')->count());
        self::assertSame(0, KingdomPlayer::query()->where('current_name', 'Same Name')->count());
    }

    public function test_cross_alliance_roster_membership_and_participant_ids_fail_closed(): void
    {
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('Transfer Tenant A', 'transfer-tenant-a-b', 5207);
        [$ownerB, $allianceB, $sessionB] = $this->ownerAlliance('Transfer Tenant B', 'transfer-tenant-b-b', 5208);
        $planA = $this->plan($allianceA, $ownerA, 'Plan A');
        $planB = $this->plan($allianceB, $ownerB, 'Plan B');
        $rosterB = $this->roster($allianceB, $ownerB, 'Foreign Roster');

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$planA->id}/participants", [
                'direction' => 'staying',
                'roster_entry_id' => $rosterB->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('roster_entry_id');

        $membershipB = AllianceMembership::query()
            ->where('alliance_id', $allianceB->id)
            ->where('user_id', $ownerB->id)
            ->sole();

        $this->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$planA->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Foreign Member',
                'membership_id' => $membershipB->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('membership_id');

        $participantB = $this->createIncomingViaHttp($ownerB, $sessionB, $planB, 'Tenant B Player');

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->patch("/alliance/transfers/{$planA->id}/participants/{$participantB->id}", [
                'direction' => 'incoming',
                'name' => 'Tampered',
            ])
            ->assertNotFound();
    }

    public function test_member_payload_excludes_manager_notes_and_member_cannot_mutate(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Private Transfer', 'private-transfer', 5209);
        $plan = $this->plan($alliance, $owner, 'Private plan');
        $this->createIncomingViaHttp($owner, $session, $plan, 'Foxtrot', 'Leadership only');

        $member = User::factory()->create();
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $member->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $memberRole = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($memberRole->id, ['alliance_id' => $alliance->id]);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($member)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferPlans')
                ->where('participants.0.name', 'Foxtrot')
                ->missing('participants.0.managerNotes'));

        $this->withSession($this->confirmedSession($alliance->id))
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Unauthorized',
            ])
            ->assertForbidden();
    }

    public function test_participant_mutation_requires_recent_password_confirmation(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Participant Password', 'participant-password', 5210);
        $plan = $this->plan($alliance, $owner, 'Password plan');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Golf',
            ])
            ->assertRedirect(route('password.confirm'));

        self::assertSame(0, TransferParticipant::query()->count());
    }

    public function test_locked_plan_and_home_kingdom_drift_block_participant_mutations(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Blocked Transfer', 'blocked-transfer', 5211);
        $plan = $this->plan($alliance, $owner, 'Blocked plan');
        $roster = $this->roster($alliance, $owner, 'Hotel');

        $this->actingAs($owner)->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/open")
            ->assertRedirect();
        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/lock")
            ->assertRedirect();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'staying',
                'roster_entry_id' => $roster->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('participant');

        $driftPlan = $this->plan($alliance, $owner, 'Drift plan');
        $newKingdom = Kingdom::query()->create(['number' => 5212, 'status' => 'active']);
        $alliance->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$driftPlan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'India',
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('participant');
    }

    public function test_withdraw_is_idempotent_and_identity_switches_require_recreate(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Withdraw Transfer', 'withdraw-transfer', 5213);
        $plan = $this->plan($alliance, $owner, 'Withdraw plan');
        $roster = $this->roster($alliance, $owner, 'Juliet');
        $this->actingAs($owner)->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'outgoing',
                'roster_entry_id' => $roster->id,
            ])->assertRedirect();
        $participant = TransferParticipant::query()->sole();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}", [
                'direction' => 'incoming',
                'name' => 'Different identity',
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('direction');

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants/{$participant->id}/withdraw")
            ->assertRedirect();

        $auditCount = $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.transfer_participant_withdrawn',
            $alliance->id,
        );
        $outboxCount = $this->eventCount(
            'outbox_messages',
            'event_type',
            'kingdoms.transfer_participant_withdrawn',
            $alliance->id,
        );

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants/{$participant->id}/withdraw")
            ->assertRedirect();

        self::assertSame($auditCount, $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.transfer_participant_withdrawn',
            $alliance->id,
        ));
        self::assertSame($outboxCount, $this->eventCount(
            'outbox_messages',
            'event_type',
            'kingdoms.transfer_participant_withdrawn',
            $alliance->id,
        ));
    }

    /** @return array{0: User, 1: Alliance, 2: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance, $this->confirmedSession($alliance->id)];
    }

    private function plan(Alliance $alliance, User $owner, string $label): TransferPlan
    {
        return $this->app->make(CreateTransferPlan::class)->handle($alliance, $owner, ['label' => $label]);
    }

    private function roster(
        Alliance $alliance,
        User $owner,
        string $name,
        ?string $gamePlayerId = null,
    ): AllianceRosterEntry {
        return $this->app->make(SaveRosterEntry::class)->handle($alliance, $owner, [
            'name' => $name,
            'game_player_id' => $gamePlayerId,
            'state' => RosterState::Active,
        ]);
    }

    private function createIncomingViaHttp(
        User $owner,
        array $session,
        TransferPlan $plan,
        string $name,
        ?string $notes = null,
    ): TransferParticipant {
        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => $name,
                'manager_notes' => $notes,
            ])
            ->assertRedirect();

        return TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('observed_name', $name)
            ->sole();
    }

    /** @return array<string, mixed> */
    private function confirmedSession(string $allianceId): array
    {
        return [
            (string) config('identity.active_alliance_session_key') => $allianceId,
            'auth.password_confirmed_at' => time(),
        ];
    }

    private function eventCount(string $table, string $column, string $event, string $allianceId): int
    {
        return (int) DB::table($table)
            ->where('alliance_id', $allianceId)
            ->where($column, $event)
            ->count();
    }
}
