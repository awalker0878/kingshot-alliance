<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Evidence\Actions\ClassifyGameEvidence;
use App\Contexts\Intelligence\Evidence\Actions\CommitReviewedGovernorProgressionEvidence;
use App\Contexts\Intelligence\Evidence\Actions\DeleteGovernorProgressionEvidence;
use App\Contexts\Intelligence\Evidence\Actions\ExtractGameEvidence;
use App\Contexts\Intelligence\Evidence\Actions\NormalizeGovernorProgressionEvidence;
use App\Contexts\Intelligence\Evidence\Actions\SaveGovernorProgressionEvidenceReview;
use App\Contexts\Intelligence\Evidence\Actions\UploadGovernorProgressionEvidence;
use App\Contexts\Intelligence\Evidence\Contracts\OcrEngine;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Jobs\ClassifyGameEvidenceJob;
use App\Contexts\Intelligence\Evidence\Jobs\ExtractGameEvidenceJob;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\ProgressionNormalizationAttempt;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionEvidenceReceipt;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionObservation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class StructuredGovernorProgressionPipelineV3Test extends TestCase
{
    use RefreshDatabase;

    private string $actorId;

    private string $allianceId;

    private string $entryId;

    protected function setUp(): void
    {
        parent::setUp();

        $scenario = new ScenarioFactory;
        $actor = $scenario->player($scenario->account()->userId);
        $alliance = $scenario->alliance($actor);
        $entry = $scenario->roster($actor, $alliance);
        $this->actorId = $actor->playerId;
        $this->allianceId = $alliance->allianceId;
        $this->entryId = $entry->rosterEntryId;
        Storage::fake('local');
        config(['evidence.disk' => 'local']);
        Bus::fake();
    }

    #[DataProvider('structuredKinds')]
    public function test_real_pipeline_requires_review_and_commits_pinned_meaning_once(
        EvidenceKind $kind,
        string $heading,
        string $label,
        string $subject,
        string $canonicalId,
    ): void {
        [$evidence, $normalization] = $this->normalize($kind, "$heading\n$label: $subject Level 1");
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        self::assertSame($dataset->id, $normalization->progression_dataset_id);
        self::assertSame($dataset->checksum, $normalization->progression_dataset_checksum);
        $fields = array_column($normalization->normalized_payload['fields'], 'candidate', 'field_key');
        self::assertSame($subject, $fields[$kind === EvidenceKind::GovernorBuildings ? 'building_name' : 'technology_name']);
        self::assertSame('1', $fields[$kind === EvidenceKind::GovernorBuildings ? 'building_level' : 'research_level']);
        self::assertSame(0, GovernorProgressionObservation::query()->count());

        $reviewId = $this->review($evidence, $normalization, ['states' => [['subject_id' => $subject, 'level' => 1]]]);
        self::assertSame(0, GovernorProgressionObservation::query()->count());
        $commit = app(CommitReviewedGovernorProgressionEvidence::class);
        $first = $commit->handle($this->actorId, $this->allianceId, $this->entryId, $reviewId);
        $replay = $commit->handle($this->actorId, $this->allianceId, $this->entryId, $reviewId);

        self::assertFalse($first->idempotentReplay);
        self::assertTrue($replay->idempotentReplay);
        self::assertSame($first->receiptId, $replay->receiptId);
        self::assertSame($first->observationId, $replay->observationId);
        self::assertSame(1, GovernorProgressionObservation::query()->count());
        self::assertSame(1, GovernorProgressionEvidenceReceipt::query()->count());
        $observation = GovernorProgressionObservation::query()->findOrFail($first->observationId);
        self::assertSame($kind, $observation->kind);
        self::assertSame(['states' => [['subject_id' => $canonicalId, 'state_id' => 'level:1', 'level' => 1]]], $observation->payload);
        self::assertSame($dataset->id, $observation->progression_dataset_id);
        self::assertSame($dataset->checksum, $observation->progression_dataset_checksum);
        self::assertSame((string) $evidence->id, $observation->evidence_id);
        self::assertSame($reviewId, $observation->evidence_review_id);
        self::assertSame(EvidenceLifecycleStatus::Committed, $evidence->fresh()->lifecycle_status);
    }

    #[DataProvider('structuredKinds')]
    public function test_missing_observed_level_stays_unknown_until_explicit_review(
        EvidenceKind $kind,
        string $heading,
        string $label,
        string $subject,
        string $canonicalId,
    ): void {
        [$evidence, $normalization] = $this->normalize($kind, "$heading\n$label: $subject");
        $fields = array_column($normalization->normalized_payload['fields'], 'candidate', 'field_key');
        self::assertArrayNotHasKey($kind === EvidenceKind::GovernorBuildings ? 'building_level' : 'research_level', $fields);

        try {
            $this->review($evidence, $normalization, ['states' => [['subject_id' => $canonicalId]]]);
            self::fail('An unobserved level must not become a zero-level observation.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('payload.states.0.level', $exception->errors());
        }
        self::assertSame(0, GovernorProgressionEvidenceReview::query()->count());
        self::assertSame(0, GovernorProgressionObservation::query()->count());
        self::assertSame(EvidenceLifecycleStatus::NeedsReview, $evidence->fresh()->lifecycle_status);
    }

    public function test_wrong_selected_class_stops_before_extraction_or_review(): void
    {
        $evidence = $this->upload(EvidenceKind::GovernorAcademyResearch, "War Academy Research\nTechnology: Truegold Battalion Level 1");
        app(ClassifyGameEvidence::class)->handle((string) $evidence->id);

        self::assertSame(EvidenceKind::GovernorWarAcademyResearch, $evidence->fresh()->kind);
        self::assertSame(EvidenceLifecycleStatus::Unsupported, $evidence->fresh()->lifecycle_status);
        Bus::assertNotDispatched(ExtractGameEvidenceJob::class);
        self::assertSame(0, EvidenceExtractionAttempt::query()->count());
        self::assertSame(0, ProgressionNormalizationAttempt::query()->count());
        self::assertSame(0, GovernorProgressionObservation::query()->count());
    }

    public function test_foreign_actor_and_roster_scope_cannot_review_or_commit(): void
    {
        [$evidence, $normalization] = $this->normalize(EvidenceKind::GovernorBuildings, "Buildings\nBuilding: Academy Level 1");
        $payload = ['states' => [['subject_id' => 'academy', 'level' => 1]]];
        $scenario = new ScenarioFactory;
        $foreignActor = $scenario->player($scenario->account()->userId);
        $foreignAlliance = $scenario->alliance($foreignActor);
        $foreignEntry = $scenario->roster($foreignActor, $foreignAlliance);
        $review = app(SaveGovernorProgressionEvidenceReview::class);

        try {
            $review->handle($foreignActor->playerId, $this->allianceId, $this->entryId, (string) $evidence->id, (string) $normalization->id, '2026-08-26T12:00:00Z', $payload);
            self::fail('An unrelated actor must not approve another Alliance\'s evidence.');
        } catch (AuthorizationException) {
            self::assertSame(0, GovernorProgressionEvidenceReview::query()->count());
        }
        try {
            $review->handle($foreignActor->playerId, $foreignAlliance->allianceId, $foreignEntry->rosterEntryId, (string) $evidence->id, (string) $normalization->id, '2026-08-26T12:00:00Z', $payload);
            self::fail('Evidence cannot be rebound to a different authorized Alliance.');
        } catch (ModelNotFoundException) {
            self::assertSame(0, GovernorProgressionEvidenceReview::query()->count());
        }

        $reviewId = $this->review($evidence, $normalization, $payload);
        try {
            app(CommitReviewedGovernorProgressionEvidence::class)->handle($foreignActor->playerId, $this->allianceId, $this->entryId, $reviewId);
            self::fail('Commit must reacquire current management authority.');
        } catch (AuthorizationException) {
            self::assertSame(0, GovernorProgressionObservation::query()->count());
            self::assertSame(0, GovernorProgressionEvidenceReceipt::query()->count());
        }
    }

    public function test_normalization_redelivery_preserves_review_and_commit_lifecycle(): void
    {
        [$evidence, $normalization] = $this->normalize(EvidenceKind::GovernorBuildings, "Buildings\nBuilding: Academy Level 1");
        $reviewId = $this->review($evidence, $normalization, ['states' => [['subject_id' => 'academy', 'level' => 1]]]);
        $normalize = app(NormalizeGovernorProgressionEvidence::class);
        $normalize->handle((string) $evidence->id, (string) $normalization->extraction_attempt_id);
        self::assertSame(EvidenceLifecycleStatus::Approved, $evidence->fresh()->lifecycle_status);

        $receipt = app(CommitReviewedGovernorProgressionEvidence::class)->handle($this->actorId, $this->allianceId, $this->entryId, $reviewId);
        $normalize->handle((string) $evidence->id, (string) $normalization->extraction_attempt_id);
        self::assertSame(EvidenceLifecycleStatus::Committed, $evidence->fresh()->lifecycle_status);
        self::assertSame(1, ProgressionNormalizationAttempt::query()->count());
        self::assertSame(1, GovernorProgressionObservation::query()->count());
        self::assertTrue(GovernorProgressionEvidenceReceipt::query()->whereKey($receipt->receiptId)->exists());
    }

    public function test_deleted_evidence_cannot_be_revived_by_normalization_or_stale_review(): void
    {
        [$evidence, $normalization] = $this->normalize(EvidenceKind::GovernorBuildings, "Buildings\nBuilding: Academy Level 1");
        app(DeleteGovernorProgressionEvidence::class)->handle($this->actorId, $this->allianceId, $this->entryId, (string) $evidence->id);
        app(NormalizeGovernorProgressionEvidence::class)->handle((string) $evidence->id, (string) $normalization->extraction_attempt_id);

        self::assertSame(EvidenceLifecycleStatus::Deleted, $evidence->fresh()->lifecycle_status);
        self::assertNull($evidence->fresh()->path);
        try {
            $this->review($evidence, $normalization, ['states' => [['subject_id' => 'academy', 'level' => 1]]]);
            self::fail('A stale review must not revive deleted evidence.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('evidence', $exception->errors());
        }
        self::assertSame(EvidenceLifecycleStatus::Deleted, $evidence->fresh()->lifecycle_status);
        self::assertSame(1, ProgressionNormalizationAttempt::query()->count());
        self::assertSame(0, GovernorProgressionEvidenceReview::query()->count());
        self::assertSame(0, GovernorProgressionObservation::query()->count());
    }

    public function test_review_cannot_reopen_committed_evidence_and_deletion_preserves_owner_history(): void
    {
        [$evidence, $normalization] = $this->normalize(EvidenceKind::GovernorBuildings, "Buildings\nBuilding: Academy Level 1");
        $payload = ['states' => [['subject_id' => 'academy', 'level' => 1]]];
        $reviewId = $this->review($evidence, $normalization, $payload);
        $receipt = app(CommitReviewedGovernorProgressionEvidence::class)->handle($this->actorId, $this->allianceId, $this->entryId, $reviewId);
        try {
            $this->review($evidence, $normalization, $payload);
            self::fail('Corrections after commit belong to the owner observation workflow.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('evidence', $exception->errors());
        }
        app(DeleteGovernorProgressionEvidence::class)->handle($this->actorId, $this->allianceId, $this->entryId, (string) $evidence->id);
        app(NormalizeGovernorProgressionEvidence::class)->handle((string) $evidence->id, (string) $normalization->extraction_attempt_id);
        self::assertSame(EvidenceLifecycleStatus::Deleted, $evidence->fresh()->lifecycle_status);
        self::assertTrue(GovernorProgressionObservation::query()->whereKey($receipt->observationId)->exists());
        self::assertTrue(GovernorProgressionEvidenceReceipt::query()->whereKey($receipt->receiptId)->exists());
        self::assertSame(1, GovernorProgressionEvidenceReview::query()->count());
    }

    public function test_deletion_is_blocked_between_extraction_and_normalization(): void
    {
        $evidence = $this->upload(EvidenceKind::GovernorBuildings, "Buildings\nBuilding: Academy Level 1");
        app(ClassifyGameEvidence::class)->handle((string) $evidence->id);
        $classification = EvidenceClassificationAttempt::query()->where('evidence_id', $evidence->id)->sole();
        app(ExtractGameEvidence::class)->handle((string) $evidence->id, (string) $classification->id);
        self::assertSame(EvidenceLifecycleStatus::Extracting, $evidence->fresh()->lifecycle_status);
        try {
            app(DeleteGovernorProgressionEvidence::class)->handle($this->actorId, $this->allianceId, $this->entryId, (string) $evidence->id);
            self::fail('The normalization handoff is still active processing.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('evidence', $exception->errors());
        }
        self::assertNotNull($evidence->fresh()->path);
        self::assertSame(EvidenceLifecycleStatus::Extracting, $evidence->fresh()->lifecycle_status);
    }

    /** @return iterable<string,array{EvidenceKind,string,string,string,string}> */
    public static function structuredKinds(): iterable
    {
        yield 'buildings' => [EvidenceKind::GovernorBuildings, 'Buildings', 'Building', 'Academy', 'academy'];
        yield 'academy' => [EvidenceKind::GovernorAcademyResearch, 'Academy Research', 'Technology', 'Tool Enhancement I', 'development-tool-enhancement-i'];
        yield 'war academy' => [EvidenceKind::GovernorWarAcademyResearch, 'War Academy Research', 'Technology', 'Truegold Battalion', 'truegold-battalion'];
    }

    /** @return array{GameEvidence,ProgressionNormalizationAttempt} */
    private function normalize(EvidenceKind $kind, string $text): array
    {
        $evidence = $this->upload($kind, $text);
        app(ClassifyGameEvidence::class)->handle((string) $evidence->id);
        self::assertSame($kind, $evidence->fresh()->kind);
        self::assertSame(EvidenceLifecycleStatus::Classified, $evidence->fresh()->lifecycle_status);
        $classification = EvidenceClassificationAttempt::query()->where('evidence_id', $evidence->id)->sole();
        app(ExtractGameEvidence::class)->handle((string) $evidence->id, (string) $classification->id);
        $extraction = EvidenceExtractionAttempt::query()->where('evidence_id', $evidence->id)->sole();
        app(NormalizeGovernorProgressionEvidence::class)->handle((string) $evidence->id, (string) $extraction->id);
        $normalization = ProgressionNormalizationAttempt::query()->where('evidence_id', $evidence->id)->sole();
        self::assertSame(EvidenceAttemptStatus::Completed, $normalization->status);
        self::assertSame(EvidenceLifecycleStatus::NeedsReview, $evidence->fresh()->lifecycle_status);

        return [$evidence, $normalization];
    }

    private function upload(EvidenceKind $kind, string $text): GameEvidence
    {
        // Only the external OCR result is substituted. All application actions,
        // review provenance, pinned catalogue lookup and destination writes are real.
        $tokens = [];
        foreach (explode("\n", $text) as $line => $value) {
            $tokens[] = new OcrToken($value, 0.99, 1, 1, 1, $line + 1, 1, 20, 20 + $line * 30, 600, 24);
        }
        $document = new OcrDocument('synthetic-pipeline-fixture', '1', 'eng', $tokens);
        app()->instance(OcrEngine::class, new class($document) implements OcrEngine
        {
            public function __construct(private OcrDocument $document) {}

            public function recognize(GameEvidence $evidence): OcrDocument
            {
                return $this->document;
            }
        });
        $result = app(UploadGovernorProgressionEvidence::class)->handle(
            $this->actorId,
            $this->allianceId,
            $this->entryId,
            $kind,
            UploadedFile::fake()->image('structured-progression.png', 1080, 1920),
        );
        self::assertFalse($result->duplicate);
        Bus::assertDispatched(ClassifyGameEvidenceJob::class);
        $evidence = GameEvidence::query()->findOrFail($result->evidenceId);
        Storage::disk('local')->assertExists((string) $evidence->path);
        self::assertSame(EvidenceLifecycleStatus::Uploaded, $evidence->lifecycle_status);

        return $evidence;
    }

    /** @param array<string,mixed> $payload */
    private function review(GameEvidence $evidence, ProgressionNormalizationAttempt $normalization, array $payload): string
    {
        return app(SaveGovernorProgressionEvidenceReview::class)->handle(
            $this->actorId,
            $this->allianceId,
            $this->entryId,
            (string) $evidence->id,
            (string) $normalization->id,
            '2026-08-26T12:00:00Z',
            $payload,
        );
    }
}
