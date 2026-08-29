<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\BattlePlans;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\BattlePlans\Enums\EventObjectiveStatus;
use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\BattlePlans\Queries\PlayerBattlePlanQuery;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlayerBattlePlanQueryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_projection_returns_direct_and_roster_assignments_but_never_other_governors_assignment(): void
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $actor = $scenario->player((int) $user->id, 64301);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        $otherUser = $scenario->authUser();
        $other = $scenario->player((int) $otherUser->id, 64301);
        $scenario->roster($actor, $alliance, $other);

        $occurrence = $this->swordland($actor, $alliance);
        $roster = EventRoster::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('key', 'combatants')
            ->firstOrFail();
        EventRosterMember::query()->create([
            'roster_id' => (string) $roster->id,
            'player_id' => $actor->playerId,
            'alliance_id' => $alliance->allianceId,
            'role' => 'Joiner',
            'slot_number' => 4,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now(),
        ]);

        $direct = $this->objective($occurrence, $actor, 'Direct objective');
        $team = $this->objective($occurrence, $actor, 'Team objective');
        $hidden = $this->objective($occurrence, $actor, 'Other Governor objective');

        $directAssignment = EventObjectiveAssignment::query()->create([
            'objective_id' => (string) $direct->id,
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $actor->playerId,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now(),
            'notes' => 'Direct note',
        ]);
        $rosterAssignment = EventObjectiveAssignment::query()->create([
            'objective_id' => (string) $team->id,
            'occurrence_id' => (string) $occurrence->id,
            'roster_id' => (string) $roster->id,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now()->addSecond(),
            'notes' => 'Team note',
        ]);
        $hiddenAssignment = EventObjectiveAssignment::query()->create([
            'objective_id' => (string) $hidden->id,
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $other->playerId,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now()->addSeconds(2),
            'notes' => 'Hidden note',
        ]);

        $rows = app(PlayerBattlePlanQuery::class)->forPlayer($occurrence, $actor);

        self::assertCount(2, $rows);
        self::assertSame(
            [(string) $directAssignment->id, (string) $rosterAssignment->id],
            array_column($rows, 'assignmentId'),
        );
        self::assertSame(['player', 'roster'], array_column($rows, 'assignmentScope'));

        $serialized = json_encode($rows, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString((string) $hiddenAssignment->id, $serialized);
        self::assertStringNotContainsString('Hidden note', $serialized);
        self::assertStringNotContainsString('Other Governor objective', $serialized);
    }

    private function objective(
        EventOccurrence $occurrence,
        PlayerReference $actor,
        string $name,
    ): EventObjective {
        return EventObjective::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'objective_type' => 'position',
            'name' => $name,
            'description' => $name.' description',
            'priority' => 1,
            'status' => EventObjectiveStatus::Planned->value,
            'sort_order' => 1,
            'created_by_player_id' => $actor->playerId,
            'updated_by_player_id' => $actor->playerId,
        ]);
    }

    private function swordland(PlayerReference $actor, AllianceReference $alliance): EventOccurrence
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'swordland-showdown'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2)->startOfHour(),
            title: 'Swordland',
            durationMinutes: 60,
        );
        self::assertNotNull($created->firstOccurrenceId);

        $occurrence = EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
        EventRoster::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'key' => 'combatants',
            'name' => 'Combatants',
            'roster_type' => EventRosterType::Combatants,
            'assignment_group' => 'primary',
            'settings' => ['source' => 'acceptance-fixture'],
            'created_by_player_id' => $actor->playerId,
            'updated_by_player_id' => $actor->playerId,
        ]);

        return $occurrence;
    }
}
