<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Recruitment;

use App\Contexts\Alliance\Recruitment\Actions\ConvertAcceptedRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Actions\SetRecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RecruitmentReentryBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_do_not_invite_control_blocks_candidate_conversion_and_is_alliance_local(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59201);
        $alliance = $scenario->alliance($owner);
        $target = $scenario->unclaimedPlayer(59201);
        $candidate = $this->candidate($alliance->allianceId, 'Blocked Candidate');

        app(SetRecruitmentReentryControl::class)->handle(
            $owner->playerId,
            $alliance->allianceId,
            (string) $candidate->id,
            RecruitmentReentryControl::DoNotInvite,
            'Officer review determined this candidate should not be invited.',
        );

        try {
            app(ConvertAcceptedRecruitmentCandidate::class)->handle(
                $owner->playerId,
                $alliance->allianceId,
                (string) $candidate->id,
                $target->playerId,
            );
            self::fail('Expected the Alliance-local do-not-invite control to block conversion.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('candidate', $exception->errors());
        }

        $candidate->refresh();
        self::assertSame(RecruitmentReentryControl::DoNotInvite, $candidate->reentry_control);
        self::assertNull($candidate->membership_invitation_id);
        self::assertTrue(AuditEvent::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('event', 'recruitment.reentry_control_changed')
            ->where('actor_player_id', $owner->playerId)
            ->exists());
    }

    public function test_reapply_after_requires_a_review_date_and_normal_clears_private_control_data(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59202);
        $alliance = $scenario->alliance($owner);
        $candidate = $this->candidate($alliance->allianceId, 'Review Candidate');

        try {
            app(SetRecruitmentReentryControl::class)->handle(
                $owner->playerId,
                $alliance->allianceId,
                (string) $candidate->id,
                RecruitmentReentryControl::ReapplyAfter,
                'Wait before reconsidering.',
                null,
            );
            self::fail('Expected reapply-after without a review date to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('review_at', $exception->errors());
        }

        app(SetRecruitmentReentryControl::class)->handle(
            $owner->playerId,
            $alliance->allianceId,
            (string) $candidate->id,
            RecruitmentReentryControl::ReviewRequired,
            'Needs another officer review.',
            now()->addWeek()->toIso8601String(),
        );
        app(SetRecruitmentReentryControl::class)->handle(
            $owner->playerId,
            $alliance->allianceId,
            (string) $candidate->id,
            RecruitmentReentryControl::Normal,
            'This text must be discarded.',
            now()->addMonth()->toIso8601String(),
        );

        $candidate->refresh();
        self::assertSame(RecruitmentReentryControl::Normal, $candidate->reentry_control);
        self::assertNull($candidate->reentry_reason);
        self::assertNull($candidate->reentry_review_at);
        self::assertNull($candidate->reentry_set_by_player_id);
        self::assertNull($candidate->reentry_set_at);
    }

    private function candidate(string $allianceId, string $name): RecruitmentCandidate
    {
        return RecruitmentCandidate::query()->create([
            'alliance_id' => $allianceId,
            'full_name' => $name,
            'email' => str_replace(' ', '.', strtolower($name)).'@example.test',
            'stage' => RecruitmentStage::Accepted,
            'submitted_at' => now(),
        ]);
    }
}
