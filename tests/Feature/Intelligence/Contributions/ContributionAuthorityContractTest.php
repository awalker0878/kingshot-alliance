<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence\Contributions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Contributions\Actions\CreateContributionCategory;
use App\Contexts\Intelligence\Contributions\Actions\RecordContribution;
use App\Contexts\Intelligence\Contributions\Enums\ContributionDataClass;
use App\Contexts\Intelligence\Contributions\Enums\ContributionPeriod;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordSource;
use App\Contexts\Intelligence\Contributions\Models\ContributionCategory;
use App\Contexts\Intelligence\Contributions\Queries\PlayerContributionHistoryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class ContributionAuthorityContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_report_is_bound_to_active_player_not_sibling_player_owned_by_same_user(): void
    {
        $user = User::factory()->create();
        $kingdom = $this->kingdom(9201);
        $actor = $this->player($user, $kingdom, '9201-main', 'Main Player');
        $sibling = $this->player($user, $kingdom, '9201-farm', 'Farm Player');
        $alliance = $this->app->make(CreateAlliance::class)->handle($actor, 'Contribution Authority', 'contribution-authority');
        $category = $this->category($actor, $alliance);
        $record = $this->app->make(RecordContribution::class)->handle(
            actor: $actor,
            alliance: $alliance,
            player: $actor,
            category: $category,
            value: 25,
            source: ContributionRecordSource::SelfReported,
        );

        self::assertSame((string) $actor->id, (string) $record->player_id);
        self::assertSame((string) $actor->id, (string) $record->recorded_by_player_id);

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(RecordContribution::class)->handle(
            actor: $actor,
            alliance: $alliance,
            player: $sibling,
            category: $category,
            value: 50,
            source: ContributionRecordSource::SelfReported,
        );
    }

    public function test_manual_record_targets_only_an_active_player_in_the_exact_alliance_kingdom(): void
    {
        $managerUser = User::factory()->create();
        $memberUser = User::factory()->create();
        $outsiderUser = User::factory()->create();
        $kingdom = $this->kingdom(9202);
        $otherKingdom = $this->kingdom(9203);
        $manager = $this->player($managerUser, $kingdom, '9202-r5', 'Contribution Manager');
        $member = $this->player($memberUser, $kingdom, '9202-member', 'Contribution Member');
        $outsider = $this->player($outsiderUser, $otherKingdom, '9203-outsider', 'Other Kingdom Player');
        $alliance = $this->app->make(CreateAlliance::class)->handle($manager, 'Manual Contributions', 'manual-contributions');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $member->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $category = $this->category($manager, $alliance);

        $record = $this->app->make(RecordContribution::class)->handle(
            actor: $manager,
            alliance: $alliance,
            player: $member,
            category: $category,
            value: 100,
            source: ContributionRecordSource::Manual,
        );

        self::assertSame((string) $member->id, (string) $record->player_id);
        self::assertSame((string) $manager->id, (string) $record->recorded_by_player_id);

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(RecordContribution::class)->handle(
            actor: $manager,
            alliance: $alliance,
            player: $outsider,
            category: $category,
            value: 200,
            source: ContributionRecordSource::Manual,
        );
    }

    public function test_player_history_does_not_aggregate_contributions_across_players_owned_by_one_user(): void
    {
        $user = User::factory()->create();
        $kingdom = $this->kingdom(9204);
        $first = $this->player($user, $kingdom, '9204-main', 'History Main');
        $second = $this->player($user, $kingdom, '9204-farm', 'History Farm');
        $alliance = $this->app->make(CreateAlliance::class)->handle($first, 'History Contributions', 'history-contributions');
        $category = $this->category($first, $alliance);
        $this->app->make(RecordContribution::class)->handle(
            actor: $first,
            alliance: $alliance,
            player: $first,
            category: $category,
            value: 75,
            source: ContributionRecordSource::SelfReported,
        );

        $query = $this->app->make(PlayerContributionHistoryQuery::class);
        $firstSummary = $query->summaryForPlayer($first);
        $secondSummary = $query->summaryForPlayer($second);

        self::assertSame(1, $firstSummary['contribution_records']);
        self::assertSame(0, $secondSummary['contribution_records']);
    }

    private function category(Player $actor, Alliance $alliance): ContributionCategory
    {
        return $this->app->make(CreateContributionCategory::class)->handle(
            actor: $actor,
            alliance: $alliance,
            name: 'Alliance Help',
            unit: 'points',
            period: ContributionPeriod::Weekly,
            dataClass: ContributionDataClass::RecordedFact,
            allowSelfReport: true,
        );
    }

    private function kingdom(int $number): Kingdom
    {
        return Kingdom::query()->create([
            'number' => $number,
            'status' => 'active',
        ]);
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
}
