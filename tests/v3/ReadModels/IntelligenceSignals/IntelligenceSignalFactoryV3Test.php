<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\IntelligenceSignals;

use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionObservation;
use App\ReadModels\IntelligenceSignals\Enums\IntelligenceSignalType;
use App\ReadModels\IntelligenceSignals\Services\IntelligenceSignalFactory;
use Illuminate\Support\Carbon;
use Tests\v3\TestCase;

final class IntelligenceSignalFactoryV3Test extends TestCase
{
    public function test_alliance_material_change_is_deterministic_and_source_cited(): void
    {
        config()->set('intelligence.change_detection.alliance_power_absolute', 100_000_000);
        config()->set('intelligence.change_detection.alliance_power_percent', 5.0);
        $factory = app(IntelligenceSignalFactory::class);
        $asOf = Carbon::parse('2026-08-22T12:00:00Z');
        $previous = $this->allianceObservation('obs-old', 3_900_000_000, 90, '2026-08-15T12:00:00Z');
        $current = $this->allianceObservation('obs-new', 4_200_000_000, 94, '2026-08-22T12:00:00Z');

        $first = $factory->allianceObservationChanges($current, $previous, $asOf);
        $second = $factory->allianceObservationChanges($current, $previous, $asOf);

        self::assertCount(2, $first);
        self::assertSame($first[0]->fingerprint, $second[0]->fingerprint);
        self::assertSame(IntelligenceSignalType::ObservationChange, $first[0]->type);
        self::assertSame('power', $first[0]->metric);
        self::assertSame('300000000', $first[0]->delta);
        self::assertSame(['obs-old', 'obs-new'], $first[0]->sourceRecordIds);
        self::assertStringContainsString('4,200,000,000', $first[0]->summary);
        self::assertStringNotContainsString('attack', strtolower($first[0]->summary));
    }

    public function test_below_materiality_alliance_change_produces_no_signal(): void
    {
        config()->set('intelligence.change_detection.alliance_power_absolute', 100_000_000);
        config()->set('intelligence.change_detection.alliance_power_percent', 5.0);
        config()->set('intelligence.change_detection.member_count_absolute', 3);
        $factory = app(IntelligenceSignalFactory::class);

        $signals = $factory->allianceObservationChanges(
            $this->allianceObservation('obs-new', 3_920_000_000, 91, '2026-08-22T12:00:00Z'),
            $this->allianceObservation('obs-old', 3_900_000_000, 90, '2026-08-15T12:00:00Z'),
            Carbon::parse('2026-08-22T12:00:00Z'),
        );

        self::assertSame([], $signals);
    }

    public function test_stale_boundary_keeps_exact_cutoff_current(): void
    {
        config()->set('intelligence.change_detection.alliance_observation_stale_days', 30);
        $factory = app(IntelligenceSignalFactory::class);
        $asOf = Carbon::parse('2026-08-31T00:00:00Z');

        self::assertNull($factory->staleAllianceObservation(
            $this->allianceObservation('cutoff', 1, 1, '2026-08-01T00:00:00Z'),
            $asOf,
        ));

        $stale = $factory->staleAllianceObservation(
            $this->allianceObservation('old', 1, 1, '2026-07-31T23:59:59Z'),
            $asOf,
        );
        self::assertNotNull($stale);
        self::assertSame('stale', $stale->state);
    }

    public function test_disappearance_requires_complete_source_semantics(): void
    {
        $factory = app(IntelligenceSignalFactory::class);
        $previousAt = Carbon::parse('2026-08-15T00:00:00Z');
        $currentAt = Carbon::parse('2026-08-22T00:00:00Z');
        $asOf = Carbon::parse('2026-08-22T00:00:00Z');

        self::assertNull($factory->trackedEntityStateChange(
            'tracked-1', true, false, false, 'capture-1', 'capture-2', $previousAt, $currentAt, $asOf,
        ));

        $signal = $factory->trackedEntityStateChange(
            'tracked-1', true, false, true, 'capture-1', 'capture-2', $previousAt, $currentAt, $asOf,
        );
        self::assertNotNull($signal);
        self::assertSame(IntelligenceSignalType::TrackedEntityStateChanged, $signal->type);
        self::assertSame('disappeared', $signal->state);
    }

    public function test_progression_change_preserves_evidence_and_dataset_provenance(): void
    {
        $factory = app(IntelligenceSignalFactory::class);
        $previous = $this->progressionObservation('prog-old', 100, '2026-08-01T00:00:00Z', 'evidence-old', 'review-old');
        $current = $this->progressionObservation('prog-new', 110, '2026-08-20T00:00:00Z', 'evidence-new', 'review-new');

        $signal = $factory->progressionChange($current, $previous, Carbon::parse('2026-08-20T00:00:00Z'));

        self::assertNotNull($signal);
        self::assertSame(IntelligenceSignalType::ProgressionChanged, $signal->type);
        self::assertSame(['evidence-old', 'evidence-new'], $signal->evidenceIds);
        self::assertSame('dataset-1', $signal->datasetId);
        self::assertSame('checksum-1', $signal->datasetChecksum);
        self::assertContains('power', $signal->metadata['changedPaths']);
    }

    public function test_transfer_expiry_uses_destination_observation_valid_until(): void
    {
        config()->set('intelligence.change_detection.transfer_expiring_days', 7);
        $factory = app(IntelligenceSignalFactory::class);
        $observation = new TransferObservation;
        $observation->forceFill([
            'id' => 'transfer-observation-1',
            'transfer_participant_id' => 'participant-1',
            'kind' => TransferObservationKind::GovernorPower,
            'source_type' => TransferSourceType::Evidence,
            'source_reference' => 'evidence:123',
            'observed_at' => Carbon::parse('2026-08-20T00:00:00Z'),
            'valid_until' => Carbon::parse('2026-09-03T00:00:00Z'),
            'evidence_id' => 'evidence-123',
        ]);

        $signal = $factory->transferExpiry($observation, Carbon::parse('2026-08-28T00:00:00Z'));

        self::assertNotNull($signal);
        self::assertSame('expiring', $signal->state);
        self::assertSame('GameWorld/KingdomTransfers', $signal->sourceOwner);
        self::assertSame(['transfer-observation-1'], $signal->sourceRecordIds);
    }

    public function test_bear_hunt_trend_requires_three_comparable_monotonic_runs(): void
    {
        config()->set('intelligence.change_detection.bear_hunt_minimum_runs', 3);
        $factory = app(IntelligenceSignalFactory::class);
        $asOf = Carbon::parse('2026-08-28T00:00:00Z');

        self::assertNull($factory->bearHuntTrend('alliance', 'a1', 'alliance_damage', [
            ['recordId' => 'r1', 'observedAt' => '2026-08-01T00:00:00Z', 'value' => 100],
            ['recordId' => 'r2', 'observedAt' => '2026-08-08T00:00:00Z', 'value' => 120],
        ], $asOf));

        $trend = $factory->bearHuntTrend('alliance', 'a1', 'alliance_damage', [
            ['recordId' => 'r1', 'observedAt' => '2026-08-01T00:00:00Z', 'value' => 100],
            ['recordId' => 'r2', 'observedAt' => '2026-08-08T00:00:00Z', 'value' => 120],
            ['recordId' => 'r3', 'observedAt' => '2026-08-15T00:00:00Z', 'value' => 150],
        ], $asOf);

        self::assertNotNull($trend);
        self::assertSame('increased', $trend->state);
        self::assertSame(50, $trend->delta);
    }

    public function test_recruitment_signal_uses_explicit_stage_history(): void
    {
        $factory = app(IntelligenceSignalFactory::class);
        $history = new RecruitmentStageHistory;
        $history->forceFill([
            'id' => 'stage-1',
            'candidate_id' => 'candidate-1',
            'from_stage' => RecruitmentStage::Screening,
            'to_stage' => RecruitmentStage::Contacted,
            'changed_at' => Carbon::parse('2026-08-27T00:00:00Z'),
        ]);

        $signal = $factory->recruitmentChange($history, Carbon::parse('2026-08-28T00:00:00Z'));

        self::assertSame('screening', $signal->previousValue);
        self::assertSame('contacted', $signal->currentValue);
        self::assertSame('Alliance/Recruitment', $signal->sourceOwner);
    }

    private function allianceObservation(string $id, int $power, int $members, string $capturedAt): KingdomAllianceObservation
    {
        $observation = new KingdomAllianceObservation;
        $observation->forceFill([
            'id' => $id,
            'alliance_id' => 'alliance-1',
            'tracked_kingdom_alliance_id' => 'tracked-1',
            'kingdom_alliance_id' => 'kingdom-alliance-1',
            'observed_name' => 'ABC Alliance',
            'observed_tag' => 'ABC',
            'power' => $power,
            'member_count' => $members,
            'captured_at' => Carbon::parse($capturedAt),
            'source' => 'manual',
            'idempotency_key' => hash('sha256', $id),
        ]);

        return $observation;
    }

    private function progressionObservation(
        string $id,
        int $power,
        string $capturedAt,
        string $evidenceId,
        string $reviewId,
    ): GovernorProgressionObservation {
        $observation = new GovernorProgressionObservation;
        $observation->forceFill([
            'id' => $id,
            'alliance_id' => 'alliance-1',
            'roster_entry_id' => 'roster-1',
            'player_id' => 'player-1',
            'kind' => EvidenceKind::GovernorProfile,
            'payload' => ['power' => $power, 'progression_level' => 30],
            'captured_at' => Carbon::parse($capturedAt),
            'progression_dataset_id' => 'dataset-1',
            'progression_dataset_checksum' => 'checksum-1',
            'source' => 'screenshot_evidence',
            'evidence_id' => $evidenceId,
            'evidence_review_id' => $reviewId,
            'destination_idempotency_key' => hash('sha256', $id),
            'accepted_by_player_id' => 'player-1',
            'accepted_at' => Carbon::parse($capturedAt),
        ]);

        return $observation;
    }
}
