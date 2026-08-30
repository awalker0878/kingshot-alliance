<?php

declare(strict_types=1);

namespace Tests\v3\Acceptance;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Observations\Actions\StartTrackingKingdomAlliance;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\ReadModels\CommandOverview\Queries\AllianceCommandQuery;
use App\ReadModels\CommandOverview\Queries\OfficerBriefQuery;
use App\ReadModels\EventManagement\Queries\RallyRosterBuilderQuery;
use App\ReadModels\KingdomIntelligence\Queries\KingdomIntelligenceTimelineQuery;
use App\ReadModels\RecruitmentManagement\Queries\TransferCampaignWorkspaceQuery;
use App\ReadModels\Roster\Queries\MemberCapabilityProfileQuery;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class CapabilityReadModelBudgetAndTelemetryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_new_read_surfaces_stay_within_query_budgets_and_emit_metadata_only(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account('acceptance-telemetry@example.test');
        $actor = $scenario->player($account->userId, 79201);
        $alliance = $scenario->alliance($actor);
        $entryReference = $scenario->roster($actor, $alliance);
        $entry = AllianceRosterEntry::query()->findOrFail($entryReference->rosterEntryId);
        $event = $this->bearHunt($actor, $alliance);
        $candidate = RecruitmentCandidate::query()->create([
            'alliance_id' => $alliance->allianceId,
            'player_id' => $actor->playerId,
            'full_name' => 'Sensitive Candidate Name',
            'email' => 'sensitive-candidate@example.test',
            'source' => 'acceptance',
            'stage' => RecruitmentStage::Screening,
            'submitted_at' => now()->subDay(),
        ]);
        $trackingId = app(StartTrackingKingdomAlliance::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'current_name' => 'Sensitive Tracked Alliance',
                'current_tag' => 'SAFE',
                'game_alliance_id' => 'safe-telemetry-target',
            ],
        );
        $occurrenceId = (string) $event->occurrences()->firstOrFail()->id;

        Log::spy();

        [$rally, $rallyQueries] = $this->measure(fn (): array => app(RallyRosterBuilderQuery::class)->forEvent(
            $actor->playerId,
            $event,
            [[
                'occurrenceId' => $occurrenceId,
                'startsAt' => now()->addDay()->toIso8601String(),
                'groups' => [],
            ]],
            [],
            [],
        ));
        [$member, $memberQueries] = $this->measure(fn (): array => app(MemberCapabilityProfileQuery::class)->forPlayer(
            $actor->playerId,
            $alliance->allianceId,
            $entry,
            $actor,
        ));
        [$campaign, $campaignQueries] = $this->measure(fn (): array => app(TransferCampaignWorkspaceQuery::class)->forCandidate(
            $actor->playerId,
            $alliance->allianceId,
            $candidate,
        ));
        [$timeline, $timelineQueries] = $this->measure(fn (): array => app(KingdomIntelligenceTimelineQuery::class)->forTrackedAlliance(
            $actor->playerId,
            $alliance->allianceId,
            $trackingId,
        ));
        [$command, $commandQueries] = $this->measure(fn (): ?array => app(AllianceCommandQuery::class)->for(
            $account->userId,
            $actor,
            $alliance->allianceId,
        ));
        self::assertNotNull($command);
        [$briefs, $briefQueries] = $this->measure(fn (): array => app(OfficerBriefQuery::class)->for(
            $actor,
            $alliance->allianceId,
            $command,
        ));

        self::assertSame('empty', $rally[0]['state']);
        self::assertSame('available', $member['eventAccess']);
        self::assertSame('linked', $campaign['playerLink']);
        self::assertSame([], $timeline);
        self::assertCount(3, $briefs);

        self::assertLessThanOrEqual(12, $rallyQueries);
        self::assertLessThanOrEqual(35, $memberQueries);
        self::assertLessThanOrEqual(20, $campaignQueries);
        self::assertLessThanOrEqual(12, $timelineQueries);
        self::assertLessThanOrEqual(65, $commandQueries);
        self::assertLessThanOrEqual(35, $briefQueries);

        foreach ([
            'rally_builder.rendered',
            'member_capability.rendered',
            'transfer_campaign.rendered',
            'intelligence_timeline.rendered',
            'alliance_command.rendered',
            'officer_briefs.rendered',
        ] as $message) {
            Log::shouldHaveReceived('debug')
                ->withArgs(function (string $actual, array $context) use ($message): bool {
                    if ($actual !== $message) {
                        return false;
                    }

                    $this->assertSafeTelemetry($context);
                    $encoded = json_encode($context, JSON_THROW_ON_ERROR);
                    self::assertStringNotContainsString('Sensitive Candidate Name', $encoded);
                    self::assertStringNotContainsString('sensitive-candidate@example.test', $encoded);
                    self::assertStringNotContainsString('Sensitive Tracked Alliance', $encoded);

                    return true;
                })
                ->once();
        }
    }

    /** @return array{mixed,int} */
    private function measure(Closure $query): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $result = $query();
            $count = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        return [$result, $count];
    }

    /** @param array<string,mixed> $context */
    private function assertSafeTelemetry(array $context): void
    {
        foreach (array_keys($context) as $key) {
            self::assertTrue(
                str_ends_with($key, '_id')
                    || str_ends_with($key, '_count')
                    || $key === 'reason_codes'
                    || $key === 'duration_ms',
                'Unsafe telemetry key: '.$key,
            );
        }
        self::assertIsArray($context['reason_codes']);
        self::assertIsNumeric($context['duration_ms']);
    }

    private function bearHunt(PlayerReference $actor, AllianceReference $alliance): Event
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
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2),
            title: 'Telemetry Bear Hunt',
            durationMinutes: 60,
        );

        return Event::query()->with(['eventType.workflowDimensions', 'occurrences'])->findOrFail($created->eventId);
    }
}
