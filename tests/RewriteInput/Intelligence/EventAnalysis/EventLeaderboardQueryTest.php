<?php

declare(strict_types=1);

namespace Tests\RewriteInput\Intelligence\EventAnalysis;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\EventAnalysis\Queries\EventTrendQuery;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Results\Actions\SaveEventAllianceResult;
use App\Contexts\Operations\Results\Actions\SaveEventPlayerResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventLeaderboardQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_player_leaderboard_uses_historical_player_identity_not_current_affiliation(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9004, 'status' => 'active']);
        $owner = $this->player($kingdom, 'Leaderboard Owner', '9004-owner');
        $participant = $this->player($kingdom, 'Historical Contributor', '9004-participant');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Leaderboard Alliance', 'leaderboard-alliance');

        foreach ([$owner, $participant] as $player) {
            $this->app->make(SaveRosterEntry::class)->handle(
                $alliance,
                $owner,
                ['name' => $player->current_name, 'game_player_id' => $player->game_player_id],
                expectedPlayerId: (string) $player->id,
            );
        }

        $event = $this->event($owner, $alliance, EventScope::Alliance, 1);
        $save = $this->app->make(SaveEventPlayerResult::class);
        $save->handle($owner, $event->occurrences->firstOrFail(), $owner, score: 100);
        $save->handle($owner, $event->occurrences->firstOrFail(), $participant, score: 250);

        $participant->forceFill(['current_name' => 'Renamed Later'])->save();

        $rows = $this->app->make(EventTrendQuery::class)->organizationPlayerScoreLeaderboard(
            EventScope::Alliance,
            (string) $alliance->id,
            'custom',
        );

        self::assertCount(2, $rows);
        self::assertSame((string) $participant->id, $rows[0]['player_id']);
        self::assertSame('Historical Contributor', $rows[0]['player_name']);
        self::assertSame(250, $rows[0]['total_score']);
        self::assertSame(250, $rows[0]['best_score']);
        self::assertSame((string) $owner->id, $rows[1]['player_id']);
    }

    public function test_kingdom_alliance_leaderboard_groups_by_historical_alliance_id(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9005, 'status' => 'active']);
        $admin = $this->player($kingdom, 'Kingdom Admin', '9005-admin');
        $firstOwner = $this->player($kingdom, 'First Owner', '9005-first-owner');
        $secondOwner = $this->player($kingdom, 'Second Owner', '9005-second-owner');
        $this->grantKingdomAdministrator($admin, $kingdom);
        $firstAlliance = $this->app->make(CreateAlliance::class)->handle($firstOwner, 'First Alliance', 'first-leaderboard-alliance');
        $secondAlliance = $this->app->make(CreateAlliance::class)->handle($secondOwner, 'Second Alliance', 'second-leaderboard-alliance');
        $event = $this->event($admin, $kingdom, EventScope::Kingdom, 1);
        $save = $this->app->make(SaveEventAllianceResult::class);
        $save->handle($admin, $event->occurrences->firstOrFail(), $firstAlliance, score: 400);
        $save->handle($admin, $event->occurrences->firstOrFail(), $secondAlliance, score: 650);

        $secondAlliance->forceFill(['name' => 'Renamed Later'])->save();

        $rows = $this->app->make(EventTrendQuery::class)->kingdomAllianceScoreLeaderboard(
            (string) $kingdom->id,
            'custom',
        );

        self::assertCount(2, $rows);
        self::assertSame((string) $secondAlliance->id, $rows[0]['alliance_id']);
        self::assertSame('Second Alliance', $rows[0]['alliance_name']);
        self::assertSame(650, $rows[0]['total_score']);
        self::assertSame((string) $firstAlliance->id, $rows[1]['alliance_id']);
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
        Alliance|Kingdom $target,
        EventScope $scope,
        int $hoursAgo,
    ): Event {
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, $scope);

        return $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $target,
            firstLocalStart: CarbonImmutable::now('UTC')->subHours($hoursAgo),
            durationMinutes: 60,
        );
    }
}
