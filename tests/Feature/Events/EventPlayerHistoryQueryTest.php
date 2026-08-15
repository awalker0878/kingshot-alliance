<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomRoleProvisioner;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\RecordEventAttendance;
use App\Domain\Events\Actions\SaveEventPlayerResult;
use App\Domain\Events\Enums\EventAttendanceStatus;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Queries\EventPlayerHistoryQuery;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Contexts\Accounts\Models\User;
use App\Domain\Kingdoms\Actions\MarkRosterEntryLeft;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventPlayerHistoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_player_history_unifies_player_alliance_and_kingdom_scopes_from_frozen_context(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8891, 'status' => 'active']);
        $player = $this->player($kingdom, 'History Player', '8891-player');
        $this->grantKingdomAdministrator($player, $kingdom);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'History Alliance', 'history-alliance');
        $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            ['name' => 'History Player', 'game_player_id' => '8891-player'],
            expectedPlayerId: (string) $player->id,
        );

        $playerEvent = $this->event($player, $player, 'custom', EventScope::Player, 1);
        $allianceEvent = $this->event($player, $alliance, 'custom', EventScope::Alliance, 2);
        $kingdomEvent = $this->event($player, $kingdom, 'custom', EventScope::Kingdom, 3);
        $save = $this->app->make(SaveEventPlayerResult::class);

        $save->handle($player, $playerEvent->occurrences->firstOrFail(), $player, score: 101);
        $save->handle($player, $allianceEvent->occurrences->firstOrFail(), $player, score: 202);
        $save->handle($player, $kingdomEvent->occurrences->firstOrFail(), $player, score: 303);
        $this->app->make(RecordEventAttendance::class)->handle(
            $player,
            $kingdomEvent->occurrences->firstOrFail(),
            $player,
            EventAttendanceStatus::Present,
        );

        $player->forceFill(['current_name' => 'Current Renamed Player'])->save();
        $alliance->forceFill(['name' => 'Current Renamed Alliance'])->save();

        $query = $this->app->make(EventPlayerHistoryQuery::class);
        $history = $query->forPlayer($player->refresh());

        self::assertCount(3, $history);
        self::assertEqualsCanonicalizing(['player', 'alliance', 'kingdom'], array_column($history, 'scope'));
        self::assertEqualsCanonicalizing([101, 202, 303], array_map(static fn (array $row): ?int => $row['result']['score'] ?? null, $history));
        foreach ($history as $row) {
            self::assertSame((string) $player->id, $row['playerContext']['playerId']);
            self::assertSame('History Player', $row['playerContext']['playerName']);
            self::assertSame((string) $kingdom->id, $row['playerContext']['kingdomIdAtEvent']);
        }

        $allianceRow = collect($history)->firstWhere('scope', EventScope::Alliance->value);
        self::assertIsArray($allianceRow);
        self::assertSame((string) $alliance->id, $allianceRow['playerContext']['representedAllianceId']);
        self::assertSame('History Alliance', $allianceRow['playerContext']['representedAllianceName']);

        $kingdomRow = collect($history)->firstWhere('scope', EventScope::Kingdom->value);
        self::assertIsArray($kingdomRow);
        self::assertSame('completed', $kingdomRow['participation']['outcome']);

        $allianceOnly = $query->forPlayer($player->refresh(), ['scope' => EventScope::Alliance->value]);
        self::assertCount(1, $allianceOnly);
        self::assertSame(EventScope::Alliance->value, $allianceOnly[0]['scope']);
    }

    public function test_personal_history_survives_alliance_and_kingdom_moves_without_rewriting_frozen_context(): void
    {
        $oldKingdom = Kingdom::query()->create(['number' => 8895, 'status' => 'active']);
        $newKingdom = Kingdom::query()->create(['number' => 8896, 'status' => 'active']);
        $owner = $this->player($oldKingdom, 'Old Alliance Owner', '8895-owner');
        $kingdomAdmin = $this->player($oldKingdom, 'Old Kingdom Admin', '8895-admin');
        $movingPlayer = $this->player($oldKingdom, 'Moving Player', '8895-moving');
        $this->grantKingdomAdministrator($kingdomAdmin, $oldKingdom);
        $oldAlliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Old Alliance', 'old-history-alliance');
        $oldRoster = $this->app->make(SaveRosterEntry::class)->handle(
            $oldAlliance,
            $owner,
            ['name' => 'Moving Player', 'game_player_id' => '8895-moving'],
            expectedPlayerId: (string) $movingPlayer->id,
        );

        $allianceEvent = $this->event($owner, $oldAlliance, 'custom', EventScope::Alliance, 1);
        $kingdomEvent = $this->event($kingdomAdmin, $oldKingdom, 'custom', EventScope::Kingdom, 2);
        $save = $this->app->make(SaveEventPlayerResult::class);
        $save->handle($owner, $allianceEvent->occurrences->firstOrFail(), $movingPlayer, score: 111);
        $save->handle($kingdomAdmin, $kingdomEvent->occurrences->firstOrFail(), $movingPlayer, score: 222);

        $this->app->make(MarkRosterEntryLeft::class)->handle($oldAlliance, $owner, (string) $oldRoster->id);
        $movingPlayer->forceFill(['current_kingdom_id' => $newKingdom->id])->save();
        $newAlliance = $this->app->make(CreateAlliance::class)->handle(
            $movingPlayer->refresh(),
            'New Alliance',
            'new-history-alliance',
        );

        $history = $this->app->make(EventPlayerHistoryQuery::class)->forPlayer($movingPlayer->refresh());

        self::assertCount(2, $history);
        $allianceRow = collect($history)->firstWhere('scope', EventScope::Alliance->value);
        self::assertIsArray($allianceRow);
        self::assertSame((string) $oldAlliance->id, $allianceRow['target']['allianceId']);
        self::assertSame((string) $oldAlliance->id, $allianceRow['playerContext']['representedAllianceId']);
        self::assertSame((string) $oldKingdom->id, $allianceRow['playerContext']['kingdomIdAtEvent']);
        self::assertSame(111, $allianceRow['result']['score']);
        self::assertNotSame((string) $newAlliance->id, $allianceRow['playerContext']['representedAllianceId']);

        $kingdomRow = collect($history)->firstWhere('scope', EventScope::Kingdom->value);
        self::assertIsArray($kingdomRow);
        self::assertSame((string) $oldKingdom->id, $kingdomRow['target']['kingdomId']);
        self::assertSame((string) $oldKingdom->id, $kingdomRow['playerContext']['kingdomIdAtEvent']);
        self::assertSame(222, $kingdomRow['result']['score']);
        self::assertSame((string) $newKingdom->id, (string) $movingPlayer->refresh()->current_kingdom_id);
    }

    public function test_history_is_exact_player_only_even_when_sibling_players_share_a_user(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8892, 'status' => 'active']);
        $user = User::factory()->create();
        $first = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8892-first',
            'current_name' => 'First Player',
        ]);
        $second = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8892-second',
            'current_name' => 'Second Player',
        ]);
        $event = $this->event($first, $first, 'custom', EventScope::Player, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $first,
            $event->occurrences->firstOrFail(),
            $first,
            score: 77,
        );

        $query = $this->app->make(EventPlayerHistoryQuery::class);
        self::assertCount(1, $query->forPlayer($first));
        self::assertSame([], $query->forPlayer($second));
    }

    public function test_history_filters_use_historical_context_and_participation_evidence(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8893, 'status' => 'active']);
        $player = $this->player($kingdom, 'Filter Player', '8893-player');
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Filter Alliance', 'filter-alliance');
        $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            ['name' => 'Filter Player', 'game_player_id' => '8893-player'],
            expectedPlayerId: (string) $player->id,
        );
        $event = $this->event($player, $alliance, 'custom', EventScope::Alliance, 1);
        $occurrence = $event->occurrences->firstOrFail();
        $this->app->make(RecordEventAttendance::class)->handle(
            $player,
            $occurrence,
            $player,
            EventAttendanceStatus::Absent,
        );

        $query = $this->app->make(EventPlayerHistoryQuery::class);
        $matching = $query->forPlayer($player, [
            'scope' => EventScope::Alliance->value,
            'represented_alliance_id' => (string) $alliance->id,
            'kingdom_id_at_event' => (string) $kingdom->id,
            'event_type_slug' => 'custom',
            'participation_outcome' => 'absent',
        ]);
        self::assertCount(1, $matching);
        self::assertSame('absent', $matching[0]['participation']['outcome']);

        self::assertSame([], $query->forPlayer($player, [
            'represented_alliance_id' => '01AAAAAAAAAAAAAAAAAAAAAAAA',
        ]));
        self::assertSame([], $query->forPlayer($player, [
            'scope' => EventScope::Kingdom->value,
        ]));
        self::assertSame([], $query->forPlayer($player, [
            'participation_outcome' => 'completed',
        ]));
    }

    public function test_participation_outcome_is_filtered_before_history_limit(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8894, 'status' => 'active']);
        $player = $this->player($kingdom, 'Bounded History Player', '8894-player');
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Bounded History Alliance', 'bounded-history-alliance');
        $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            ['name' => 'Bounded History Player', 'game_player_id' => '8894-player'],
            expectedPlayerId: (string) $player->id,
        );
        $olderAbsent = $this->event($player, $alliance, 'custom', EventScope::Alliance, 1);
        $newerCompleted = $this->event($player, $alliance, 'custom', EventScope::Alliance, 2);
        $attendance = $this->app->make(RecordEventAttendance::class);

        $attendance->handle(
            $player,
            $olderAbsent->occurrences->firstOrFail(),
            $player,
            EventAttendanceStatus::Absent,
        );
        $attendance->handle(
            $player,
            $newerCompleted->occurrences->firstOrFail(),
            $player,
            EventAttendanceStatus::Present,
        );

        $history = $this->app->make(EventPlayerHistoryQuery::class)->forPlayer($player, [
            'participation_outcome' => 'absent',
            'limit' => 1,
        ]);

        self::assertCount(1, $history);
        self::assertSame((string) $olderAbsent->occurrences->firstOrFail()->id, $history[0]['occurrenceId']);
        self::assertSame('absent', $history[0]['participation']['outcome']);
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
        string $slug,
        EventScope $scope,
        int $hoursFromNow,
    ): Event {
        $type = EventType::query()->where('slug', $slug)->sole();
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
