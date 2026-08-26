<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Actions\AssignTransferParticipantCohort;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomCondition;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Actions\TransitionTransferReadiness;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class KingdomTransferVisualFixture
{
    public static function seed(): void
    {
        $now = CarbonImmutable::parse('2026-08-23 16:00:00', 'UTC');
        $currentThrough = CarbonImmutable::parse('2099-08-24 16:00:00', 'UTC');
        CarbonImmutable::setTestNow($now);

        $user = User::factory()->create([
            'name' => 'Kingdom Transfer Visual',
            'email' => 'transfer-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $home = Kingdom::query()->create(['number' => 1523, 'status' => 'active']);
        $target = Kingdom::query()->create(['number' => 1524, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $home->id,
            'game_player_id' => 'TRANSFER-VISUAL-A',
            'current_name' => 'Northstar Marshal',
        ]);
        $blocked = Player::query()->create([
            'current_kingdom_id' => $home->id,
            'game_player_id' => 'TRANSFER-VISUAL-B',
            'current_name' => 'Ember Vanguard',
        ]);
        $verify = Player::query()->create([
            'current_kingdom_id' => $home->id,
            'game_player_id' => 'TRANSFER-VISUAL-C',
            'current_name' => 'Frost Envoy',
        ]);

        $allianceId = app(CreateAlliance::class)->handle(
            (string) $actor->id,
            'Northern Crown',
            'northern-crown',
            'en',
            'UTC',
        );
        $rosters = [];
        foreach ([$actor, $blocked, $verify] as $player) {
            $rosters[(string) $player->id] = app(UpsertRosterEntry::class)->handle(
                actorPlayerId: (string) $actor->id,
                allianceId: $allianceId,
                attributes: [
                    'name' => (string) $player->current_name,
                    'game_player_id' => (string) $player->game_player_id,
                    'state' => RosterState::Active,
                ],
                expectedPlayerId: (string) $player->id,
            );
        }

        $windowId = app(SaveTransferWindow::class)->handle(
            $allianceId,
            (string) $actor->id,
            [
                'label' => 'August Kingdom Transfer',
                'pre_transfer_starts_at' => $now->subDays(3)->toIso8601String(),
                'invitational_starts_at' => $now->subDays(2)->toIso8601String(),
                'transfer_opens_at' => $now->subDay()->toIso8601String(),
                'ends_at' => $currentThrough->toIso8601String(),
                'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Century Games Kingdom Transfer event notice',
                'observed_at' => $now->subDays(4)->toIso8601String(),
            ],
        );
        app(SaveTransferGroup::class)->handle(
            $allianceId,
            (string) $actor->id,
            $windowId,
            [
                'official_label' => 'Transfer Group 7',
                'kingdom_numbers' => [1523, 1524],
                'source_type' => TransferSourceType::InGame,
                'source_reference' => 'KingShot Transfer Group screen',
                'observed_at' => $now->subMinutes(45)->toIso8601String(),
            ],
        );
        app(RecordTransferKingdomCondition::class)->handle(
            $allianceId,
            (string) $actor->id,
            $windowId,
            1524,
            125_000_000,
            TransferKingdomClassification::Ordinary,
            TransferSourceType::InGame,
            'KingShot Kingdom 1524 transfer screen',
            $now->subMinutes(30)->toIso8601String(),
        );
        app(CreateTransferPlan::class)->handle(
            $allianceId,
            (string) $actor->id,
            ['label' => 'Northern Crown transfer board', 'transfer_window_id' => $windowId],
        );
        $plan = TransferPlan::query()->where('alliance_id', $allianceId)->firstOrFail();

        app(SaveTransferCohort::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            [
                'name' => 'K1524 Vanguard',
                'direction' => TransferDirection::Outgoing,
                'destination_kingdom' => 1524,
                'coordinator_player_id' => (string) $actor->id,
                'manager_notes' => 'Coordinate departures after final Bear Hunt.',
            ],
        );
        $cohort = TransferCohort::query()->where('transfer_plan_id', $plan->id)->firstOrFail();

        foreach ([$actor, $blocked, $verify] as $player) {
            $roster = $rosters[(string) $player->id];
            app(SaveTransferParticipant::class)->handle(
                $allianceId,
                (string) $actor->id,
                (string) $plan->id,
                [
                    'direction' => TransferDirection::Outgoing,
                    'roster_entry_id' => $roster->rosterEntryId,
                    'destination_kingdom' => 1524,
                ],
            );
            $participant = TransferParticipant::query()
                ->where('transfer_plan_id', $plan->id)
                ->where('player_id', $player->id)
                ->firstOrFail();
            app(AssignTransferParticipantCohort::class)->handle(
                $allianceId,
                (string) $actor->id,
                (string) $plan->id,
                (string) $participant->id,
                (string) $cohort->id,
            );
        }

        $eligible = TransferParticipant::query()->where('player_id', $actor->id)->firstOrFail();
        $gameBlocked = TransferParticipant::query()->where('player_id', $blocked->id)->firstOrFail();
        $needsVerification = TransferParticipant::query()->where('player_id', $verify->id)->firstOrFail();

        self::recordFacts($allianceId, (string) $actor->id, $plan, $eligible, $now, $currentThrough, true, false);
        self::recordFacts($allianceId, (string) $actor->id, $plan, $gameBlocked, $now, $currentThrough, false, false);
        self::recordFacts($allianceId, (string) $actor->id, $plan, $needsVerification, $now, $currentThrough, true, true);

        app(CreateTransferBlocker::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $eligible->id,
            'Confirm alliance hand-off time',
            'Planning remains open even though the Governor is eligible in-game.',
        );
        app(TransitionTransferReadiness::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $eligible->id,
            TransferReadinessState::Blocked,
        );

        app(TransitionTransferReadiness::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $gameBlocked->id,
            TransferReadinessState::Preparing,
        );
        app(TransitionTransferReadiness::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $gameBlocked->id,
            TransferReadinessState::Ready,
        );
        app(TransitionTransferReadiness::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $needsVerification->id,
            TransferReadinessState::Preparing,
        );

        self::seedTransferEvidence(
            $allianceId,
            (string) $actor->id,
            $plan,
            $eligible,
            (string) $target->id,
            $now,
            $currentThrough,
        );
    }

    private static function recordFacts(
        string $allianceId,
        string $actorPlayerId,
        TransferPlan $plan,
        TransferParticipant $participant,
        CarbonImmutable $now,
        CarbonImmutable $currentThrough,
        bool $rulesVerified,
        bool $stalePower,
    ): void {
        $record = app(RecordTransferObservation::class);
        $validUntil = $currentThrough->toIso8601String();
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::GovernorPower,
            118_000_000,
            TransferSourceType::InGame,
            'KingShot Governor transfer screen',
            $now->subMinutes(20)->toIso8601String(),
            ($stalePower ? $now->subMinute() : $currentThrough)->toIso8601String(),
        );
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::TransferScore,
            $participant->observed_name === 'Northstar Marshal' ? 880 : 910,
            TransferSourceType::InGame,
            'KingShot Transfer Score screen',
            $now->subMinutes(18)->toIso8601String(),
            $validUntil,
        );
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::TransferPassesAvailable,
            9,
            TransferSourceType::InGame,
            'KingShot Transfer Pass inventory',
            $now->subMinutes(15)->toIso8601String(),
            $validUntil,
        );
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::TransferPassesRequired,
            9,
            TransferSourceType::InGame,
            'KingShot Kingdom 1524 transfer requirements',
            $now->subMinutes(15)->toIso8601String(),
            $validUntil,
        );
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::InGameRulesVerified,
            $rulesVerified,
            TransferSourceType::InGame,
            'KingShot transfer eligibility screen',
            $now->subMinutes(10)->toIso8601String(),
            $validUntil,
            $rulesVerified ? null : 'KingShot reports an unresolved transfer requirement.',
        );
    }

    private static function seedTransferEvidence(
        string $allianceId,
        string $actorPlayerId,
        TransferPlan $plan,
        TransferParticipant $participant,
        string $targetKingdomId,
        CarbonImmutable $now,
        CarbonImmutable $validUntil,
    ): void {
        $governor = self::evidence(
            $allianceId,
            $actorPlayerId,
            $plan,
            $participant,
            EvidenceKind::TransferGovernorStatus,
            EvidenceLifecycleStatus::NeedsReview,
            'transfer-visual-governor',
            $now->subMinutes(8),
        );
        [$governorClassification, $governorExtraction] = self::machineAttempts(
            $governor,
            EvidenceKind::TransferGovernorStatus,
            'transfer-governor-status/1',
            0.91,
            $now->subMinutes(7),
        );
        EvidenceExtractedField::query()->create([
            'extraction_attempt_id' => $governorExtraction->id,
            'field_key' => 'governor_power',
            'row_ordinal' => 0,
            'raw_text' => 'Governor Power 117,500,000',
            'normalized_value' => '117500000',
            'data_type' => 'integer',
            'confidence' => 0.52,
            'bounding_box' => ['left' => 0.18, 'top' => 0.24, 'width' => 0.42, 'height' => 0.08],
            'warnings' => ['Review low-confidence digits before approval.'],
        ]);
        $governorClassification->forceFill(['reason' => 'Governor status labels match the v1 fixture family.'])->save();

        $score = self::evidence(
            $allianceId,
            $actorPlayerId,
            $plan,
            $participant,
            EvidenceKind::TransferScorePasses,
            EvidenceLifecycleStatus::Approved,
            'transfer-visual-score-pass',
            $now->subMinutes(6),
        );
        [, $scoreExtraction] = self::machineAttempts(
            $score,
            EvidenceKind::TransferScorePasses,
            'transfer-score-passes/1',
            0.97,
            $now->subMinutes(5),
        );
        foreach ([
            ['transfer_score', 'Transfer Score 8,765,432', '8765432', 0.98],
            ['transfer_passes_available', 'Passes Available 9', '9', 0.99],
            ['transfer_passes_required', 'Passes Required 12', '12', 0.96],
        ] as $ordinal => [$key, $raw, $normalized, $confidence]) {
            EvidenceExtractedField::query()->create([
                'extraction_attempt_id' => $scoreExtraction->id,
                'field_key' => $key,
                'row_ordinal' => $ordinal,
                'raw_text' => $raw,
                'normalized_value' => $normalized,
                'data_type' => 'integer',
                'confidence' => $confidence,
                'bounding_box' => null,
                'warnings' => [],
            ]);
        }
        TransferEvidenceReview::query()->create([
            'evidence_id' => $score->id,
            'extraction_attempt_id' => $scoreExtraction->id,
            'alliance_id' => $allianceId,
            'transfer_plan_id' => $plan->id,
            'transfer_participant_id' => $participant->id,
            'transfer_window_id' => $plan->transfer_window_id,
            'target_kingdom_id' => $targetKingdomId,
            'evidence_kind' => EvidenceKind::TransferScorePasses,
            'schema_version' => 'transfer-score-passes/1',
            'revision_number' => 1,
            'status' => EvidenceReviewStatus::Approved,
            'observed_at' => $now->subMinutes(6),
            'valid_until' => $validUntil,
            'transfer_score' => 8_765_432,
            'transfer_passes_available' => 9,
            'transfer_passes_required' => 12,
            'semantic_fingerprint' => hash('sha256', 'transfer-visual-score-pass-reviewed'),
            'reviewed_by_player_id' => $actorPlayerId,
            'reviewed_at' => $now->subMinutes(4),
        ]);

        $invitation = self::evidence(
            $allianceId,
            $actorPlayerId,
            $plan,
            $participant,
            EvidenceKind::TransferInvitation,
            EvidenceLifecycleStatus::Committed,
            'transfer-visual-invitation',
            $now->subMinutes(4),
        );
        [, $invitationExtraction] = self::machineAttempts(
            $invitation,
            EvidenceKind::TransferInvitation,
            'transfer-invitation/1',
            0.96,
            $now->subMinutes(3),
        );
        EvidenceExtractedField::query()->create([
            'extraction_attempt_id' => $invitationExtraction->id,
            'field_key' => 'invitation_status',
            'row_ordinal' => 0,
            'raw_text' => 'Special Invitation Approved',
            'normalized_value' => 'special_approved',
            'data_type' => 'enum',
            'confidence' => 0.96,
            'bounding_box' => null,
            'warnings' => [],
        ]);
        $invitationReview = TransferEvidenceReview::query()->create([
            'evidence_id' => $invitation->id,
            'extraction_attempt_id' => $invitationExtraction->id,
            'alliance_id' => $allianceId,
            'transfer_plan_id' => $plan->id,
            'transfer_participant_id' => $participant->id,
            'transfer_window_id' => $plan->transfer_window_id,
            'target_kingdom_id' => $targetKingdomId,
            'evidence_kind' => EvidenceKind::TransferInvitation,
            'schema_version' => 'transfer-invitation/1',
            'revision_number' => 1,
            'status' => EvidenceReviewStatus::Approved,
            'observed_at' => $now->subMinutes(4),
            'valid_until' => $validUntil,
            'invitation_status' => 'special_approved',
            'semantic_fingerprint' => hash('sha256', 'transfer-visual-invitation-reviewed'),
            'reviewed_by_player_id' => $actorPlayerId,
            'reviewed_at' => $now->subMinutes(2),
        ]);
        TransferEvidenceCommitAttempt::query()->create([
            'evidence_id' => $invitation->id,
            'transfer_review_id' => $invitationReview->id,
            'alliance_id' => $allianceId,
            'status' => EvidenceCommitStatus::Succeeded,
            'idempotency_key' => hash('sha256', 'transfer-visual-invitation-idempotency'),
            'destination_action' => 'RecordTransferInvitationEvidence',
            'destination_receipt_id' => (string) Str::ulid(),
            'destination_receipt' => ['observation_id' => (string) Str::ulid()],
            'started_by_player_id' => $actorPlayerId,
            'started_at' => $now->subMinutes(2),
            'completed_at' => $now->subMinute(),
        ]);

        $governor->forceFill(['visual_duplicate_evidence_id' => $score->id, 'visual_duplicate_distance' => 4])->save();
    }

    private static function evidence(
        string $allianceId,
        string $actorPlayerId,
        TransferPlan $plan,
        TransferParticipant $participant,
        EvidenceKind $kind,
        EvidenceLifecycleStatus $status,
        string $seed,
        CarbonImmutable $createdAt,
    ): GameEvidence {
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => null,
            'transfer_plan_id' => $plan->id,
            'transfer_participant_id' => $participant->id,
            'expected_kind' => $kind,
            'kind' => $kind,
            'lifecycle_status' => $status,
            'original_name' => $seed.'.png',
            'disk' => 'local',
            'path' => null,
            'mime_type' => 'image/png',
            'size_bytes' => 245_760,
            'width' => 1170,
            'height' => 2532,
            'sha256' => hash('sha256', $seed),
            'perceptual_hash' => substr(hash('sha256', $seed.'-visual'), 0, 16),
            'uploaded_by_player_id' => $actorPlayerId,
            'scanned_at' => $createdAt,
        ]);
        $evidence->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $evidence;
    }

    /** @return array{EvidenceClassificationAttempt, EvidenceExtractionAttempt} */
    private static function machineAttempts(
        GameEvidence $evidence,
        EvidenceKind $kind,
        string $schemaVersion,
        float $confidence,
        CarbonImmutable $at,
    ): array {
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'transfer-evidence-classifier',
            'classifier_version' => '1',
            'input_sha256' => $evidence->sha256,
            'ocr_engine' => 'visual-fixture',
            'ocr_version' => '1',
            'ocr_language' => 'en',
            'classified_kind' => $kind,
            'confidence' => $confidence,
            'reason' => 'Synthetic deterministic visual fixture.',
            'started_at' => $at,
            'completed_at' => $at,
        ]);
        $extraction = EvidenceExtractionAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'classification_attempt_id' => $classification->id,
            'status' => EvidenceAttemptStatus::Completed,
            'extractor_key' => $kind->value.'-extractor',
            'extractor_version' => '1',
            'schema_version' => $schemaVersion,
            'input_sha256' => $evidence->sha256,
            'overall_confidence' => $confidence,
            'field_count' => 1,
            'started_at' => $at,
            'completed_at' => $at,
        ]);

        return [$classification, $extraction];
    }
}
