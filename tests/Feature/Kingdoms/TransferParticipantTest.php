<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Enums\KingdomStatus;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_staying_participant_uses_same_roster_player_and_home_kingdom(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Staying Transfer', 'staying-transfer', 5201);
        $plan = $this->plan($alliance, $ownerPlayer, 'Staying plan');
        $roster = $this->roster($alliance, $ownerPlayer, 'Alpha', 'alpha-1');

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
        self::assertSame($roster->player_id, $participant->player_id);
        self::assertSame($plan->home_kingdom_id, $participant->source_kingdom_id);
        self::assertNull($participant->destination_kingdom_id);
    }

    public function test_outgoing_destination_may_be_undecided_then_set_to_another_active_kingdom(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Outgoing Transfer', 'outgoing-transfer', 5202);
        $plan = $this->plan($alliance, $ownerPlayer, 'Outgoing plan');
        $roster = $this->roster($alliance, $ownerPlayer, 'Bravo', 'bravo-1');

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
        self::assertSame($roster->player_id, $participant->player_id);
    }

    public function test_outgoing_destination_cannot_be_the_plan_home_kingdom(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Same Destination', 'same-destination', 5203);
        $plan = $this->plan($alliance, $ownerPlayer, 'Same destination plan');
        $roster = $this->roster($alliance, $ownerPlayer, 'Charlie');

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

    public function test_incoming_participant_requires_source_and_establishes_durable_player_before_roster_or_membership(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Incoming Transfer', 'incoming-transfer', 5204);
        $plan = $this->plan($alliance, $ownerPlayer, 'Incoming plan');

        $this->actingAs($owner)
            ->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Delta',
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('source_kingdom');

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Delta',
                'source_kingdom' => 5274,
            ])
            ->assertRedirect();

        $source = Kingdom::query()->where('number', 5274)->sole();
        $participant = TransferParticipant::query()->sole();
        $player = Player::query()->findOrFail($participant->player_id);

        self::assertNull($participant->roster_entry_id);
        self::assertSame($source->id, $participant->source_kingdom_id);
        self::assertSame($plan->home_kingdom_id, $participant->destination_kingdom_id);
        self::assertSame($source->id, $player->current_kingdom_id);
        self::assertSame('Delta', $player->current_name);
        self::assertNull($player->user_id);
        self::assertFalse(AllianceMembership::query()->where('player_id', $player->id)->exists());
    }

    public function test_incoming_source_and_stable_id_reuse_player_without_changing_destination(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Known Incoming', 'known-incoming', 5205);
        $plan = $this->plan($alliance, $ownerPlayer, 'Known incoming plan');
        $source = Kingdom::query()->create(['number' => 5277, 'status' => KingdomStatus::Active]);
        $existing = Player::query()->create([
            'current_kingdom_id' => $source->id,
            'game_player_id' => 'echo-77',
            'current_name' => 'Echo Old',
        ]);

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

        $participant = TransferParticipant::query()->sole();
        self::assertSame($existing->id, $participant->player_id);
        self::assertSame($source->id, $participant->source_kingdom_id);
        self::assertSame($plan->home_kingdom_id, $participant->destination_kingdom_id);
        self::assertSame('Echo', $existing->refresh()->current_name);
        self::assertSame(1, Player::query()->where('game_player_id', 'echo-77')->count());
    }

    public function test_display_name_alone_never_merges_incoming_player_identity(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Name Collision', 'name-collision', 5206);
        $plan = $this->plan($alliance, $ownerPlayer, 'Collision plan');

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

        $participants = TransferParticipant::query()->where('observed_name', 'Same Name')->get();
        self::assertCount(2, $participants);
        self::assertNotSame($participants[0]->player_id, $participants[1]->player_id);
        self::assertSame(2, Player::query()->where('current_name', 'Same Name')->count());
    }

    public function test_cross_alliance_roster_and_participant_ids_fail_closed(): void
    {
        [$ownerA, $playerA, $allianceA, $sessionA] = $this->ownerAlliance('Transfer Tenant A', 'transfer-tenant-a-b', 5207);
        [$ownerB, $playerB, $allianceB, $sessionB] = $this->ownerAlliance('Transfer Tenant B', 'transfer-tenant-b-b', 5208);
        $planA = $this->plan($allianceA, $playerA, 'Plan A');
        $planB = $this->plan($allianceB, $playerB, 'Plan B');
        $rosterB = $this->roster($allianceB, $playerB, 'Foreign Roster');

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$planA->id}/participants", [
                'direction' => 'staying',
                'roster_entry_id' => $rosterB->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('roster_entry_id');

        $participantB = $this->createIncomingViaHttp($ownerB, $sessionB, $planB, 'Tenant B Player', 5288);

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->patch("/alliance/transfers/{$planA->id}/participants/{$participantB->id}", [
                'direction' => 'incoming',
                'name' => 'Tampered',
                'source_kingdom' => 5289,
            ])
            ->assertNotFound();
    }

    public function test_r1_member_payload_excludes_manager_notes_and_member_cannot_mutate(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Private Transfer', 'private-transfer', 5209);
        $plan = $this->plan($alliance, $ownerPlayer, 'Private plan');
        $this->createIncomingViaHttp($owner, $session, $plan, 'Foxtrot', 5280, 'Leadership only');

        $member = User::factory()->create();
        $memberPlayer = $this->player($member, $alliance->kingdom, 'private-transfer-member', 'Private Transfer Member');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $this->actingAs($member)
            ->withSession($this->activeSession($memberPlayer->id))
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferPlans')
                ->where('participants.0.name', 'Foxtrot')
                ->missing('participants.0.managerNotes'));

        $this->withSession($this->confirmedSession($memberPlayer->id))
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Unauthorized',
                'source_kingdom' => 5281,
            ])
            ->assertForbidden();
    }

    public function test_participant_mutation_requires_recent_password_confirmation(): void
    {
        [$owner, $ownerPlayer, $alliance] = $this->ownerAlliance('Participant Password', 'participant-password', 5210);
        $plan = $this->plan($alliance, $ownerPlayer, 'Password plan');

        $this->actingAs($owner)
            ->withSession($this->activeSession($ownerPlayer->id))
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'Golf',
                'source_kingdom' => 5290,
            ])
            ->assertRedirect(route('password.confirm'));

        self::assertSame(0, TransferParticipant::query()->count());
    }

    public function test_locked_plan_and_mismatched_plan_home_kingdom_block_participant_mutations(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Blocked Transfer', 'blocked-transfer', 5211);
        $plan = $this->plan($alliance, $ownerPlayer, 'Blocked plan');
        $roster = $this->roster($alliance, $ownerPlayer, 'Hotel');

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

        $mismatchPlan = $this->plan($alliance, $ownerPlayer, 'Mismatch plan');
        $otherKingdom = Kingdom::query()->create(['number' => 5212, 'status' => KingdomStatus::Active]);
        $mismatchPlan->forceFill(['home_kingdom_id' => $otherKingdom->id])->save();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$mismatchPlan->id}/participants", [
                'direction' => 'incoming',
                'name' => 'India',
                'source_kingdom' => 5213,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('participant');
    }

    public function test_participant_identity_cannot_change_in_place_and_withdraw_is_idempotent(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Withdraw Transfer', 'withdraw-transfer', 5214);
        $plan = $this->plan($alliance, $ownerPlayer, 'Withdraw plan');
        $roster = $this->roster($alliance, $ownerPlayer, 'Juliet');
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
                'source_kingdom' => 5294,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors();

        self::assertSame($roster->player_id, $participant->refresh()->player_id);

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants/{$participant->id}/withdraw")
            ->assertRedirect();

        $auditCount = $this->eventCount('audit_events', 'event', 'kingdoms.transfer_participant_withdrawn', $alliance->id);
        $outboxCount = $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_participant_withdrawn', $alliance->id);

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants/{$participant->id}/withdraw")
            ->assertRedirect();

        self::assertSame($auditCount, $this->eventCount('audit_events', 'event', 'kingdoms.transfer_participant_withdrawn', $alliance->id));
        self::assertSame($outboxCount, $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_participant_withdrawn', $alliance->id));
    }

    /** @return array{0: User, 1: Player, 2: Alliance, 3: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdomNumber): array
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => $kingdomNumber, 'status' => KingdomStatus::Active]);
        $ownerPlayer = $this->player($owner, $kingdom, $slug.'-r5', $name.' R5');
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, $name, $slug);

        return [$owner, $ownerPlayer, $alliance, $this->confirmedSession($ownerPlayer->id)];
    }

    private function plan(Alliance $alliance, Player $actor, string $label): TransferPlan
    {
        return $this->app->make(CreateTransferPlan::class)->handle($alliance, $actor, ['label' => $label]);
    }

    private function roster(
        Alliance $alliance,
        Player $actor,
        string $name,
        ?string $gamePlayerId = null,
    ): AllianceRosterEntry {
        return $this->app->make(SaveRosterEntry::class)->handle($alliance, $actor, [
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
        int $sourceKingdom,
        ?string $notes = null,
    ): TransferParticipant {
        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => $name,
                'source_kingdom' => $sourceKingdom,
                'manager_notes' => $notes,
            ])
            ->assertRedirect();

        return TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('observed_name', $name)
            ->sole();
    }

    private function player(User $user, Kingdom $kingdom, string $gamePlayerId, string $name): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }

    /** @return array<string, mixed> */
    private function activeSession(string $playerId): array
    {
        return [(string) config('game_world.active_player_session_key') => $playerId];
    }

    /** @return array<string, mixed> */
    private function confirmedSession(string $playerId): array
    {
        return [
            ...$this->activeSession($playerId),
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
