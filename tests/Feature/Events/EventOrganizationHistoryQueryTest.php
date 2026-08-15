<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\Results\Actions\SaveEventAllianceResult;
use App\Contexts\Operations\Results\Actions\SaveEventPlayerResult;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Intelligence\EventAnalysis\Queries\EventAllianceHistoryQuery;
use App\Contexts\Intelligence\EventAnalysis\Queries\EventKingdomHistoryQuery;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Domain\Kingdoms\Actions\MarkRosterEntryLeft;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventOrganizationHistoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_history_follows_immutable_event_target_after_participant_leaves_and_new_r4_takes_over(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8901, 'status' => 'active']);
        $owner = $this->player($kingdom, 'Original R5', '8901-owner');
        $participant = $this->player($kingdom, 'Historical Member', '8901-participant');
        $futureLeader = $this->player($kingdom, 'Future R4', '8901-future-r4');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'History Alliance', 'history-owned');

        $participantRoster = $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $owner,
            ['name' => 'Historical Member', 'game_player_id' => '8901-participant'],
            expectedPlayerId: (string) $participant->id,
        );
        $event = $this->event($owner, $alliance, EventScope::Alliance, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $owner,
            $event->occurrences->firstOrFail(),
            $participant,
            score: 444,
        );

        $this->app->make(MarkRosterEntryLeft::class)->handle($alliance, $owner, (string) $participantRoster->id);
        $this->app->make(CreateAlliance::class)->handle($participant, 'New Home', 'historical-member-new-home');

        $futureMembership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $futureLeader->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $this->app->make(UpdateAllianceRank::class)->handle(
            $alliance,
            $owner,
            (string) $futureMembership->id,
            AllianceRank::R4,
        );

        $history = $this->app->make(EventAllianceHistoryQuery::class)->forAlliance($futureLeader, $alliance);

        self::assertCount(1, $history);
        self::assertSame((string) $alliance->id, $history[0]['targetId']);
        self::assertCount(1, $history[0]['participants']);
        self::assertSame((string) $participant->id, $history[0]['participants'][0]['playerId']);
        self::assertSame('Historical Member', $history[0]['participants'][0]['playerName']);
        self::assertSame((string) $alliance->id, $history[0]['participants'][0]['representedAllianceId']);
        self::assertSame(444, $history[0]['participants'][0]['result']['score']);
    }

    public function test_former_alliance_leader_loses_organization_history_access_but_history_remains(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8902, 'status' => 'active']);
        $owner = $this->player($kingdom, 'Owner', '8902-owner');
        $formerLeader = $this->player($kingdom, 'Former R4', '8902-former');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Authority History', 'authority-history');
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $formerLeader->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $this->app->make(UpdateAllianceRank::class)->handle($alliance, $owner, (string) $membership->id, AllianceRank::R4);

        $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $owner,
            ['name' => 'Owner', 'game_player_id' => '8902-owner'],
            expectedPlayerId: (string) $owner->id,
        );
        $event = $this->event($owner, $alliance, EventScope::Alliance, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $owner,
            $event->occurrences->firstOrFail(),
            $owner,
            score: 99,
        );

        self::assertCount(1, $this->app->make(EventAllianceHistoryQuery::class)->forAlliance($formerLeader, $alliance));

        $this->app->make(UpdateMembershipStatus::class)->handle(
            $alliance,
            $owner,
            (string) $membership->id,
            MembershipStatus::Removed,
        );

        $this->expectException(AuthorizationException::class);
        $this->app->make(EventAllianceHistoryQuery::class)->forAlliance($formerLeader->refresh(), $alliance);
    }

    public function test_historical_alliance_snapshot_never_grants_current_alliance_history_authority(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8903, 'status' => 'active']);
        $owner = $this->player($kingdom, 'Owner', '8903-owner');
        $participant = $this->player($kingdom, 'Old Participant', '8903-participant');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Snapshot Authority', 'snapshot-authority');
        $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $owner,
            ['name' => 'Old Participant', 'game_player_id' => '8903-participant'],
            expectedPlayerId: (string) $participant->id,
        );
        $event = $this->event($owner, $alliance, EventScope::Alliance, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $owner,
            $event->occurrences->firstOrFail(),
            $participant,
            score: 5,
        );

        $this->expectException(AuthorizationException::class);
        $this->app->make(EventAllianceHistoryQuery::class)->forAlliance($participant, $alliance);
    }

    public function test_kingdom_history_keeps_transferred_player_and_alliance_results_under_original_event_target(): void
    {
        $firstKingdom = Kingdom::query()->create(['number' => 8911, 'status' => 'active']);
        $secondKingdom = Kingdom::query()->create(['number' => 8912, 'status' => 'active']);
        $admin = $this->player($firstKingdom, 'Kingdom Admin', '8911-admin');
        $participant = $this->player($firstKingdom, 'Transfer Later', '8911-transfer');
        $allianceOwner = $this->player($firstKingdom, 'Alliance Owner', '8911-alliance-owner');
        $this->grantKingdomAdministrator($admin, $firstKingdom);
        $alliance = $this->app->make(CreateAlliance::class)->handle($allianceOwner, 'Kingdom Result Alliance', 'kingdom-result-alliance');

        $event = $this->event($admin, $firstKingdom, EventScope::Kingdom, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $admin,
            $event->occurrences->firstOrFail(),
            $participant,
            score: 777,
        );
        $this->app->make(SaveEventAllianceResult::class)->handle(
            $admin,
            $event->occurrences->firstOrFail(),
            $alliance,
            score: 888,
        );

        $participant->forceFill(['current_kingdom_id' => $secondKingdom->id])->save();

        $history = $this->app->make(EventKingdomHistoryQuery::class)->forKingdom($admin, $firstKingdom);

        self::assertCount(1, $history);
        self::assertSame((string) $firstKingdom->id, $history[0]['targetId']);
        self::assertSame((string) $participant->id, $history[0]['participants'][0]['playerId']);
        self::assertSame((string) $firstKingdom->id, $history[0]['participants'][0]['kingdomIdAtEvent']);
        self::assertSame(777, $history[0]['participants'][0]['result']['score']);
        self::assertCount(1, $history[0]['allianceResults']);
        self::assertSame((string) $alliance->id, $history[0]['allianceResults'][0]['allianceId']);
        self::assertSame(888, $history[0]['allianceResults'][0]['score']);
    }

    public function test_new_kingdom_administrator_can_see_history_before_tenure_and_wrong_kingdom_cannot(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8921, 'status' => 'active']);
        $otherKingdom = Kingdom::query()->create(['number' => 8922, 'status' => 'active']);
        $creator = $this->player($kingdom, 'Creator Admin', '8921-creator');
        $futureAdmin = $this->player($kingdom, 'Future Admin', '8921-future');
        $otherAdmin = $this->player($otherKingdom, 'Other Admin', '8922-admin');
        $this->grantKingdomAdministrator($creator, $kingdom);
        $this->grantKingdomAdministrator($otherAdmin, $otherKingdom);

        $event = $this->event($creator, $kingdom, EventScope::Kingdom, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $creator,
            $event->occurrences->firstOrFail(),
            $creator,
            score: 123,
        );
        $this->grantKingdomAdministrator($futureAdmin, $kingdom);

        self::assertCount(1, $this->app->make(EventKingdomHistoryQuery::class)->forKingdom($futureAdmin, $kingdom));

        $this->expectException(AuthorizationException::class);
        $this->app->make(EventKingdomHistoryQuery::class)->forKingdom($otherAdmin, $kingdom);
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
        KingdomRoleAssignment::query()->firstOrCreate([
            'kingdom_id' => $kingdom->id,
            'player_id' => $player->id,
            'kingdom_role_id' => $roles[DefaultKingdomRole::Administrator->value]->id,
        ]);
    }

    private function event(Player $actor, Alliance|Kingdom|Player $target, EventScope $scope, int $hoursFromNow): Event
    {
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
