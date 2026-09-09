<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\Alliance\Recruitment;

use App\Contexts\Alliance\Recruitment\Actions\BulkChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\PreviewRecruitmentStageBulkChange;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentBulkStageBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{bool,int}> */
    public static function invalidSelections(): iterable
    {
        foreach ([false, true] as $execute) {
            yield ($execute ? 'execute ' : 'preview ').'empty' => [$execute, 0];
            yield ($execute ? 'execute ' : 'preview ').'oversized' => [$execute, 51];
        }
    }

    #[DataProvider('invalidSelections')]
    public function test_direct_owner_rejects_invalid_selection_before_scope_queries(bool $execute, int $count): void
    {
        $ids = [];
        for ($index = 0; $index < $count; $index++) {
            $ids[] = (string) Str::ulid();
        }
        $queried = false;
        DB::listen(static function (QueryExecuted $query) use (&$queried): void {
            if (str_contains($query->sql, '"alliances"') || str_contains($query->sql, '"alliance_memberships"') || str_contains($query->sql, '"recruitment_candidates"')) {
                $queried = true;
            }
        });
        try {
            $action = $execute ? app(BulkChangeRecruitmentStage::class) : app(PreviewRecruitmentStageBulkChange::class);
            $action->handle((string) Str::ulid(), (string) Str::ulid(), $ids, RecruitmentStage::Screening);
            self::fail('Direct intake administration must share the bounded selection contract.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('candidate_ids', $exception->errors());
        }
        self::assertFalse($queried);
    }

    public function test_exactly_fifty_distinct_selections_remain_supported(): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($owner);
        $ids = [];
        for ($index = 0; $index < 50; $index++) {
            $ids[] = (string) Str::ulid();
        }
        $preview = app(PreviewRecruitmentStageBulkChange::class)->handle($owner->playerId, $alliance->allianceId, $ids, RecruitmentStage::Screening);
        self::assertCount(50, $preview['items']);
        self::assertSame($ids, array_column($preview['items'], 'itemId'));
        self::assertSame(50, $preview['blocked']);
    }

    public function test_duplicate_selections_execute_once_and_produce_a_canonical_receipt(): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($owner);
        $candidate = $this->candidate($alliance->allianceId, 'Current Candidate', RecruitmentStage::New);
        $result = app(BulkChangeRecruitmentStage::class)->handle($owner->playerId, $alliance->allianceId, array_fill(0, 60, (string) $candidate->id), RecruitmentStage::Screening)->toArray();
        self::assertSame(1, $result['succeeded']);
        self::assertSame(0, $result['failed']);
        self::assertSame(0, $result['skipped']);
        self::assertSame(RecruitmentStage::Screening, $candidate->fresh()?->stage);
        $receipt = AuditEvent::query()->where('event', 'recruitment.candidates.bulk_stage_changed')->sole();
        self::assertSame([(string) $candidate->id], $receipt->metadata['candidate_ids']);
        self::assertSame(1, DB::table('recruitment_stage_history')->where('candidate_id', $candidate->id)->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'recruitment.candidate.stage_changed')->count());
    }

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
