<?php

declare(strict_types=1);

namespace Tests\Contexts\Intelligence\Evidence\Feature;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\SaveEvidenceReview;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Queries\BearHuntUnmatchedGovernorQuery;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class BearHuntUnmatchedGovernorQueryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_only_needs_review_evidence_for_the_authorized_alliance_occurrence_is_returned(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61401);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));

        $needsReview = $this->evidence(
            $alliance->allianceId,
            (string) $occurrence->id,
            $actor->playerId,
            EvidenceLifecycleStatus::NeedsReview,
            'Unknown Ember',
            'needs-review',
        );
        $this->evidence(
            $alliance->allianceId,
            (string) $occurrence->id,
            $actor->playerId,
            EvidenceLifecycleStatus::Approved,
            'Already Approved',
            'approved',
        );
        $this->evidence(
            $alliance->allianceId,
            (string) $occurrence->id,
            $actor->playerId,
            EvidenceLifecycleStatus::Committed,
            'Already Committed',
            'committed',
        );

        $otherAccount = $scenario->authUser();
        $otherOwner = $scenario->player((int) $otherAccount->id, 61402);
        $otherAlliance = $scenario->alliance($otherOwner);
        $scenario->roster($otherOwner, $otherAlliance);
        $otherOccurrence = $this->occurrence($otherOwner, $otherAlliance, CarbonImmutable::now('UTC'));
        $this->evidence(
            $otherAlliance->allianceId,
            (string) $otherOccurrence->id,
            $otherOwner->playerId,
            EvidenceLifecycleStatus::NeedsReview,
            'Other Alliance Governor',
            'other-alliance',
        );

        $queue = app(BearHuntUnmatchedGovernorQuery::class)->forOccurrence(
            $actor->playerId,
            (string) $occurrence->id,
        );

        self::assertCount(1, $queue);
        self::assertSame((string) $needsReview->id, $queue[0]['evidenceId']);
        self::assertSame('Unknown Ember', $queue[0]['rows'][0]['observedName']);
        self::assertSame(4, $queue[0]['rows'][0]['reportedRank']);
        self::assertSame(450000, $queue[0]['rows'][0]['damage']);
        self::assertStringContainsString('/events/'.(string) $occurrence->id.'/screenshot-intake', $queue[0]['reviewHref']);

        $needsReview->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Approved])->save();
        self::assertSame([], app(BearHuntUnmatchedGovernorQuery::class)->forOccurrence(
            $actor->playerId,
            (string) $occurrence->id,
        ));
    }

    public function test_duplicate_blocked_review_is_not_mislabeled_as_unmatched_governor_work(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61405);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $first = $this->evidence(
            $alliance->allianceId,
            (string) $occurrence->id,
            $actor->playerId,
            EvidenceLifecycleStatus::NeedsReview,
            $actor->currentName,
            'semantic-first',
        );
        $second = $this->evidence(
            $alliance->allianceId,
            (string) $occurrence->id,
            $actor->playerId,
            EvidenceLifecycleStatus::NeedsReview,
            $actor->currentName,
            'semantic-second',
        );
        $firstExtraction = EvidenceExtractionAttempt::query()
            ->where('evidence_id', $first->id)
            ->firstOrFail();
        $secondExtraction = EvidenceExtractionAttempt::query()
            ->where('evidence_id', $second->id)
            ->firstOrFail();
        $reviewRows = [[
            'row_ordinal' => 1,
            'included' => true,
            'player_id' => $actor->playerId,
            'player_name' => $actor->currentName,
            'reported_rank' => 4,
            'damage_points' => 450000,
            'correction_reason' => null,
        ]];
        $reviews = app(SaveEvidenceReview::class);
        $reviews->handle(
            $actor->playerId,
            (string) $occurrence->id,
            (string) $first->id,
            (string) $firstExtraction->id,
            $reviewRows,
        );
        $secondReviewId = $reviews->handle(
            $actor->playerId,
            (string) $occurrence->id,
            (string) $second->id,
            (string) $secondExtraction->id,
            $reviewRows,
        );

        $second->refresh();
        $secondReview = EvidenceReview::query()->findOrFail($secondReviewId);
        self::assertSame(EvidenceLifecycleStatus::NeedsReview, $second->lifecycle_status);
        self::assertSame(EvidenceReviewStatus::DuplicateBlocked, $secondReview->status);
        self::assertSame([], app(BearHuntUnmatchedGovernorQuery::class)->forOccurrence(
            $actor->playerId,
            (string) $occurrence->id,
        ));
    }

    public function test_reviewed_duplicate_follow_up_cannot_starve_older_unmatched_evidence_from_the_bounded_queue(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61406);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $unmatched = $this->evidence(
            $alliance->allianceId,
            (string) $occurrence->id,
            $actor->playerId,
            EvidenceLifecycleStatus::NeedsReview,
            'Older Unmatched Governor',
            'older-unmatched',
        );

        $reviews = app(SaveEvidenceReview::class);
        $reviewRows = [[
            'row_ordinal' => 1,
            'included' => true,
            'player_id' => $actor->playerId,
            'player_name' => $actor->currentName,
            'reported_rank' => 4,
            'damage_points' => 450000,
            'correction_reason' => null,
        ]];

        // The first review becomes approved; the next fifty share its reviewed
        // meaning and remain needs_review only for semantic-duplicate resolution.
        // Without filtering reviewed latest extractions before LIMIT 50, those
        // fifty newer items hide the genuinely unmatched Evidence above.
        for ($index = 0; $index <= 50; $index++) {
            $reviewed = $this->evidence(
                $alliance->allianceId,
                (string) $occurrence->id,
                $actor->playerId,
                EvidenceLifecycleStatus::NeedsReview,
                $actor->currentName,
                'reviewed-follow-up-'.$index,
            );
            $extraction = EvidenceExtractionAttempt::query()
                ->where('evidence_id', $reviewed->id)
                ->firstOrFail();
            $reviews->handle(
                $actor->playerId,
                (string) $occurrence->id,
                (string) $reviewed->id,
                (string) $extraction->id,
                $reviewRows,
            );
        }

        self::assertSame(50, GameEvidence::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('lifecycle_status', EvidenceLifecycleStatus::NeedsReview->value)
            ->whereKeyNot($unmatched->id)
            ->count());

        $queue = app(BearHuntUnmatchedGovernorQuery::class)->forOccurrence(
            $actor->playerId,
            (string) $occurrence->id,
        );

        self::assertCount(1, $queue);
        self::assertSame((string) $unmatched->id, $queue[0]['evidenceId']);
        self::assertSame('Older Unmatched Governor', $queue[0]['rows'][0]['observedName']);
    }

    public function test_manager_authority_is_reacquired_before_evidence_is_exposed(): void
    {
        $scenario = new ScenarioFactory;
        $ownerAccount = $scenario->authUser();
        $owner = $scenario->player((int) $ownerAccount->id, 61403);
        $alliance = $scenario->alliance($owner);
        $scenario->roster($owner, $alliance);
        $occurrence = $this->occurrence($owner, $alliance, CarbonImmutable::now('UTC'));
        $this->evidence(
            $alliance->allianceId,
            (string) $occurrence->id,
            $owner->playerId,
            EvidenceLifecycleStatus::NeedsReview,
            'Protected Governor',
            'protected',
        );

        $outsiderAccount = $scenario->authUser();
        $outsider = $scenario->player((int) $outsiderAccount->id, 61404);

        $this->expectException(AuthorizationException::class);
        app(BearHuntUnmatchedGovernorQuery::class)->forOccurrence(
            $outsider->playerId,
            (string) $occurrence->id,
        );
    }

    public function test_retained_attempts_and_thousands_of_fields_produce_one_bounded_exact_preview(): void
    {
        $scenario = app(ScenarioFactory::class);
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 62737);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $evidence = $this->evidence($alliance->allianceId, (string) $occurrence->id, $actor->playerId,
            EvidenceLifecycleStatus::NeedsReview, 'Latest', 'bounded-preview');
        $latest = EvidenceExtractionAttempt::query()->where('evidence_id', $evidence->id)->firstOrFail();
        $attempts = [];
        for ($i = 0; $i < 2001; $i++) {
            $attempts[] = ['id' => strtolower((string) Str::ulid()), 'evidence_id' => $evidence->id,
                'classification_attempt_id' => $latest->classification_attempt_id, 'status' => 'completed',
                'extractor_key' => 'fixture', 'extractor_version' => '1', 'schema_version' => '1',
                'input_sha256' => $evidence->sha256, 'started_at' => now()->subDay(), 'created_at' => now()->subDay()];
        }
        foreach (array_chunk($attempts, 500) as $rows) {
            DB::table('evidence_extraction_attempts')->insert($rows);
        }
        $fields = [];
        for ($ordinal = 2; $ordinal <= 1001; $ordinal++) {
            foreach (['player_name' => 'Governor '.$ordinal, 'rank' => (string) $ordinal, 'damage' => '1200', 'extra' => 'unused'] as $key => $value) {
                $fields[] = ['id' => strtolower((string) Str::ulid()), 'extraction_attempt_id' => $latest->id,
                    'field_key' => $key, 'row_ordinal' => $ordinal, 'raw_text' => str_repeat('x', 1024),
                    'normalized_value' => $value, 'data_type' => 'string', 'confidence' => 0.8];
            }
        }
        foreach (array_chunk($fields, 500) as $rows) {
            DB::table('evidence_extracted_fields')->insert($rows);
        }
        EvidenceExtractedField::query()->where('extraction_attempt_id', $latest->id)->where('row_ordinal', 1)
            ->where('field_key', 'player_name')->update(['normalized_value' => str_repeat('界', 10000)]);
        EvidenceExtractedField::query()->where('extraction_attempt_id', $latest->id)->where('row_ordinal', 1)
            ->where('field_key', 'damage')->update(['normalized_value' => '9223372036854775808']);
        $attemptModels = $fieldModels = 0;
        EvidenceExtractionAttempt::retrieved(static function () use (&$attemptModels): void {
            $attemptModels++;
        });
        EvidenceExtractedField::retrieved(static function () use (&$fieldModels): void {
            $fieldModels++;
        });
        $queue = app(BearHuntUnmatchedGovernorQuery::class)->forOccurrence($actor->playerId, (string) $occurrence->id);
        self::assertCount(1, $queue);
        self::assertSame(1001, $queue[0]['rowCount']);
        self::assertCount(25, $queue[0]['rows']);
        self::assertSame(range(1, 25), array_column($queue[0]['rows'], 'ordinal'));
        self::assertSame(str_repeat('界', 512).'…', $queue[0]['rows'][0]['observedName']);
        self::assertNull($queue[0]['rows'][0]['damage']);
        self::assertSame(1200, $queue[0]['rows'][24]['damage']);
        self::assertSame(1, $attemptModels);
        self::assertSame(0, $fieldModels);
        self::assertStringContainsString('?evidence='.$evidence->id.'#evidence-', $queue[0]['reviewHref']);
    }

    public function test_review_handoff_selects_older_evidence_outside_recent_workspace_and_preserves_scope(): void
    {
        $scenario = app(ScenarioFactory::class);
        $account = $scenario->authUser();
        $account->forceFill(['email_verified_at' => now()])->save();
        $actor = $scenario->player((int) $account->id, 62738);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $old = $this->evidence($alliance->allianceId, (string) $occurrence->id, $actor->playerId,
            EvidenceLifecycleStatus::NeedsReview, 'Old Governor', 'old-handoff');
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        for ($i = 0; $i < 102; $i++) {
            $item = $old->replicate();
            $item->forceFill(['sha256' => hash('sha256', 'newer-'.$i), 'lifecycle_status' => EvidenceLifecycleStatus::Approved,
                'created_at' => now(), 'updated_at' => now()])->save();
        }
        $this->actingAs($account)->withSession([(string) config('game_world.active_player_session_key') => $actor->playerId]);
        $url = '/events/'.$occurrence->id.'/screenshot-intake?evidence='.$old->id;
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.selectedEvidenceId', (string) $old->id)
            ->has('workspace.evidence', 1)->where('workspace.evidence.0.id', (string) $old->id));
        $other = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC')->addDays(3));
        $this->get('/events/'.$other->id.'/screenshot-intake?evidence='.$old->id)->assertNotFound();
    }

    private function occurrence(
        PlayerReference $actor,
        AllianceReference $alliance,
        CarbonImmutable $start,
    ): EventOccurrence {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: $start,
            title: 'Unmatched Governor Fixture',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
    }

    private function evidence(
        string $allianceId,
        string $occurrenceId,
        string $uploaderPlayerId,
        EvidenceLifecycleStatus $lifecycle,
        string $observedName,
        string $suffix,
    ): GameEvidence {
        $sha256 = hash('sha256', 'bear-hunt-unmatched-'.$suffix);
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => $lifecycle,
            'original_name' => $suffix.'.png',
            'disk' => 'local',
            'path' => 'evidence/tests/'.$suffix.'.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
            'width' => 1080,
            'height' => 1920,
            'sha256' => $sha256,
            'perceptual_hash' => substr(hash('sha256', 'visual-'.$suffix), 0, 16),
            'uploaded_by_player_id' => $uploaderPlayerId,
            'scanned_at' => now(),
        ]);
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'bear-hunt-unmatched-test',
            'classifier_version' => '1',
            'input_sha256' => $sha256,
            'ocr_engine' => 'fixture',
            'ocr_version' => '1',
            'ocr_language' => 'eng',
            'classified_kind' => EvidenceKind::BearHuntBattleReport,
            'confidence' => 0.9,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $extraction = EvidenceExtractionAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'classification_attempt_id' => $classification->id,
            'status' => EvidenceAttemptStatus::Completed,
            'extractor_key' => 'bear-hunt-ranking-v1',
            'extractor_version' => '1.1.0',
            'schema_version' => 'bear-hunt-report/1',
            'input_sha256' => $sha256,
            'overall_confidence' => 0.8,
            'field_count' => 3,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        foreach ([
            ['player_name', $observedName, 'string', 0.70],
            ['rank', '4', 'integer', 0.80],
            ['damage', '450000', 'integer', 0.90],
        ] as [$fieldKey, $value, $dataType, $confidence]) {
            EvidenceExtractedField::query()->create([
                'extraction_attempt_id' => $extraction->id,
                'field_key' => $fieldKey,
                'row_ordinal' => 1,
                'raw_text' => $value,
                'normalized_value' => $value,
                'data_type' => $dataType,
                'confidence' => $confidence,
            ]);
        }

        return $evidence;
    }
}
