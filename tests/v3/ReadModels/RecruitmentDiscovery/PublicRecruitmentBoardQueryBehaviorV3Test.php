<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\RecruitmentDiscovery;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentSetting;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentMetricsQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\RecruitmentDiscovery\Queries\PublicRecruitmentBoardQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PublicRecruitmentBoardQueryBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_board_only_lists_opted_in_open_public_active_alliances_and_filters_results(): void
    {
        $listed = $this->recruitingAlliance(
            kingdom: 41001,
            name: 'Fire Vanguard',
            language: 'en',
            mode: RecruitmentApplicationMode::Public,
            open: true,
            listed: true,
            introduction: 'Coordinated event team',
        );
        $french = $this->recruitingAlliance(
            kingdom: 41002,
            name: 'Garde du Nord',
            language: 'fr',
            mode: RecruitmentApplicationMode::Public,
            open: true,
            listed: true,
            introduction: 'Alliance francophone',
        );
        $this->recruitingAlliance(
            kingdom: 41003,
            name: 'Invitation Guard',
            language: 'en',
            mode: RecruitmentApplicationMode::Invitation,
            open: true,
            listed: true,
            introduction: null,
        );
        $this->recruitingAlliance(
            kingdom: 41004,
            name: 'Private Guard',
            language: 'en',
            mode: RecruitmentApplicationMode::Public,
            open: true,
            listed: false,
            introduction: null,
        );

        $board = app(PublicRecruitmentBoardQuery::class);

        $all = $board->search(null, null, null);
        self::assertSame(['Fire Vanguard', 'Garde du Nord'], array_column($all['alliances'], 'name'));
        self::assertStringContainsString('source=recruitment-board', $all['alliances'][0]['applicationUrl']);

        $bySearch = $board->search('francophone', null, null);
        self::assertSame([$french['alliance']->allianceId], $this->allianceIds($bySearch['alliances']));

        $byKingdom = $board->search(null, 41001, null);
        self::assertSame([$listed['alliance']->allianceId], $this->allianceIds($byKingdom['alliances']));

        $byLanguage = $board->search(null, null, 'FR');
        self::assertSame([$french['alliance']->allianceId], $this->allianceIds($byLanguage['alliances']));
    }

    public function test_recruitment_metrics_report_source_conversion_funnel(): void
    {
        $listed = $this->recruitingAlliance(
            kingdom: 42001,
            name: 'Metric Guard',
            language: 'en',
            mode: RecruitmentApplicationMode::Public,
            open: true,
            listed: true,
            introduction: null,
        );
        $allianceId = $listed['alliance']->allianceId;

        RecruitmentCandidate::query()->create([
            'alliance_id' => $allianceId,
            'full_name' => 'New Candidate',
            'email' => 'new@example.test',
            'source' => 'recruitment-board',
            'stage' => RecruitmentStage::New,
            'submitted_at' => now()->subDays(3),
        ]);
        RecruitmentCandidate::query()->create([
            'alliance_id' => $allianceId,
            'full_name' => 'Accepted Candidate',
            'email' => 'accepted@example.test',
            'source' => 'recruitment-board',
            'stage' => RecruitmentStage::Accepted,
            'submitted_at' => now()->subDays(2),
            'accepted_at' => now()->subDay(),
        ]);
        RecruitmentCandidate::query()->create([
            'alliance_id' => $allianceId,
            'full_name' => 'Joined Candidate',
            'email' => 'joined@example.test',
            'source' => 'recruitment-board',
            'stage' => RecruitmentStage::Joined,
            'submitted_at' => now()->subDays(4),
            'accepted_at' => now()->subDays(2),
            'joined_at' => now()->subDay(),
        ]);

        $summary = app(RecruitmentMetricsQuery::class)->summary($allianceId);

        self::assertSame([
            'submitted' => 3,
            'accepted' => 2,
            'joined' => 1,
            'acceptedRate' => 0.6667,
            'joinedRate' => 0.3333,
        ], $summary['sourceFunnel']['recruitment-board']);
    }

    /**
     * @return array{alliance: AllianceReference, player: PlayerReference}
     */
    private function recruitingAlliance(
        int $kingdom,
        string $name,
        string $language,
        RecruitmentApplicationMode $mode,
        bool $open,
        bool $listed,
        ?string $introduction,
    ): array {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, $kingdom);
        $alliance = $scenarios->alliance($player);

        Alliance::query()->whereKey($alliance->allianceId)->update([
            'name' => $name,
            'language' => $language,
        ]);

        RecruitmentSetting::query()->create([
            'alliance_id' => $alliance->allianceId,
            'application_mode' => $mode,
            'title' => $name.' recruitment',
            'introduction' => $introduction,
            'retention_unsuccessful_days' => 90,
            'is_open' => $open,
            'is_listed' => $listed,
            'created_by_player_id' => $player->playerId,
            'updated_by_player_id' => $player->playerId,
        ]);

        return [
            'alliance' => $alliance,
            'player' => $player,
        ];
    }

    /**
     * @param list<array{name:string, slug:string, title:string, introduction:string|null, kingdom:int, language:string, timezone:string, profileUrl:string, applicationUrl:string}> $alliances
     * @return list<string>
     */
    private function allianceIds(array $alliances): array
    {
        $slugs = array_column($alliances, 'slug');

        return Alliance::query()
            ->whereIn('slug', $slugs)
            ->orderBy('kingdom_id')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }
}
