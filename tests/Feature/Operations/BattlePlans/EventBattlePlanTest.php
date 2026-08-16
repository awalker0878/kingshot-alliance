<?php

declare(strict_types=1);

namespace Tests\Feature\Operations\BattlePlans;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\BattlePlans\Actions\AssignEventObjectiveTarget;
use App\Contexts\Operations\BattlePlans\Actions\SaveEventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\BattlePlans\Queries\EventObjectiveQuery;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Rosters\Actions\SaveEventRoster;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventBattlePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_build_hierarchy_and_assign_roster_or_player(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8501, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8501-a',
            'current_name' => 'Alpha',
        ]);
        $second = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8501-b',
            'current_name' => 'Bravo',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($actor, 'Battle Plan 8501', 'battle-plan-8501');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8501-a')->sole()->id,
            'observed_name' => 'Alpha',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8501-b')->sole()->id,
            'observed_name' => 'Bravo',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 120,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $save = $this->app->make(SaveEventObjective::class);

        $parent = $save->handle($actor, $occurrence, 'Hold the center', 'control', priority: 90);
        $child = $save->handle($actor, $occurrence, 'Reinforce north gate', 'reinforce', priority: 75, parent: $parent);
        $roster = $this->app->make(SaveEventRoster::class)->handle(
            $actor,
            $occurrence,
            'north-team',
            EventRosterType::Team,
            'battle-plan',
            name: 'North Team',
        );
        $assign = $this->app->make(AssignEventObjectiveTarget::class);
        $assign->handle($actor, $child, $roster, 'Primary roster responsibility');
        $assign->handle($actor, $child, $second, 'Fallback point player');

        self::assertSame($parent->id, $child->refresh()->parent_id);
        self::assertSame(2, EventObjectiveAssignment::query()->where('objective_id', $child->id)->count());
        self::assertDatabaseHas('event_objective_assignments', ['objective_id' => $child->id, 'roster_id' => $roster->id, 'player_id' => null]);
        self::assertDatabaseHas('event_objective_assignments', ['objective_id' => $child->id, 'roster_id' => null, 'player_id' => $second->id]);
    }

    public function test_player_assignment_highlighting_is_independent_for_multiple_players_owned_by_same_user(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8502, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8502-a',
            'current_name' => 'Alpha',
        ]);
        $second = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8502-b',
            'current_name' => 'Bravo',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($actor, 'Battle Plan 8502', 'battle-plan-8502');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8502-a')->sole()->id,
            'observed_name' => 'Alpha',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8502-b')->sole()->id,
            'observed_name' => 'Bravo',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 120,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $objective = $this->app->make(SaveEventObjective::class)->handle($actor, $occurrence, 'Capture east tower', 'capture');
        $assignment = $this->app->make(AssignEventObjectiveTarget::class)->handle($actor, $objective, $second);
        $query = $this->app->make(EventObjectiveQuery::class);

        $firstPlan = $query->forOccurrence($occurrence, $actor);
        $secondPlan = $query->forOccurrence($occurrence, $second);

        self::assertNotContains((string) $assignment->id, $firstPlan['myAssignmentIds']);
        self::assertContains((string) $assignment->id, $secondPlan['myAssignmentIds']);
        self::assertSame((int) $owner->id, (int) $actor->user_id);
        self::assertSame((int) $owner->id, (int) $second->user_id);
    }

    public function test_player_assignment_rejects_player_outside_event_target(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8503, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8503-a',
            'current_name' => 'Alpha',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($actor, 'Battle Plan 8503', 'battle-plan-8503');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8503-a')->sole()->id,
            'observed_name' => 'Alpha',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);

        $otherOwner = User::factory()->create();
        $otherKingdom = Kingdom::query()->create(['number' => 8504, 'status' => 'active']);
        $otherActor = Player::query()->create([
            'user_id' => $otherOwner->id,
            'current_kingdom_id' => $otherKingdom->id,
            'game_player_id' => '8504-r5',
            'current_name' => 'Other R5',
        ]);
        $other = Player::query()->create([
            'user_id' => $otherOwner->id,
            'current_kingdom_id' => $otherKingdom->id,
            'game_player_id' => '8504-a',
            'current_name' => 'Outside Player',
        ]);
        $otherAlliance = $this->app->make(CreateAlliance::class)->handle($otherActor, 'Other Battle Plan', 'other-battle-plan');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $otherAlliance->id,
            'player_id' => Player::query()->where('game_player_id', '8504-a')->sole()->id,
            'observed_name' => 'Outside Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);

        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 120,
        );
        $objective = $this->app->make(SaveEventObjective::class)->handle($actor, $event->occurrences->firstOrFail(), 'Defend west', 'defend');

        $this->expectException(ValidationException::class);
        $this->app->make(AssignEventObjectiveTarget::class)->handle($actor, $objective, $other);
    }

    public function test_objective_cannot_be_reparented_under_its_descendant(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8505, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8505-a',
            'current_name' => 'Alpha',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($actor, 'Battle Plan 8505', 'battle-plan-8505');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', '8505-a')->sole()->id,
            'observed_name' => 'Alpha',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 120,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $save = $this->app->make(SaveEventObjective::class);
        $parent = $save->handle($actor, $occurrence, 'Parent objective', 'custom');
        $child = $save->handle($actor, $occurrence, 'Child objective', 'custom', parent: $parent);

        $this->expectException(ValidationException::class);
        $save->handle($actor, $occurrence, 'Parent objective', 'custom', parent: $child, objective: $parent);
    }
}
