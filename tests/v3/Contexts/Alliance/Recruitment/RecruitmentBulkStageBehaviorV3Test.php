<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Recruitment;

use App\Contexts\Alliance\Recruitment\Actions\BulkChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\PreviewRecruitmentStageBulkChange;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RecruitmentBulkStageBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_stage_change_previews_and_reports_each_candidate_without_hiding_failures(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id);
        $alliance = $scenario->alliance($actor);
        $blocked = $this->candidate($alliance->allianceId, 'Blocked Candidate', RecruitmentStage::New);
        $ready = $this->candidate($alliance->allianceId, 'Ready Candidate', RecruitmentStage::Interview);
        $complete = $this->candidate($alliance->allianceId, 'Complete Candidate', RecruitmentStage::Accepted);
        $candidateIds = [(string) $blocked->id, (string) $ready->id, (string) $complete->id];

        $preview = app(PreviewRecruitmentStageBulkChange::class)->handle(
            $actor->playerId,
            $alliance->allianceId,
            $candidateIds,
            RecruitmentStage::Accepted,
        );

        self::assertSame(1, $preview['ready']);
        self::assertSame(2, $preview['blocked']);
        self::assertSame([(string) $ready->id], $preview['readyItemIds']);
        self::assertSame(
            ['transition-not-allowed', 'ready', 'already-in-target-stage'],
            array_column($preview['items'], 'code'),
        );

        $result = app(BulkChangeRecruitmentStage::class)->handle(
            $actor->playerId,
            $alliance->allianceId,
            $candidateIds,
            RecruitmentStage::Accepted,
            'Reviewed in bulk triage.',
        )->toArray();

        self::assertSame(1, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertSame(1, $result['skipped']);
        self::assertSame([(string) $blocked->id], $result['failedItemIds']);
        self::assertSame(RecruitmentStage::Accepted, $ready->refresh()->stage);
        self::assertSame(RecruitmentStage::New, $blocked->refresh()->stage);
        self::assertTrue(AuditEvent::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('event', 'recruitment.candidates.bulk_stage_changed')
            ->exists());
    }

    private function candidate(
        string $allianceId,
        string $name,
        RecruitmentStage $stage,
    ): RecruitmentCandidate {
        return RecruitmentCandidate::query()->create([
            'alliance_id' => $allianceId,
            'full_name' => $name,
            'email' => str_replace(' ', '.', strtolower($name)).'@example.test',
            'stage' => $stage,
            'submitted_at' => now(),
        ]);
    }
}
