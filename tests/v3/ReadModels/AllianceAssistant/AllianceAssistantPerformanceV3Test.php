<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\ReadModels\AllianceAssistant\Queries\AllianceAssistantQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceAssistantPerformanceV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_event_and_roster_answer_stays_within_a_bounded_query_budget(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 63501);
        $alliance = $scenario->alliance($actor);
        $rosterEntry = $scenario->roster($actor, $alliance);
        $occurrence = $this->swordland($actor, $alliance->allianceId);
        $roster = EventRoster::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('key', 'combatants')
            ->firstOrFail();
        EventRosterMember::query()->create([
            'roster_id' => (string) $roster->id,
            'player_id' => $actor->playerId,
            'alliance_id' => $alliance->allianceId,
            'role' => 'Rally Lead',
            'slot_number' => 7,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now(),
        ]);
        $scope = new AllianceScopeReference(
            $actor->playerId,
            $actor->kingdomId,
            $alliance->allianceId,
            $rosterEntry->rosterEntryId,
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = app(AllianceAssistantQuery::class)->ask(
            $actor,
            $scope,
            'What time is Swordland and am I rostered?',
        );
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertSame('answered', $result->status->value);
        self::assertCount(2, $result->evidence);
        self::assertLessThanOrEqual(
            35,
            $queryCount,
            'The canonical Assistant answer exceeded its bounded read-query budget.',
        );
    }

    private function swordland(PlayerReference $actor, string $allianceId): EventOccurrence
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'swordland-showdown'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
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
