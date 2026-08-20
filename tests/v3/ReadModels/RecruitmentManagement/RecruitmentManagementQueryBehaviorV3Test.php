<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\RecruitmentManagement;

use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\ReadModels\RecruitmentManagement\Queries\RecruitmentManagementQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RecruitmentManagementQueryBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_candidates_are_filtered_and_cursor_paginated_without_silent_truncation(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $player = $scenario->player((int) $account->id);
        $alliance = $scenario->alliance($player);

        foreach (range(0, 54) as $index) {
            RecruitmentCandidate::query()->create([
                'alliance_id' => $alliance->allianceId,
                'full_name' => sprintf('Candidate %02d', $index),
                'email' => sprintf('candidate-%02d@example.test', $index),
                'source' => $index % 2 === 0 ? 'event' : 'referral',
                'stage' => RecruitmentStage::New,
                'submitted_at' => now()->subMinutes($index),
            ]);
        }

        $query = app(RecruitmentManagementQuery::class);
        $first = $query->forAlliance($alliance->allianceId)['candidatePage'];

        self::assertCount(50, $first['items']);
        self::assertTrue($first['hasMore']);
        self::assertTrue($first['isFirstPage']);
        self::assertSame('Candidate 00', $first['items'][0]['name']);
        self::assertIsString($first['nextCursor']);

        $second = $query->forAlliance(
            $alliance->allianceId,
            cursor: $first['nextCursor'],
        )['candidatePage'];

        self::assertCount(5, $second['items']);
        self::assertFalse($second['hasMore']);
        self::assertFalse($second['isFirstPage']);
        self::assertSame('Candidate 50', $second['items'][0]['name']);

        $filtered = $query->forAlliance($alliance->allianceId, ['source' => 'event'])['candidatePage'];
        self::assertCount(28, $filtered['items']);
        self::assertSame([], array_values(array_filter(
            $filtered['items'],
            static fn (array $candidate): bool => $candidate['source'] !== 'event',
        )));
    }

    public function test_candidate_cursor_cannot_be_reused_with_different_filters(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $player = $scenario->player((int) $account->id);
        $alliance = $scenario->alliance($player);

        foreach (range(0, 50) as $index) {
            RecruitmentCandidate::query()->create([
                'alliance_id' => $alliance->allianceId,
                'full_name' => 'Candidate '.$index,
                'email' => 'candidate-'.$index.'@example.test',
                'stage' => RecruitmentStage::New,
                'submitted_at' => now()->subMinutes($index),
            ]);
        }

        $query = app(RecruitmentManagementQuery::class);
        $cursor = $query->forAlliance($alliance->allianceId)['candidatePage']['nextCursor'];
        self::assertIsString($cursor);

        $this->expectException(ValidationException::class);
        $query->forAlliance(
            $alliance->allianceId,
            ['stage' => RecruitmentStage::Accepted->value],
            $cursor,
        );
    }
}
