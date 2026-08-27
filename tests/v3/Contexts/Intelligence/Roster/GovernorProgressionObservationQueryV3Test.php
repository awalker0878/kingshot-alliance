<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Roster;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionObservation;
use App\Contexts\Intelligence\Roster\Queries\GovernorProgressionObservationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GovernorProgressionObservationQueryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_partial_observations_update_only_observed_facts_and_preserve_provenance(): void
    {
        [$actor, $alliance, $entry] = $this->scope(62301);
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $first = $this->observation(
            $alliance->allianceId,
            $entry->rosterEntryId,
            $actor->playerId,
            EvidenceKind::GovernorHeroDetail,
            ['hero_id' => 'ava', 'level' => 70, 'star' => 4],
            '2026-08-24T12:00:00Z',
            $dataset->id,
            $dataset->checksum,
            'evidence-first',
            'review-first',
        );
        $second = $this->observation(
            $alliance->allianceId,
            $entry->rosterEntryId,
            $actor->playerId,
            EvidenceKind::GovernorHeroDetail,
            ['hero_id' => 'ava', 'level' => 80],
            '2026-08-26T12:00:00Z',
            $dataset->id,
            $dataset->checksum,
            'evidence-second',
            'review-second',
        );

        $state = app(GovernorProgressionObservationQuery::class)->forRosterEntry($alliance->allianceId, $entry->rosterEntryId);
        $ava = $state['current']['heroes']['ava'];

        self::assertSame(80, $ava['facts']['level']['value']);
        self::assertSame((string) $second->id, $ava['facts']['level']['observationId']);
        self::assertSame('evidence-second', $ava['facts']['level']['evidenceId']);
        self::assertSame(4, $ava['facts']['star']['value']);
        self::assertSame((string) $first->id, $ava['facts']['star']['observationId']);
        self::assertSame('evidence-first', $ava['facts']['star']['evidenceId']);
        self::assertSame('observed_present', $ava['membership']['value']);
        self::assertSame($dataset->id, $ava['facts']['level']['datasetId']);
        self::assertSame($dataset->checksum, $ava['facts']['level']['datasetChecksum']);
        self::assertSame('2026-08-26T12:00:00+00:00', $state['last_updated_at']);
        self::assertCount(2, $state['history']);
    }

    public function test_partial_roster_does_not_mark_unshown_heroes_absent_but_complete_roster_does(): void
    {
        [$actor, $alliance, $entry] = $this->scope(62302);
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $this->observation(
            $alliance->allianceId,
            $entry->rosterEntryId,
            $actor->playerId,
            EvidenceKind::GovernorHeroDetail,
            ['hero_id' => 'charles', 'level' => 70],
            '2026-08-23T12:00:00Z',
            $dataset->id,
            $dataset->checksum,
            'evidence-charles',
            'review-charles',
        );
        $this->observation(
            $alliance->allianceId,
            $entry->rosterEntryId,
            $actor->playerId,
            EvidenceKind::GovernorHeroRoster,
            ['heroes' => [['hero_id' => 'ava', 'level' => 80]], 'complete_roster_capture' => false],
            '2026-08-24T12:00:00Z',
            $dataset->id,
            $dataset->checksum,
            'evidence-partial',
            'review-partial',
        );

        $query = app(GovernorProgressionObservationQuery::class);
        $partial = $query->forRosterEntry($alliance->allianceId, $entry->rosterEntryId);
        self::assertSame('observed_present', $partial['current']['heroes']['charles']['membership']['value']);
        self::assertNull($partial['current']['completeRosterCapture']);

        $complete = $this->observation(
            $alliance->allianceId,
            $entry->rosterEntryId,
            $actor->playerId,
            EvidenceKind::GovernorHeroRoster,
            ['heroes' => [['hero_id' => 'ava', 'level' => 80]], 'complete_roster_capture' => true],
            '2026-08-25T12:00:00Z',
            $dataset->id,
            $dataset->checksum,
            'evidence-complete',
            'review-complete',
        );

        $state = $query->forRosterEntry($alliance->allianceId, $entry->rosterEntryId);
        self::assertSame('observed_absent', $state['current']['heroes']['charles']['membership']['value']);
        self::assertSame(70, $state['current']['heroes']['charles']['facts']['level']['value']);
        self::assertTrue($state['current']['completeRosterCapture']['value']);
        self::assertSame((string) $complete->id, $state['current']['completeRosterCapture']['observationId']);
    }

    public function test_preview_uses_same_projection_without_persisting_hypothetical_observation(): void
    {
        [$actor, $alliance, $entry] = $this->scope(62303);
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $this->observation(
            $alliance->allianceId,
            $entry->rosterEntryId,
            $actor->playerId,
            EvidenceKind::GovernorProfile,
            ['power' => '1000000'],
            '2026-08-24T12:00:00Z',
            $dataset->id,
            $dataset->checksum,
            'evidence-existing',
            'review-existing',
        );

        $beforeCount = GovernorProgressionObservation::query()->count();
        $preview = app(GovernorProgressionObservationQuery::class)->preview(
            $alliance->allianceId,
            $entry->rosterEntryId,
            EvidenceKind::GovernorProfile,
            ['power' => '2000000'],
            '2026-08-26T12:00:00Z',
            $dataset->id,
            $dataset->checksum,
            'evidence-preview',
            'review-preview',
        );

        self::assertSame('1000000', $preview['before']['profile']['power']['value']);
        self::assertSame('2000000', $preview['after']['profile']['power']['value']);
        self::assertSame($beforeCount, GovernorProgressionObservation::query()->count());
    }

    /** @return array{0:PlayerReference,1:AllianceReference,2:RosterEntryReference} */
    private function scope(int $gamePlayerId): array
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, $gamePlayerId);
        $alliance = $scenario->alliance($actor);
        $entry = $scenario->roster($actor, $alliance);

        return [$actor, $alliance, $entry];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function observation(
        string $allianceId,
        string $rosterEntryId,
        string $playerId,
        EvidenceKind $kind,
        array $payload,
        string $capturedAt,
        string $datasetId,
        string $datasetChecksum,
        string $evidenceId,
        string $reviewId,
    ): GovernorProgressionObservation {
        return GovernorProgressionObservation::query()->create([
            'alliance_id' => $allianceId,
            'roster_entry_id' => $rosterEntryId,
            'player_id' => $playerId,
            'kind' => $kind,
            'payload' => $payload,
            'captured_at' => $capturedAt,
            'progression_dataset_id' => $datasetId,
            'progression_dataset_checksum' => $datasetChecksum,
            'source' => 'screenshot_evidence',
            'evidence_id' => $evidenceId,
            'evidence_review_id' => $reviewId,
            'destination_idempotency_key' => hash('sha256', $evidenceId.'|'.$reviewId),
            'accepted_by_player_id' => $playerId,
            'accepted_at' => $capturedAt,
        ]);
    }
}
