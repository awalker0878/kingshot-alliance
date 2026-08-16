<?php

declare(strict_types=1);

namespace Tests\Feature\Operations\Rosters;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Participation\Actions\RespondToEvent;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Rosters\Actions\AssignEventRosterPlayer;
use App\Contexts\Operations\Rosters\Actions\RespondToEventRosterAssignment;
use App\Contexts\Operations\Rosters\Actions\SaveEventRoster;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_swordland_materializes_combatants_and_substitutes_with_shared_assignment_group(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8301, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8301-r5',
            'current_name' => 'Swordland R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Swordland Roster', 'swordland-roster');
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $rosters = $occurrence->rosters()->orderBy('sort_order')->get();

        self::assertSame(['combatants', 'substitutes'], $rosters->pluck('key')->all());
        self::assertSame([30, 10], $rosters->pluck('capacity')->all());
        self::assertSame(['battlefield'], $rosters->pluck('assignment_group')->unique()->values()->all());
    }

    public function test_summit_materializes_two_legions_with_combatant_and_substitute_children(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8302, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8302-r5',
            'current_name' => 'Summit R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Summit Roster', 'summit-roster');
        $type = EventType::query()->where('slug', 'swordland-summit-league')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $rosters = $event->occurrences->firstOrFail()->rosters()->get()->keyBy('key');

        self::assertCount(6, $rosters);
        self::assertNull($rosters['legion-1']->parent_id);
        self::assertNull($rosters['legion-2']->parent_id);
        self::assertSame((string) $rosters['legion-1']->id, (string) $rosters['legion-1-combatants']->parent_id);
        self::assertSame((string) $rosters['legion-1']->id, (string) $rosters['legion-1-substitutes']->parent_id);
        self::assertSame(30, $rosters['legion-1-combatants']->capacity);
        self::assertSame(20, $rosters['legion-1-substitutes']->capacity);
        self::assertSame('league', $rosters['legion-2-combatants']->assignment_group);
    }

    public function test_assignment_moves_player_between_mutually_exclusive_rosters_and_records_live_warning(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8303, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8303-player',
            'current_name' => 'Move Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Roster Move', 'roster-move');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8303-player')->sole()->id,
            'observed_name' => 'Move Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $combatants = $occurrence->rosters()->where('key', 'combatants')->sole();
        $substitutes = $occurrence->rosters()->where('key', 'substitutes')->sole();

        $this->app->make(RespondToEvent::class)->handle($ownerPlayer, $occurrence, $ownerPlayer, EventResponseChoice::Unavailable);
        $assign = $this->app->make(AssignEventRosterPlayer::class);
        $firstAssignment = $assign->handle($ownerPlayer, $combatants, $ownerPlayer, slotNumber: 1);
        self::assertContains('unavailable', $firstAssignment->assignment_warnings);

        $secondAssignment = $assign->handle($ownerPlayer, $substitutes, $ownerPlayer, slotNumber: 1);
        self::assertSame(EventRosterMemberStatus::Removed, $firstAssignment->refresh()->status);
        self::assertSame(EventRosterMemberStatus::Assigned, $secondAssignment->status);
        self::assertSame(
            1,
            EventRosterMember::query()
                ->where('player_id', $ownerPlayer->id)
                ->whereIn('status', [EventRosterMemberStatus::Assigned->value, EventRosterMemberStatus::Confirmed->value])
                ->count(),
        );
    }

    public function test_decline_frees_capacity_and_slot_for_another_player(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'roster-capacity@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 8304, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8304-first',
            'current_name' => 'First Player',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8304-second',
            'current_name' => 'Second Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Roster Capacity', 'roster-capacity');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8304-first')->sole()->id,
            'observed_name' => 'First Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8304-second')->sole()->id,
            'observed_name' => 'Second Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $roster = $this->app->make(SaveEventRoster::class)->handle(
            $ownerPlayer,
            $occurrence,
            'main',
            EventRosterType::Roster,
            'battlefield',
            'Main',
            capacity: 1,
        );
        $assignment = $this->app->make(AssignEventRosterPlayer::class)->handle($ownerPlayer, $roster, $ownerPlayer, slotNumber: 1);

        $this->app->make(RespondToEventRosterAssignment::class)->handle(
            $ownerPlayer,
            $assignment,
            $ownerPlayer,
            EventRosterMemberStatus::Declined,
        );
        self::assertSame(EventRosterMemberStatus::Declined, $assignment->refresh()->status);

        $secondAssignment = $this->app->make(AssignEventRosterPlayer::class)->handle($ownerPlayer, $roster, $memberPlayer, slotNumber: 1);
        self::assertSame(EventRosterMemberStatus::Assigned, $secondAssignment->status);
    }

    public function test_self_confirmation_uses_active_player_and_cannot_impersonate_another_owned_player(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8305, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8305-first',
            'current_name' => 'Context One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8305-second',
            'current_name' => 'Context Two',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'Roster Context', 'roster-context');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8305-first')->sole()->id,
            'observed_name' => 'Context One',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8305-second')->sole()->id,
            'observed_name' => 'Context Two',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $firstPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $roster = $occurrence->rosters()->where('key', 'combatants')->sole();
        $assignment = $this->app->make(AssignEventRosterPlayer::class)->handle($firstPlayer, $roster, $secondPlayer);

        $this->actingAs($owner)
            ->withSession([(string) config('game_world.active_player_session_key') => (string) $firstPlayer->id])
            ->put('/events/'.$occurrence->id.'/roster-members/'.$assignment->id.'/response', ['status' => 'confirmed'])
            ->assertNotFound();

        self::assertSame(EventRosterMemberStatus::Assigned, $assignment->refresh()->status);
    }

    public function test_kingdom_roster_can_snapshot_players_from_different_alliances(): void
    {
        $adminUser = User::factory()->create();
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8307, 'status' => 'active']);
        $adminPlayer = Player::query()->create([
            'user_id' => $adminUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8307-admin',
            'current_name' => 'Kingdom Administrator',
        ]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8307-a',
            'current_name' => 'Kingdom A Player',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8307-b',
            'current_name' => 'Kingdom B Player',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $firstAlliance = $createAlliance->handle($firstPlayer, 'Kingdom A', 'kingdom-a');
        $secondAlliance = $createAlliance->handle($secondPlayer, 'Kingdom B', 'kingdom-b');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $firstAlliance->id,
            'player_id' => Player::query()->where('game_player_id', '8307-a')->sole()->id,
            'observed_name' => 'Kingdom A Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $secondAlliance->id,
            'player_id' => Player::query()->where('game_player_id', '8307-b')->sole()->id,
            'observed_name' => 'Kingdom B Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        $this->app->make(BootstrapKingdomAdministrator::class)->handle($kingdom, $adminPlayer);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $adminPlayer,
            configuration: $configuration,
            target: $kingdom,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $roster = $this->app->make(SaveEventRoster::class)->handle(
            $adminPlayer,
            $occurrence,
            'kingdom-main',
            EventRosterType::Roster,
            'kingdom',
            'Kingdom Main',
        );
        $assign = $this->app->make(AssignEventRosterPlayer::class);
        $firstAssignment = $assign->handle($adminPlayer, $roster, $firstPlayer);
        $secondAssignment = $assign->handle($adminPlayer, $roster, $secondPlayer);

        self::assertSame((string) $firstAlliance->id, (string) $firstAssignment->alliance_id);
        self::assertSame((string) $secondAlliance->id, (string) $secondAssignment->alliance_id);
    }

    public function test_parent_roster_rejects_direct_player_assignment(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8308, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8308-player',
            'current_name' => 'Parent Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Roster Parent', 'roster-parent');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8308-player')->sole()->id,
            'observed_name' => 'Parent Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        $type = EventType::query()->where('slug', 'swordland-summit-league')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $parent = $event->occurrences->firstOrFail()->rosters()->where('key', 'legion-1')->sole();

        $this->expectException(ValidationException::class);
        $this->app->make(AssignEventRosterPlayer::class)->handle($ownerPlayer, $parent, $ownerPlayer);
    }
}
