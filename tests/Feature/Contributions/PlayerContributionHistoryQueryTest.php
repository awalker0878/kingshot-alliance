<?php

declare(strict_types=1);

namespace Tests\Feature\Contributions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
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
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Contexts\Intelligence\Contributions\Queries\PlayerContributionHistoryQuery;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Results\Actions\SaveEventPlayerResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayerContributionHistoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_player_timeline_composes_event_and_contribution_facts_without_copying_event_result(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8931, 'status' => 'active']);
        $player = $this->player($kingdom, 'Unified History', '8931-player');
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Unified Alliance', 'unified-alliance');
        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $player,
            $alliance,
            'Helpfulness',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
            allowSelfReport: true,
        );
        $record = $this->app->make(RecordContribution::class)->handle(
            $player,
            $alliance,
            $player,
            $category,
            25,
            ContributionRecordSource::SelfReported,
        );

        self::assertSame(1, ContributionRecord::query()->count());
        $event = $this->playerEvent($player, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $player,
            $event->occurrences->firstOrFail(),
            $player,
            score: 700,
        );
        self::assertSame(1, ContributionRecord::query()->count(), 'Event facts must not be copied into contribution_records.');

        $timeline = $this->app->make(PlayerContributionHistoryQuery::class)->forPlayer($player);

        self::assertCount(2, $timeline);
        self::assertSame(['event', 'contribution'], array_column($timeline, 'kind'));
        self::assertSame(700, $timeline[0]['event']['result']['score']);
        self::assertSame((string) $record->id, $timeline[1]['contribution']['recordId']);
        self::assertSame('25.00', $timeline[1]['contribution']['value']);
    }

    public function test_unified_timeline_never_leaks_sibling_player_history(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8932, 'status' => 'active']);
        $user = User::factory()->create();
        $first = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8932-first',
            'current_name' => 'First',
        ]);
        $second = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8932-second',
            'current_name' => 'Second',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($first, 'Sibling Alliance', 'sibling-history');
        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $first,
            $alliance,
            'Support',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
            allowSelfReport: true,
        );
        $this->app->make(RecordContribution::class)->handle(
            $first,
            $alliance,
            $first,
            $category,
            10,
            ContributionRecordSource::SelfReported,
        );
        $event = $this->playerEvent($first, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $first,
            $event->occurrences->firstOrFail(),
            $first,
            score: 10,
        );

        self::assertCount(2, $this->app->make(PlayerContributionHistoryQuery::class)->forPlayer($first));
        self::assertSame([], $this->app->make(PlayerContributionHistoryQuery::class)->forPlayer($second));
    }

    public function test_personal_contribution_history_follows_player_across_alliances(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8933, 'status' => 'active']);
        $owner = $this->player($kingdom, 'Old Alliance Owner', '8933-owner');
        $player = $this->player($kingdom, 'Moving Player', '8933-player');
        $oldAlliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Old Alliance', 'old-history-alliance');
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $oldAlliance->id,
            'player_id' => $player->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $owner,
            $oldAlliance,
            'Old Alliance Work',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
        );
        $record = $this->app->make(RecordContribution::class)->handle(
            $owner,
            $oldAlliance,
            $player,
            $category,
            50,
            ContributionRecordSource::Manual,
        );
        $this->app->make(UpdateMembershipStatus::class)->handle(
            $oldAlliance,
            $owner,
            (string) $membership->id,
            MembershipStatus::Removed,
        );
        $newAlliance = $this->app->make(CreateAlliance::class)->handle($player, 'New Alliance', 'new-history-alliance');

        $timeline = $this->app->make(PlayerContributionHistoryQuery::class)->forPlayer($player);

        self::assertCount(1, $timeline);
        self::assertSame('contribution', $timeline[0]['kind']);
        self::assertSame((string) $record->id, $timeline[0]['contribution']['recordId']);
        self::assertSame((string) $oldAlliance->id, $timeline[0]['contribution']['allianceId']);
        self::assertNotSame((string) $newAlliance->id, $timeline[0]['contribution']['allianceId']);
    }

    public function test_global_limit_is_applied_after_merging_event_and_contribution_sources(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8934, 'status' => 'active']);
        $player = $this->player($kingdom, 'Ordered History', '8934-player');
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Ordered Alliance', 'ordered-history');
        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $player,
            $alliance,
            'Ordered Contribution',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
            allowSelfReport: true,
        );
        $record = $this->app->make(RecordContribution::class)->handle(
            $player,
            $alliance,
            $player,
            $category,
            1,
            ContributionRecordSource::SelfReported,
        );
        $record->forceFill(['recorded_at' => CarbonImmutable::now('UTC')->addHours(2)])->save();

        $event = $this->playerEvent($player, 1);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $player,
            $event->occurrences->firstOrFail(),
            $player,
            score: 2,
        );

        $timeline = $this->app->make(PlayerContributionHistoryQuery::class)->forPlayer($player, ['limit' => 1]);

        self::assertCount(1, $timeline);
        self::assertSame('contribution', $timeline[0]['kind']);
        self::assertSame((string) $record->id, $timeline[0]['contribution']['recordId']);
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

    private function playerEvent(Player $player, int $hoursFromNow): Event
    {
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Player);

        return $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $player,
            firstLocalStart: CarbonImmutable::now('UTC')->addHours($hoursFromNow),
            durationMinutes: 60,
        );
    }
}
