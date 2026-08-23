<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReviewRow;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

final class ScreenshotIntakeVisualFixture
{
    public static function seed(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-23 02:00:00', 'UTC'));

        $user = User::factory()->create([
            'name' => 'Screenshot Visual',
            'email' => 'screenshot-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $kingdom = Kingdom::query()->create(['number' => 1323, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'GOV-SCREENSHOT-A',
            'current_name' => 'Report Warden',
        ]);
        $allianceId = app(CreateAlliance::class)->handle(
            (string) $player->id,
            'Ember Watch',
            'ember-watch',
            'en',
            'UTC',
        );
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $event = app(CreateEvent::class)->handle(
            actorPlayerId: (string) $player->id,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
            firstLocalStart: CarbonImmutable::parse('2026-08-23 13:00:00', 'UTC'),
            title: 'Bear Hunt · Visual Review',
            durationMinutes: 30,
        );
        if ($event->firstOccurrenceId === null) {
            return;
        }

        $binary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/l3MB9QAAAABJRU5ErkJggg==', true);
        if (! is_string($binary)) {
            return;
        }
        $path = 'evidence/visual/bear-hunt-report.png';
        Storage::disk('local')->put($path, $binary);
        $sha256 = hash('sha256', $binary);
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => $event->firstOccurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => EvidenceLifecycleStatus::Approved,
            'original_name' => 'raging-bear-battle-record.png',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => strlen($binary),
            'width' => 1080,
            'height' => 1920,
            'sha256' => $sha256,
            'perceptual_hash' => '0f0f0f0f0f0f0f0f',
            'uploaded_by_player_id' => $player->id,
            'scanned_at' => now(),
        ]);
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'bear-hunt-visual-fixture',
            'classifier_version' => '1',
            'input_sha256' => $sha256,
            'ocr_engine' => 'fixture',
            'ocr_version' => '1',
            'ocr_language' => 'eng',
            'classified_kind' => EvidenceKind::BearHuntBattleReport,
            'confidence' => 0.96,
            'reason' => 'Fixture contains the Bear Hunt battle record and ranking markers.',
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
            'overall_confidence' => 0.89,
            'field_count' => 5,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $name = EvidenceExtractedField::query()->create([
            'extraction_attempt_id' => $extraction->id,
            'field_key' => 'player_name',
            'row_ordinal' => 1,
            'raw_text' => 'Report Warden',
            'normalized_value' => 'Report Warden',
            'data_type' => 'string',
            'confidence' => 0.94,
            'bounding_box' => ['left' => 120, 'top' => 760, 'width' => 360, 'height' => 54],
        ]);
        $damage = EvidenceExtractedField::query()->create([
            'extraction_attempt_id' => $extraction->id,
            'field_key' => 'damage',
            'row_ordinal' => 1,
            'raw_text' => '1,156,200',
            'normalized_value' => '1156200',
            'data_type' => 'integer',
            'confidence' => 0.83,
            'bounding_box' => ['left' => 540, 'top' => 760, 'width' => 270, 'height' => 54],
        ]);
        EvidenceExtractedField::query()->create([
            'extraction_attempt_id' => $extraction->id,
            'field_key' => 'report_timestamp',
            'row_ordinal' => 0,
            'raw_text' => '2026-08-22 13:05:23',
            'normalized_value' => '2026-08-22 13:05:23',
            'data_type' => 'datetime_text',
            'confidence' => 0.91,
        ]);
        $review = EvidenceReview::query()->create([
            'evidence_id' => $evidence->id,
            'extraction_attempt_id' => $extraction->id,
            'alliance_id' => $allianceId,
            'occurrence_id' => $event->firstOccurrenceId,
            'revision_number' => 1,
            'status' => EvidenceReviewStatus::Approved,
            'report_timestamp_text' => '2026-08-22 13:05:23',
            'semantic_fingerprint' => hash('sha256', 'screenshot-visual-review'),
            'reviewed_by_player_id' => $player->id,
            'reviewed_at' => now(),
        ]);
        EvidenceReviewRow::query()->create([
            'review_id' => $review->id,
            'row_ordinal' => 1,
            'source_name_field_id' => $name->id,
            'source_damage_field_id' => $damage->id,
            'player_id' => $player->id,
            'player_name' => 'Report Warden',
            'reported_rank' => 1,
            'damage_points' => 1156260,
            'included' => true,
            'rank_corrected' => true,
            'name_corrected' => false,
            'damage_corrected' => true,
            'correction_reason' => 'Reviewer corrected the final OCR digit from the visible report.',
        ]);
    }
}
