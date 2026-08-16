<?php

declare(strict_types=1);

namespace Tests\Feature\Contributions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Contributions\Actions\CreateContributionCategory;
use App\Contexts\Intelligence\Contributions\Actions\RecordContribution;
use App\Contexts\Intelligence\Contributions\Enums\ContributionDataClass;
use App\Contexts\Intelligence\Contributions\Enums\ContributionPeriod;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordSource;
use App\Contexts\Intelligence\Contributions\Queries\AllianceContributionReportQuery;
use App\Contexts\Intelligence\Contributions\Services\ContributionReportExporter;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Results\Actions\SaveEventPlayerResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AllianceContributionReportQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_rows_compose_contribution_and_event_result_metric_facts(): void
    {
        [$player, $alliance] = $this->context();
        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $player,
            $alliance,
            'Alliance Support',
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
            25,
            ContributionRecordSource::SelfReported,
        );

        $eventType = EventType::query()->where('slug', 'bear-hunt')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($eventType, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addHour(),
            durationMinutes: 60,
        );
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $player,
            $event->occurrences->firstOrFail(),
            $player,
            outcome: 'completed',
            score: 900,
            rank: 1,
            metrics: [['key' => 'rallies_joined', 'value' => 3]],
        );

        $rows = $this->app->make(AllianceContributionReportQuery::class)->rows($alliance);
        $kinds = array_column($rows, 'record_kind');

        self::assertContains('contribution', $kinds);
        self::assertContains('event_player_result', $kinds);
        self::assertContains('event_player_metric', $kinds);

        $metric = collect($rows)->firstWhere('record_kind', 'event_player_metric');
        self::assertIsArray($metric);
        self::assertSame('alliance', $metric['event_scope']);
        self::assertSame('bear-hunt', $metric['event_type']);
        self::assertSame((string) $alliance->id, $metric['historical_alliance_id']);
        self::assertSame((string) $player->id, $metric['player_id']);
        self::assertSame('rallies_joined', $metric['metric_key']);
        self::assertSame('count', $metric['metric_unit']);
        self::assertSame(3.0, $metric['metric_value']);
        self::assertSame(900, $metric['event_score']);
        self::assertSame(1, $metric['event_rank']);
    }

    public function test_export_schema_is_versioned_and_contains_event_history_columns(): void
    {
        [$player, $alliance] = $this->context();

        $export = $this->app->make(ContributionReportExporter::class)->export($alliance, $player, 'csv');

        self::assertSame(ContributionReportExporter::REPORT_VERSION, $export['run']->report_version);
        self::assertStringContainsString('record_kind', $export['content']);
        self::assertStringContainsString('event_scope', $export['content']);
        self::assertStringContainsString('event_type', $export['content']);
        self::assertStringContainsString('historical_alliance_id', $export['content']);
        self::assertStringContainsString('historical_kingdom_id', $export['content']);
        self::assertStringContainsString('metric_key', $export['content']);
        self::assertStringContainsString('metric_value', $export['content']);
    }

    /** @return array{Player, Alliance} */
    private function context(): array
    {
        $kingdom = Kingdom::query()->create(['number' => random_int(8950, 8999), 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => User::factory()->create()->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'report-'.random_int(100000, 999999),
            'current_name' => 'Report Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Report Alliance', 'report-'.random_int(100000, 999999));
        $this->app->make(SaveRosterEntry::class)->handle(
            $alliance,
            $player,
            ['name' => 'Report Player', 'game_player_id' => $player->game_player_id],
            expectedPlayerId: (string) $player->id,
        );

        return [$player, $alliance];
    }
}
