<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\SaveTransferEvidenceReview;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TransferEvidenceReviewV3Test extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-08-26T04:00:00Z');
        CarbonImmutable::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_invitation_review_requires_explicit_validity_boundary(): void
    {
        $scenario = $this->outgoingScenario(7701, 7702);
        [$evidence, $extraction] = $this->evidence(
            $scenario,
            EvidenceKind::TransferInvitation,
            'transfer-invitation/1',
            [
                ['invitation_status', 'Special Invite Approved', 'special_approved', 'enum', 0.96],
                ['target_kingdom_number', 'Kingdom #7702', '7702', 'integer', 0.94],
            ],
        );

        $this->expectException(ValidationException::class);
        app(SaveTransferEvidenceReview::class)->handle(
            actorPlayerId: $scenario['actor']->playerId,
            allianceId: $scenario['alliance']->allianceId,
            planId: (string) $scenario['plan']->id,
            participantId: (string) $scenario['participant']->id,
            evidenceId: (string) $evidence->id,
            extractionAttemptId: (string) $extraction->id,
            observedAt: $this->now->subMinutes(10)->toIso8601String(),
            validUntil: null,
            invitationStatus: 'special_approved',
            targetKingdomNumber: 7702,
        );
    }

    public function test_review_rejects_extracted_field_outside_registered_schema(): void
    {
        $scenario = $this->outgoingScenario(7711, 7712);
        [$evidence, $extraction] = $this->evidence(
            $scenario,
            EvidenceKind::TransferGovernorStatus,
            'transfer-governor-status/1',
            [
                ['governor_power', 'Governor Power 70,000,000', '70000000', 'integer', 0.96],
                ['transfer_score', 'Transfer Score 99,999,999', '99999999', 'integer', 0.96],
            ],
        );

        $this->expectException(ValidationException::class);
        app(SaveTransferEvidenceReview::class)->handle(
            actorPlayerId: $scenario['actor']->playerId,
            allianceId: $scenario['alliance']->allianceId,
            planId: (string) $scenario['plan']->id,
            participantId: (string) $scenario['participant']->id,
            evidenceId: (string) $evidence->id,
            extractionAttemptId: (string) $extraction->id,
            observedAt: $this->now->subMinutes(10)->toIso8601String(),
            validUntil: $this->now->addHour()->toIso8601String(),
            governorPower: 70_000_000,
        );
    }

    public function test_review_keeps_machine_candidate_immutable_when_human_corrects_value(): void
    {
        $scenario = $this->outgoingScenario(7721, 7722);
        [$evidence, $extraction] = $this->evidence(
            $scenario,
            EvidenceKind::TransferGovernorStatus,
            'transfer-governor-status/1',
            [
                ['governor_power', 'Governor Power 70,000,008', '70000008', 'integer', 0.71],
            ],
        );
        $source = EvidenceExtractedField::query()->where('extraction_attempt_id', $extraction->id)->firstOrFail();

        $reviewId = app(SaveTransferEvidenceReview::class)->handle(
            actorPlayerId: $scenario['actor']->playerId,
            allianceId: $scenario['alliance']->allianceId,
            planId: (string) $scenario['plan']->id,
            participantId: (string) $scenario['participant']->id,
            evidenceId: (string) $evidence->id,
            extractionAttemptId: (string) $extraction->id,
            observedAt: $this->now->subMinutes(10)->toIso8601String(),
            validUntil: $this->now->addHour()->toIso8601String(),
            governorPower: 70_000_000,
        );

        $review = TransferEvidenceReview::query()->findOrFail($reviewId);
        self::assertSame(70_000_000, (int) $review->governor_power);
        self::assertSame('70000008', (string) $source->refresh()->normalized_value);
        self::assertEqualsWithDelta(0.71, (float) $source->confidence, 0.001);
    }

    /**
     * @param  array{
     *   actor: PlayerReference,
     *   alliance: AllianceReference,
     *   roster: RosterEntryReference,
     *   plan: TransferPlan,
     *   participant: TransferParticipant,
     *   targetNumber: int
     * }  $scenario
     * @param  list<array{string, string, string, string, float}>  $fields
     *
     * @return array{GameEvidence, EvidenceExtractionAttempt}
     */
    private function evidence(array $scenario, EvidenceKind $kind, string $schemaVersion, array $fields): array
    {
        $hash = hash('sha256', $kind->value.'-'.count($fields).'-'.$scenario['participant']->id);
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $scenario['alliance']->allianceId,
            'occurrence_id' => null,
            'transfer_plan_id' => $scenario['plan']->id,
            'transfer_participant_id' => $scenario['participant']->id,
            'expected_kind' => $kind,
            'kind' => $kind,
            'lifecycle_status' => EvidenceLifecycleStatus::NeedsReview,
            'original_name' => $kind->value.'.png',
            'disk' => 'local',
            'path' => 'evidence/'.$kind->value.'.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
            'width' => 1080,
            'height' => 1920,
            'sha256' => $hash,
            'uploaded_by_player_id' => $scenario['actor']->playerId,
            'scanned_at' => now(),
        ]);
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'transfer-fixture',
            'classifier_version' => '1',
            'input_sha256' => $hash,
            'classified_kind' => $kind,
            'confidence' => 0.95,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $extraction = EvidenceExtractionAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'classification_attempt_id' => $classification->id,
            'status' => EvidenceAttemptStatus::Completed,
            'extractor_key' => 'transfer-fixture',
            'extractor_version' => '1',
            'schema_version' => $schemaVersion,
            'input_sha256' => $hash,
            'overall_confidence' => 0.9,
            'field_count' => count($fields),
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        foreach ($fields as $ordinal => [$key, $raw, $normalized, $type, $confidence]) {
            EvidenceExtractedField::query()->create([
                'extraction_attempt_id' => $extraction->id,
                'field_key' => $key,
                'row_ordinal' => $ordinal + 1,
                'raw_text' => $raw,
                'normalized_value' => $normalized,
                'data_type' => $type,
                'confidence' => $confidence,
            ]);
        }

        return [$evidence, $extraction];
    }

    /**
     * @return array{
     *   actor: PlayerReference,
     *   alliance: AllianceReference,
     *   roster: RosterEntryReference,
     *   plan: TransferPlan,
     *   participant: TransferParticipant,
     *   targetNumber: int
     * }
     */
    private function outgoingScenario(int $homeNumber, int $targetNumber): array
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $actor = $scenarios->player($account->userId, $homeNumber, 'TRANSFER-REVIEW-'.$homeNumber);
        $alliance = $scenarios->alliance($actor);
        $roster = $scenarios->roster($actor, $alliance, $actor);
        $scenarios->kingdom($targetNumber);

        $windowId = app(SaveTransferWindow::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'label' => 'Evidence review window',
                'pre_transfer_starts_at' => $this->now->subDays(3)->toIso8601String(),
                'invitational_starts_at' => $this->now->subDays(2)->toIso8601String(),
                'transfer_opens_at' => $this->now->subDay()->toIso8601String(),
                'ends_at' => $this->now->addDay()->toIso8601String(),
                'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Synthetic Transfer Evidence review fixture',
                'observed_at' => $this->now->subDays(4)->toIso8601String(),
            ],
        );
        app(CreateTransferPlan::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            ['label' => 'Evidence review plan', 'transfer_window_id' => $windowId],
        );
        $plan = TransferPlan::query()->where('alliance_id', $alliance->allianceId)->where('transfer_window_id', $windowId)->firstOrFail();
        app(SaveTransferParticipant::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            (string) $plan->id,
            [
                'direction' => TransferDirection::Outgoing,
                'roster_entry_id' => $roster->rosterEntryId,
                'destination_kingdom' => $targetNumber,
            ],
        );
        $participant = TransferParticipant::query()->where('transfer_plan_id', $plan->id)->firstOrFail();

        return compact('actor', 'alliance', 'roster', 'plan', 'participant', 'targetNumber');
    }
}
