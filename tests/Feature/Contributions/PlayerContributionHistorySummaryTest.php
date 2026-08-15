<?php

declare(strict_types=1);

namespace Tests\Feature\Contributions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomRoleProvisioner;
use App\Domain\Contributions\Actions\CreateContributionCategory;
use App\Domain\Contributions\Actions\RecordContribution;
use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionPeriod;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use App\Domain\Contributions\Queries\PlayerContributionHistoryQuery;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\RecordEventAttendance;
use App\Domain\Events\Actions\SaveEventPlayerResult;
use App\Domain\Events\Enums\EventAttendanceStatus;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayerContributionHistorySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifetime_summary_counts_exact_player_scopes_and_reliability(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8941, 'status' => 'active']);
        $player = $this->player($kingdom, 'Summary Player', '8941-player');
        $this->grantKingdomAdministrator($player, $kingdom);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Summary Alliance', 'summary-alliance');
        $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            ['name' => 'Summary Player', 'game_player_id' => '8941-player'],
            expectedPlayerId: (string) $player->id,
        );

        $playerEvent = $this->event($player, $player, EventScope::Player, 1);
        $allianceEvent = $this->event($player, $alliance, EventScope::Alliance, 2);
        $kingdomEvent = $this->event($player, $kingdom, EventScope::Kingdom, 3);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $player,
            $playerEvent->occurrences->firstOrFail(),
            $player,
            score: 10,
        );
        $attendance = $this->app->make(RecordEventAttendance::class);
        $attendance->handle(
            $player,
            $allianceEvent->occurrences->firstOrFail(),
            $player,
            EventAttendanceStatus::Present,
        );
        $attendance->handle(
            $player,
            $kingdomEvent->occurrences->firstOrFail(),
            $player,
            EventAttendanceStatus::Absent,
        );

        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $player,
            $alliance,
            'Summary Work',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
            allowSelfReport: true,
        );
        $this->app->make(RecordContribution::class)->handle(
            $player,
            $alliance,
            $player,
            $category,
            12,
            ContributionRecordSource::SelfReported,
        );

        $summary = $this->app->make(PlayerContributionHistoryQuery::class)->summaryForPlayer($player);

        self::assertSame(3, $summary['events']);
        self::assertSame(1, $summary['player_events']);
        self::assertSame(1, $summary['alliance_events']);
        self::assertSame(1, $summary['kingdom_events']);
        self::assertSame(1, $summary['completed']);
        self::assertSame(1, $summary['absent']);
        self::assertSame(0, $summary['excused']);
        self::assertSame(0, $summary['unresolved']);
        self::assertSame(50.0, $summary['reliability_percent']);
        self::assertSame(1, $summary['contribution_records']);
    }

    public function test_scope_category_and_historical_kingdom_filters_have_consistent_source_semantics(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8942, 'status' => 'active']);
        $otherKingdom = Kingdom::query()->create(['number' => 8943, 'status' => 'active']);
        $player = $this->player($kingdom, 'Filter Player', '8942-player');
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Filter Alliance', 'summary-filter-alliance');

        $firstCategory = $this->app->make(CreateContributionCategory::class)->handle(
            $player,
            $alliance,
            'Helping',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
            allowSelfReport: true,
        );
        $secondCategory = $this->app->make(CreateContributionCategory::class)->handle(
            $player,
            $alliance,
            'Donations',
            'items',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
            allowSelfReport: true,
        );
        $record = $this->app->make(RecordContribution::class)->handle(
            $player,
            $alliance,
            $player,
            $firstCategory,
            5,
            ContributionRecordSource::SelfReported,
        );
        $this->app->make(RecordContribution::class)->handle(
            $player,
            $alliance,
            $player,
            $secondCategory,
            7,
            ContributionRecordSource::SelfReported,
        );

        $event = $this->event($player, $player, EventScope::Player, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $player,
            $event->occurrences->firstOrFail(),
            $player,
            score: 25,
        );

        $query = $this->app->make(PlayerContributionHistoryQuery::class);
        $playerTab = $query->forPlayer($player, ['event_scope' => EventScope::Player->value]);
        self::assertCount(1, $playerTab);
        self::assertSame('event', $playerTab[0]['kind']);

        $categoryFiltered = $query->forPlayer($player, [
            'contribution_category_slug' => (string) $firstCategory->slug,
        ]);
        self::assertCount(2, $categoryFiltered);
        self::assertCount(1, array_filter(
            $categoryFiltered,
            static fn (array $row): bool => $row['kind'] === 'contribution'
                && ($row['contribution']['recordId'] ?? null) === (string) $record->id,
        ));

        self::assertSame([], $query->forPlayer($player, [
            'kingdom_id_at_event' => (string) $otherKingdom->id,
        ]));
    }

    private function player(Kingdom $kingdom, string $name, string $gamePlayerId): Player
    {
        return Player::query()->create([
            'user_id' => User::factory()->create()->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }

    private function grantKingdomAdministrator(Player $player, Kingdom $kingdom): void
    {
        $roles = $this->app->make(KingdomRoleProvisioner::class)->provision($kingdom);
        KingdomRoleAssignment::query()->create([
            'kingdom_id' => $kingdom->id,
            'player_id' => $player->id,
            'kingdom_role_id' => $roles[DefaultKingdomRole::Administrator->value]->id,
        ]);
    }

    private function event(
        Player $actor,
        Alliance|Kingdom|Player $target,
        EventScope $scope,
        int $hoursFromNow,
    ): Event {
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, $scope);

        return $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $target,
            firstLocalStart: CarbonImmutable::now('UTC')->addHours($hoursFromNow),
            durationMinutes: 60,
        );
    }
}
