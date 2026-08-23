<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Queries\BearHuntUnmatchedGovernorQuery;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

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
