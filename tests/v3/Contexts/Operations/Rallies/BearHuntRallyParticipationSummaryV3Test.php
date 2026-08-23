<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Rallies;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Queries\RallyParticipationSummaryQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BearHuntRallyParticipationSummaryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_only_recorded_participated_outcomes_count_as_completed_rallies(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61801);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance);

        $this->assignment($occurrence, $alliance, $actor, RallyAssignmentRole::Lead, RallyAssignmentStatus::Participated, true, 'Lead participated');
        $this->assignment($occurrence, $alliance, $actor, RallyAssignmentRole::Joiner, RallyAssignmentStatus::Participated, true, 'Joiner participated');
        $this->assignment($occurrence, $alliance, $actor, RallyAssignmentRole::Standby, RallyAssignmentStatus::Participated, true, 'Standby participated');
        $this->assignment($occurrence, $alliance, $actor, RallyAssignmentRole::Joiner, RallyAssignmentStatus::Declined, true, 'Joiner declined');
        $this->assignment($occurrence, $alliance, $actor, RallyAssignmentRole::Joiner, RallyAssignmentStatus::Absent, true, 'Joiner absent');
        $this->assignment($occurrence, $alliance, $actor, RallyAssignmentRole::Joiner, RallyAssignmentStatus::Removed, true, 'Joiner removed');
        $this->assignment($occurrence, $alliance, $actor, RallyAssignmentRole::Joiner, RallyAssignmentStatus::Assigned, false, 'Joiner assigned');
        $this->assignment($occurrence, $alliance, $actor, RallyAssignmentRole::Joiner, RallyAssignmentStatus::Confirmed, false, 'Joiner confirmed');

        $summary = app(RallyParticipationSummaryQuery::class)->forOccurrence((string) $occurrence->id);

        self::assertTrue($summary['available']);
        self::assertSame(6, $summary['recordedAssignments']);
        self::assertSame(3, $summary['participated']);
        self::assertSame(1, $summary['led']);
        self::assertSame(1, $summary['joined']);
        self::assertSame(1, $summary['standby']);
        self::assertSame(3, $summary['players'][$actor->playerId]['participated']);
        self::assertSame(1, $summary['players'][$actor->playerId]['led']);
        self::assertSame(1, $summary['players'][$actor->playerId]['joined']);
        self::assertSame(1, $summary['players'][$actor->playerId]['standby']);
    }

    private function occurrence(PlayerReference $actor, AllianceReference $alliance): EventOccurrence
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC'),
            title: 'Bear Hunt Rally Summary Fixture',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
    }

    private function assignment(
        EventOccurrence $occurrence,
        AllianceReference $alliance,
        PlayerReference $player,
        RallyAssignmentRole $role,
        RallyAssignmentStatus $status,
        bool $recorded,
        string $name,
    ): void {
        $group = RallyGroup::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'alliance_id' => $alliance->allianceId,
            'name' => $name,
            'sort_order' => 0,
            'created_by_player_id' => $player->playerId,
        ]);
        RallyAssignment::query()->create([
            'rally_group_id' => (string) $group->id,
            'player_id' => $player->playerId,
            'role' => $role,
            'status' => $status,
            'assigned_by_player_id' => $player->playerId,
            'assigned_at' => now(),
            'recorded_by_player_id' => $recorded ? $player->playerId : null,
            'recorded_at' => $recorded ? now() : null,
        ]);
    }
}
