<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Contributions;

use App\Contexts\Intelligence\Contributions\Actions\ApproveContributionRecord;
use App\Contexts\Intelligence\Contributions\Actions\BulkApproveContributionRecords;
use App\Contexts\Intelligence\Contributions\Actions\CreateContributionCategory;
use App\Contexts\Intelligence\Contributions\Actions\PreviewContributionBulkApproval;
use App\Contexts\Intelligence\Contributions\Actions\RecordContribution;
use App\Contexts\Intelligence\Contributions\Actions\ReverseContributionRecord;
use App\Contexts\Intelligence\Contributions\Enums\ContributionDataClass;
use App\Contexts\Intelligence\Contributions\Enums\ContributionPeriod;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordSource;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordStatus;
use App\Contexts\Intelligence\Contributions\Models\ContributionCategory;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class ContributionBulkApprovalBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_approval_previews_state_and_reports_every_record(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 60001);
        $alliance = $scenario->alliance($actor);
        app(CreateContributionCategory::class)->handle(
            $actor->playerId,
            $alliance->allianceId,
            'Bulk Approval',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
        );
        $category = ContributionCategory::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('slug', 'bulk-approval')
            ->firstOrFail();
        $ready = $this->record($actor->playerId, $alliance->allianceId, (string) $category->id, 101.0);
        $approved = $this->record($actor->playerId, $alliance->allianceId, (string) $category->id, 102.0);
        $reversed = $this->record($actor->playerId, $alliance->allianceId, (string) $category->id, 103.0);
        app(ApproveContributionRecord::class)->handle($actor->playerId, $alliance->allianceId, (string) $approved->id);
        app(ReverseContributionRecord::class)->handle($actor->playerId, $alliance->allianceId, (string) $reversed->id, 'Duplicate record.');
        $recordIds = [(string) $ready->id, (string) $approved->id, (string) $reversed->id];

        $preview = app(PreviewContributionBulkApproval::class)->handle(
            $actor->playerId,
            $alliance->allianceId,
            $recordIds,
        );

        self::assertSame(1, $preview['ready']);
        self::assertSame(2, $preview['blocked']);
        self::assertSame([(string) $ready->id], $preview['readyItemIds']);
        self::assertSame(
            ['ready', 'already-approved', 'record-reversed'],
            array_column($preview['items'], 'code'),
        );

        $result = app(BulkApproveContributionRecords::class)->handle(
            $actor->playerId,
            $alliance->allianceId,
            $recordIds,
        )->toArray();

        self::assertSame(1, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertSame(1, $result['skipped']);
        self::assertSame([(string) $reversed->id], $result['failedItemIds']);
        self::assertSame(ContributionRecordStatus::Approved, $ready->refresh()->status);
        self::assertTrue(AuditEvent::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('event', 'contribution.records.bulk_approved')
            ->exists());
    }

    private function record(
        string $actorPlayerId,
        string $allianceId,
        string $categoryId,
        float $value,
    ): ContributionRecord {
        app(RecordContribution::class)->handle(
            $actorPlayerId,
            $allianceId,
            $actorPlayerId,
            $categoryId,
            $value,
            ContributionRecordSource::Manual,
        );

        return ContributionRecord::query()
            ->where('alliance_id', $allianceId)
            ->where('category_id', $categoryId)
            ->where('value', $value)
            ->firstOrFail();
    }
}
