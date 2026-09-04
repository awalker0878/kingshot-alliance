<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Intelligence\Evidence\Actions\CommitReviewedAllianceRosterEvidence;
use App\Contexts\Intelligence\Evidence\Actions\SaveAllianceRosterEvidenceReview;
use App\Contexts\Intelligence\Evidence\Actions\UploadAllianceRosterEvidence;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\AllianceRosterEvidence;
use App\Contexts\Intelligence\Roster\Models\AllianceRosterObservation;
use App\Contexts\Intelligence\Roster\Models\AllianceRosterObservationBatch;
use App\ReadModels\AllianceGovernance\Queries\AllianceRosterReconciliationQuery;
use App\Shared\Infrastructure\Uploads\Services\UploadScanner;
use App\Shared\Infrastructure\Uploads\ValueObjects\UploadScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceRosterEvidenceBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_reviewed_roster_evidence_commits_exactly_once_without_mutating_membership(): void
    {
        Storage::fake('local');
        config()->set('evidence.disk', 'local');
        $this->bindScanner();

        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59220);
        $alliance = $scenario->alliance($owner);
        $roster = $scenario->roster($owner, $alliance);
        $membershipBefore = AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $owner->playerId)
            ->firstOrFail();

        $upload = app(UploadAllianceRosterEvidence::class);
        $binary = $this->pngBinary();
        $first = $upload->handle(
            $owner->playerId,
            $alliance->allianceId,
            UploadedFile::fake()->createWithContent('roster.png', $binary),
        );
        $duplicate = $upload->handle(
            $owner->playerId,
            $alliance->allianceId,
            UploadedFile::fake()->createWithContent('roster-copy.png', $binary),
        );

        self::assertFalse($first->duplicate);
        self::assertTrue($duplicate->duplicate);
        self::assertSame($first->evidenceId, $duplicate->evidenceId);
        self::assertSame(1, AllianceRosterEvidence::query()->where('alliance_id', $alliance->allianceId)->count());

        $reviewId = app(SaveAllianceRosterEvidenceReview::class)->handle(
            actorPlayerId: $owner->playerId,
            allianceId: $alliance->allianceId,
            evidenceId: $first->evidenceId,
            capturedAt: now()->subMinute()->toIso8601String(),
            rows: [
                [
                    'observed_name' => $owner->currentName,
                    'game_player_id' => $owner->gamePlayerId,
                    'observed_rank' => 'r5',
                    'power' => 1000,
                    'roster_entry_id' => $roster->rosterEntryId,
                ],
                [
                    'observed_name' => 'Observed Recruit',
                    'game_player_id' => 'unclaimed-59220',
                    'observed_rank' => 'r1',
                    'power' => 500,
                ],
            ],
            completeRoster: true,
        );

        $evidence = AllianceRosterEvidence::query()->findOrFail($first->evidenceId);
        self::assertSame(EvidenceLifecycleStatus::Approved, $evidence->lifecycle_status);

        $commit = app(CommitReviewedAllianceRosterEvidence::class);
        $firstReceipt = $commit->handle($owner->playerId, $alliance->allianceId, $reviewId);
        $secondReceipt = $commit->handle($owner->playerId, $alliance->allianceId, $reviewId);

        self::assertSame($firstReceipt->batchId, $secondReceipt->batchId);
        self::assertSame(2, $firstReceipt->rowCount);
        self::assertSame(1, AllianceRosterObservationBatch::query()->count());
        self::assertSame(2, AllianceRosterObservation::query()->count());

        $membershipAfter = AllianceMembership::query()->findOrFail($membershipBefore->id);
        self::assertSame($membershipBefore->rank, $membershipAfter->rank);
        self::assertSame($membershipBefore->status, $membershipAfter->status);
        self::assertSame(1, AllianceMembership::query()->where('alliance_id', $alliance->allianceId)->count());

        $reconciliation = app(AllianceRosterReconciliationQuery::class)->forAlliance($alliance->allianceId);
        self::assertSame(1, $reconciliation['summary']['matched']);
        self::assertSame(1, $reconciliation['summary']['needsReview']);
        $observedRecruit = collect($reconciliation['items'])
            ->firstWhere('gamePlayerId', 'unclaimed-59220');
        self::assertIsArray($observedRecruit);
        self::assertContains('observation_without_membership', $observedRecruit['reasons']);
        self::assertContains('observed_new', $observedRecruit['reasons']);
        self::assertSame(EvidenceLifecycleStatus::Committed, AllianceRosterEvidence::query()->findOrFail($first->evidenceId)->lifecycle_status);
    }

    private function bindScanner(): void
    {
        app()->instance(UploadScanner::class, new class implements UploadScanner
        {
            public function scan(UploadedFile $file): UploadScanResult
            {
                return new UploadScanResult(true, null);
            }
        });
    }

    private function pngBinary(): string
    {
        $binary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/l3MB9QAAAABJRU5ErkJggg==',
            true,
        );
        self::assertIsString($binary);

        return $binary;
    }
}
