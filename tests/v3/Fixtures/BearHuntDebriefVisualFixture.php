<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BearHuntDebriefVisualFixture
{
    public static function seed(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-23 03:00:00', 'UTC'));

        $user = User::factory()->create([
            'name' => 'Bear Hunt Debrief Visual',
            'email' => 'bear-debrief-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $kingdom = Kingdom::query()->create(['number' => 1423, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'GOV-DEBRIEF-A',
            'current_name' => 'Bear Marshal',
        ]);
        $second = Player::query()->create([
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'GOV-DEBRIEF-B',
            'current_name' => 'Ember Scout',
        ]);
        $third = Player::query()->create([
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'GOV-DEBRIEF-C',
            'current_name' => 'Frost Guard of the Northern Aurora Vanguard Expedition',
        ]);

        $allianceId = app(CreateAlliance::class)->handle(
            (string) $actor->id,
            'Aurora Vanguard',
            'aurora-vanguard',
            'en',
            'UTC',
        );
        foreach ([$second, $third] as $member) {
            AllianceMembership::query()->create([
                'alliance_id' => $allianceId,
                'player_id' => $member->id,
                'status' => MembershipStatus::Active,
                'rank' => AllianceRank::R3,
                'joined_at' => now(),
            ]);
        }
        foreach ([$actor, $second, $third] as $member) {
            app(UpsertRosterEntry::class)->handle(
                actorPlayerId: (string) $actor->id,
                allianceId: $allianceId,
                attributes: [
                    'name' => (string) $member->current_name,
                    'game_player_id' => (string) $member->game_player_id,
                    'state' => RosterState::Active,
                ],
                expectedPlayerId: (string) $member->id,
            );
        }

        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();

        $previous = app(CreateEvent::class)->handle(
            actorPlayerId: (string) $actor->id,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
            firstLocalStart: CarbonImmutable::parse('2026-08-16 13:00:00', 'UTC'),
            title: 'Bear Hunt · Previous Debrief',
            durationMinutes: 30,
        );
        $current = app(CreateEvent::class)->handle(
            actorPlayerId: (string) $actor->id,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
            firstLocalStart: CarbonImmutable::parse('2026-08-23 13:00:00', 'UTC'),
            title: 'Bear Hunt · Debrief Visual',
            durationMinutes: 30,
        );
        if ($previous->firstOccurrenceId === null || $current->firstOccurrenceId === null) {
            return;
        }

        EventOccurrence::query()->whereKey($previous->firstOccurrenceId)->update([
            'status' => EventOccurrenceStatus::Completed->value,
        ]);
        EventOccurrence::query()->whereKey($current->firstOccurrenceId)->update([
            'status' => EventOccurrenceStatus::Completed->value,
        ]);
        EventOccurrence::query()->create([
            'event_id' => $current->eventId,
            'starts_at' => CarbonImmutable::now('UTC')->addDays(2)->startOfHour(),
            'ends_at' => CarbonImmutable::now('UTC')->addDays(2)->startOfHour()->addMinutes(30),
            'status' => EventOccurrenceStatus::Scheduled,
            'settings' => [],
        ]);

        app(RecordBearHuntBattleReport::class)->handle(
            actorPlayerId: (string) $actor->id,
            occurrenceId: $previous->firstOccurrenceId,
            sourceEvidenceId: (string) Str::ulid(),
            sourceCommitAttemptId: (string) Str::ulid(),
            idempotencyKey: hash('sha256', 'bear-debrief-visual-previous'),
            reportFingerprint: hash('sha256', 'bear-debrief-visual-previous-report'),
            reportTimestampText: '2026-08-16 13:29:00',
            entries: [
                [
                    'player_id' => (string) $actor->id,
                    'reported_rank' => 2,
                    'damage_points' => 2100000,
                ],
                [
                    'player_id' => (string) $second->id,
                    'reported_rank' => 1,
                    'damage_points' => 2500000,
                ],
                [
                    'player_id' => (string) $third->id,
                    'reported_rank' => 3,
                    'damage_points' => 700000,
                ],
            ],
        );

        app(RecordBearHuntBattleReport::class)->handle(
            actorPlayerId: (string) $actor->id,
            occurrenceId: $current->firstOccurrenceId,
            sourceEvidenceId: (string) Str::ulid(),
            sourceCommitAttemptId: (string) Str::ulid(),
            idempotencyKey: hash('sha256', 'bear-debrief-visual-current'),
            reportFingerprint: hash('sha256', 'bear-debrief-visual-current-report'),
            reportTimestampText: '2026-08-23 13:29:00',
            entries: [
                [
                    'player_id' => (string) $actor->id,
                    'reported_rank' => 1,
                    'damage_points' => 3200000,
                ],
                [
                    'player_id' => (string) $second->id,
                    'reported_rank' => 2,
                    'damage_points' => 2800000,
                ],
                [
                    'player_id' => (string) $third->id,
                    'reported_rank' => 3,
                    'damage_points' => 900000,
                ],
            ],
        );

        self::attendance($current->firstOccurrenceId, (string) $actor->id, EventAttendanceStatus::Present, (string) $actor->id);
        self::attendance($current->firstOccurrenceId, (string) $second->id, EventAttendanceStatus::Present, (string) $actor->id);
        self::attendance($current->firstOccurrenceId, (string) $third->id, EventAttendanceStatus::Absent, (string) $actor->id);
        self::attendance($previous->firstOccurrenceId, (string) $actor->id, EventAttendanceStatus::Present, (string) $actor->id);
        self::attendance($previous->firstOccurrenceId, (string) $second->id, EventAttendanceStatus::Present, (string) $actor->id);
        self::attendance($previous->firstOccurrenceId, (string) $third->id, EventAttendanceStatus::Present, (string) $actor->id);

        $rally = RallyGroup::query()->create([
            'occurrence_id' => $current->firstOccurrenceId,
            'alliance_id' => $allianceId,
            'name' => 'Bear Trap Alpha',
            'sort_order' => 0,
            'created_by_player_id' => $actor->id,
        ]);
        self::rallyAssignment($rally, (string) $actor->id, RallyAssignmentRole::Lead, RallyAssignmentStatus::Participated, (string) $actor->id);
        self::rallyAssignment($rally, (string) $second->id, RallyAssignmentRole::Joiner, RallyAssignmentStatus::Participated, (string) $actor->id);
        self::rallyAssignment($rally, (string) $third->id, RallyAssignmentRole::Joiner, RallyAssignmentStatus::Absent, (string) $actor->id);

        self::unmatchedEvidence($allianceId, $current->firstOccurrenceId, (string) $actor->id);
    }

    private static function attendance(
        string $occurrenceId,
        string $playerId,
        EventAttendanceStatus $status,
        string $actorPlayerId,
    ): void {
        EventAttendance::query()->create([
            'occurrence_id' => $occurrenceId,
            'player_id' => $playerId,
            'status' => $status,
            'recorded_by_player_id' => $actorPlayerId,
            'recorded_at' => now(),
        ]);
    }

    private static function rallyAssignment(
        RallyGroup $group,
        string $playerId,
        RallyAssignmentRole $role,
        RallyAssignmentStatus $status,
        string $actorPlayerId,
    ): void {
        RallyAssignment::query()->create([
            'rally_group_id' => $group->id,
            'player_id' => $playerId,
            'role' => $role,
            'status' => $status,
            'assigned_by_player_id' => $actorPlayerId,
            'assigned_at' => now(),
            'recorded_by_player_id' => $actorPlayerId,
            'recorded_at' => now(),
        ]);
    }

    private static function unmatchedEvidence(string $allianceId, string $occurrenceId, string $actorPlayerId): void
    {
        $binary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/l3MB9QAAAABJRU5ErkJggg==', true);
        if (! is_string($binary)) {
            return;
        }
        $path = 'evidence/visual/bear-hunt-debrief-unmatched.png';
        Storage::disk('local')->put($path, $binary);
        $sha256 = hash('sha256', $binary);
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => EvidenceLifecycleStatus::NeedsReview,
            'original_name' => 'bear-hunt-unmatched.png',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => strlen($binary),
            'width' => 1080,
            'height' => 1920,
            'sha256' => $sha256,
            'perceptual_hash' => 'f0f0f0f0f0f0f0f0',
            'uploaded_by_player_id' => $actorPlayerId,
            'scanned_at' => now(),
        ]);
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'bear-hunt-debrief-visual',
            'classifier_version' => '1',
            'input_sha256' => $sha256,
            'ocr_engine' => 'fixture',
            'ocr_version' => '1',
            'ocr_language' => 'eng',
            'classified_kind' => EvidenceKind::BearHuntBattleReport,
            'confidence' => 0.88,
            'reason' => 'Fixture contains one unmatched Governor row.',
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
            'overall_confidence' => 0.71,
            'field_count' => 3,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        foreach ([
            ['player_name', 'Unknown Ember', 'string', 0.62],
            ['rank', '4', 'integer', 0.77],
            ['damage', '450000', 'integer', 0.74],
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
    }
}
