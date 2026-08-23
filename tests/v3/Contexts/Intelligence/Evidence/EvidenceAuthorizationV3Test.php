<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\CommitReviewedBearHuntEvidence;
use App\Contexts\Intelligence\Evidence\Actions\DeleteEvidence;
use App\Contexts\Intelligence\Evidence\Actions\ResolveSemanticDuplicate;
use App\Contexts\Intelligence\Evidence\Actions\SaveEvidenceReview;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReviewRow;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\Actions\RemoveBearHuntBattleReport;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EvidenceAuthorizationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_review_commit_delete_and_domain_correction_require_current_event_manage_authority(): void
    {
        $scenario = new ScenarioFactory;
        $ownerAccount = $scenario->authUser();
        $owner = $scenario->player((int) $ownerAccount->id, 59115);
        $alliance = $scenario->alliance($owner);
        $scenario->roster($owner, $alliance);
        $occurrenceId = $this->bearHunt($owner, $alliance->allianceId);
        [$evidence, $extractionId, $review] = $this->reviewFixture($owner, $alliance->allianceId, $occurrenceId);

        $outsiderAccount = $scenario->authUser();
        $outsider = $scenario->player((int) $outsiderAccount->id, 59115);

        $this->assertAuthorizationDenied(function () use ($outsider, $occurrenceId, $evidence, $extractionId, $owner): void {
            app(SaveEvidenceReview::class)->handle(
                actorPlayerId: $outsider->playerId,
                occurrenceId: $occurrenceId,
                evidenceId: (string) $evidence->id,
                extractionAttemptId: $extractionId,
                rows: [[
                    'row_ordinal' => 1,
                    'included' => true,
                    'player_id' => $owner->playerId,
                    'player_name' => $owner->currentName,
                    'reported_rank' => 1,
                    'damage_points' => 100,
                    'correction_reason' => null,
                ]],
            );
        });

        $this->assertAuthorizationDenied(function () use ($outsider, $occurrenceId, $review): void {
            app(CommitReviewedBearHuntEvidence::class)->handle(
                $outsider->playerId,
                $occurrenceId,
                (string) $review->id,
            );
        });

        $this->assertAuthorizationDenied(function () use ($outsider, $occurrenceId, $evidence): void {
            app(DeleteEvidence::class)->handle($outsider->playerId, $occurrenceId, (string) $evidence->id);
        });

        $report = app(RecordBearHuntBattleReport::class)->handle(
            actorPlayerId: $owner->playerId,
            occurrenceId: $occurrenceId,
            sourceEvidenceId: (string) $evidence->id,
            sourceCommitAttemptId: (string) Str::ulid(),
            idempotencyKey: hash('sha256', 'authorization-report-idempotency'),
            reportFingerprint: hash('sha256', 'authorization-report-fingerprint'),
            reportTimestampText: '2026-08-22 13:05:23',
            entries: [[
                'player_id' => $owner->playerId,
                'reported_rank' => 1,
                'damage_points' => 100,
            ]],
        );

        $this->assertAuthorizationDenied(function () use ($outsider, $report): void {
            app(RemoveBearHuntBattleReport::class)->handle(
                $outsider->playerId,
                $report->reportId,
                'Outsiders cannot remove an Alliance Bear Hunt battle report.',
            );
        });
    }

    public function test_foreign_evidence_and_reviews_are_not_resolved_inside_an_authorized_occurrence(): void
    {
        $scenario = new ScenarioFactory;
        $ownerAccount = $scenario->authUser();
        $owner = $scenario->player((int) $ownerAccount->id, 59116);
        $ownerAlliance = $scenario->alliance($owner);
        $scenario->roster($owner, $ownerAlliance);
        $ownerOccurrenceId = $this->bearHunt($owner, $ownerAlliance->allianceId);

        $foreignAccount = $scenario->authUser();
        $foreignOwner = $scenario->player((int) $foreignAccount->id, 59116);
        $foreignAlliance = $scenario->alliance($foreignOwner);
        $scenario->roster($foreignOwner, $foreignAlliance);
        $foreignOccurrenceId = $this->bearHunt($foreignOwner, $foreignAlliance->allianceId);
        [$foreignEvidence, $foreignExtractionId, $foreignReview] = $this->reviewFixture(
            $foreignOwner,
            $foreignAlliance->allianceId,
            $foreignOccurrenceId,
        );

        $this->assertNotFound(function () use ($owner, $ownerOccurrenceId, $foreignEvidence, $foreignExtractionId): void {
            app(SaveEvidenceReview::class)->handle(
                actorPlayerId: $owner->playerId,
                occurrenceId: $ownerOccurrenceId,
                evidenceId: (string) $foreignEvidence->id,
                extractionAttemptId: $foreignExtractionId,
                rows: [[
                    'row_ordinal' => 1,
                    'included' => false,
                    'player_id' => null,
                    'player_name' => 'Unknown',
                    'reported_rank' => 1,
                    'damage_points' => 100,
                    'correction_reason' => null,
                ]],
            );
        });

        $this->assertNotFound(function () use ($owner, $ownerOccurrenceId, $foreignReview): void {
            app(CommitReviewedBearHuntEvidence::class)->handle(
                $owner->playerId,
                $ownerOccurrenceId,
                (string) $foreignReview->id,
            );
        });

        $this->assertNotFound(function () use ($owner, $ownerOccurrenceId, $foreignReview): void {
            app(ResolveSemanticDuplicate::class)->handle(
                $owner->playerId,
                $ownerOccurrenceId,
                (string) $foreignReview->id,
                'This should never resolve a foreign review.',
            );
        });
    }

    private function bearHunt(PlayerReference $actor, string $allianceId): string
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return $created->firstOccurrenceId;
    }

    /** @return array{0:GameEvidence,1:string,2:EvidenceReview} */
    private function reviewFixture(
        PlayerReference $owner,
        string $allianceId,
        string $occurrenceId,
    ): array {
        $sha256 = hash('sha256', 'authorization-source-'.$occurrenceId);
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => EvidenceLifecycleStatus::Approved,
            'original_name' => 'authorization.png',
            'disk' => 'local',
            'path' => 'evidence/authorization-'.$occurrenceId.'.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
            'width' => 1080,
            'height' => 1920,
            'sha256' => $sha256,
            'uploaded_by_player_id' => $owner->playerId,
            'scanned_at' => now(),
        ]);
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'authorization-fixture',
            'classifier_version' => '1',
            'input_sha256' => $sha256,
            'classified_kind' => EvidenceKind::BearHuntBattleReport,
            'confidence' => 0.99,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $extraction = EvidenceExtractionAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'classification_attempt_id' => $classification->id,
            'status' => EvidenceAttemptStatus::Completed,
            'extractor_key' => 'authorization-fixture',
            'extractor_version' => '1',
            'schema_version' => 'bear-hunt-report/1',
            'input_sha256' => $sha256,
            'overall_confidence' => 0.99,
            'field_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $review = EvidenceReview::query()->create([
            'evidence_id' => $evidence->id,
            'extraction_attempt_id' => $extraction->id,
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'revision_number' => 1,
            'status' => EvidenceReviewStatus::Approved,
            'semantic_fingerprint' => hash('sha256', 'authorization-semantic-'.$occurrenceId),
            'reviewed_by_player_id' => $owner->playerId,
            'reviewed_at' => now(),
        ]);
        EvidenceReviewRow::query()->create([
            'review_id' => $review->id,
            'row_ordinal' => 1,
            'player_id' => $owner->playerId,
            'player_name' => $owner->currentName,
            'reported_rank' => 1,
            'damage_points' => 100,
            'included' => true,
        ]);

        return [$evidence, (string) $extraction->id, $review];
    }

    /** @param callable():void $operation */
    private function assertAuthorizationDenied(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected current authority to be rejected.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
    }

    /** @param callable():void $operation */
    private function assertNotFound(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected the foreign Evidence or Review to remain unresolved.');
        } catch (ModelNotFoundException) {
            self::assertTrue(true);
        }
    }
}
