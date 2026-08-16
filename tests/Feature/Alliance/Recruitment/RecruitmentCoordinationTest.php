<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Recruitment;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Recruitment\Actions\AddRecruitmentNote;
use App\Contexts\Alliance\Recruitment\Actions\AssignRecruitmentReviewer;
use App\Contexts\Alliance\Recruitment\Actions\ChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Contexts\Alliance\Recruitment\Actions\ConvertAcceptedRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentDecisionTemplate;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Actions\MarkRecruitmentCommunicationSent;
use App\Contexts\Alliance\Recruitment\Actions\MergeRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Actions\PrepareRecruitmentDecisionCommunication;
use App\Contexts\Alliance\Recruitment\Actions\PurgeExpiredRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Contexts\Alliance\Recruitment\Actions\TagRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentCommunicationStatus;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentDuplicateFinder;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentMetricsQuery;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\Messaging\Outbox\Actions\PublishOutboxBatch;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecruitmentCoordinationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_reviewer_notes_tags_duplicate_detection_and_merge_preserve_player_provenance(): void
    {
        $owner = User::factory()->create();
        $reviewerUser = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2110, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'recruitment-ops-owner',
            'current_name' => 'Recruitment Ops Owner',
        ]);
        $reviewerPlayer = Player::query()->create([
            'user_id' => $reviewerUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'recruitment-ops-reviewer',
            'current_name' => 'Recruitment Ops Reviewer',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Recruitment Ops', 'recruitment-ops');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $ownerPlayer,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            90,
            true,
        );
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $reviewerPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $submit = $this->app->make(SubmitRecruitmentApplication::class);
        $source = $submit->handle($alliance, 'Source Candidate', 'source@example.com', [], contactHandle: 'same-player');
        $target = $submit->handle($alliance, 'Target Candidate', 'target@example.com', [], contactHandle: 'same-player');

        $this->app->make(AssignRecruitmentReviewer::class)->handle($ownerPlayer, $alliance, $source, $reviewerPlayer);
        $this->app->make(TagRecruitmentCandidate::class)->handle($ownerPlayer, $alliance, $source, 'High Priority');
        $note = $this->app->make(AddRecruitmentNote::class)->handle($ownerPlayer, $alliance, $source, 'Private recruiter context');
        self::assertSame('Private recruiter context', $note->body);
        self::assertSame($ownerPlayer->id, $note->author_player_id);

        $duplicates = $this->app->make(RecruitmentDuplicateFinder::class)->forCandidate($alliance, $target);
        self::assertSame([$source->id], $duplicates->pluck('id')->all());

        $merge = $this->app->make(MergeRecruitmentCandidates::class);
        $mergedTarget = $merge->handle($ownerPlayer, $alliance, $source, $target, 'Verified same player');
        self::assertSame($target->id, $mergedTarget->id);
        self::assertSame($target->id, $source->refresh()->merged_into_id);
        self::assertCount(1, $target->refresh()->reviewers);
        self::assertSame($reviewerPlayer->id, $target->reviewers->sole()->id);
        self::assertCount(1, $target->refresh()->tags);
        self::assertSame('high priority', $target->tags->sole()->name);
        self::assertTrue($target->notes()->where('body', 'Merge reason: Verified same player')->exists());
        self::assertSame($target->id, $merge->handle($ownerPlayer, $alliance, $source->refresh(), $target->refresh())->id);
    }

    public function test_accepted_candidate_conversion_targets_specific_player_and_invitation_acceptance_marks_joined(): void
    {
        $owner = User::factory()->create();
        $candidateUser = User::factory()->create(['email' => 'accepted@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 2111, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'join-pipeline-owner',
            'current_name' => 'Join Pipeline Owner',
        ]);
        $candidatePlayer = Player::query()->create([
            'user_id' => $candidateUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'join-pipeline-candidate',
            'current_name' => 'Accepted Candidate',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Join Pipeline', 'join-pipeline');
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $candidatePlayer->id,
            'observed_name' => $candidatePlayer->current_name,
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'test',
        ]);
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $ownerPlayer,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            90,
            true,
        );
        $this->app->make(CreateRecruitmentOnboardingItem::class)->handle(
            $ownerPlayer,
            $alliance,
            'Read alliance rules',
            'Review the member handbook.',
        );
        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle(
            $alliance,
            'Accepted Candidate',
            'accepted@example.com',
            [],
            applicant: $candidateUser,
        );
        $changeStage = $this->app->make(ChangeRecruitmentStage::class);
        $candidate = $changeStage->handle($ownerPlayer, $alliance, $candidate, RecruitmentStage::Screening);
        $candidate = $changeStage->handle($ownerPlayer, $alliance, $candidate, RecruitmentStage::Accepted);

        $convert = $this->app->make(ConvertAcceptedRecruitmentCandidate::class);
        $converted = $convert->handle($ownerPlayer, $alliance, $candidate, $candidatePlayer);
        self::assertTrue($converted->wasCreated);
        self::assertNotNull($converted->token);
        self::assertSame($candidatePlayer->id, $candidate->refresh()->player_id);
        self::assertSame($converted->invitationId, $candidate->membership_invitation_id);
        $onboarding = RecruitmentCandidateOnboarding::query()->where('candidate_id', $candidate->id)->sole();
        self::assertSame(RecruitmentOnboardingStatus::Pending, $onboarding->status);

        $repeat = $convert->handle($ownerPlayer, $alliance, $candidate->refresh(), $candidatePlayer);
        self::assertFalse($repeat->wasCreated);
        self::assertNull($repeat->token);
        self::assertSame($converted->invitationId, $repeat->invitationId);

        $membership = $this->app->make(AcceptInvitation::class)->handle($candidateUser, (string) $converted->token);
        self::assertSame($candidatePlayer->id, $membership->player_id);
        $this->app->make(PublishOutboxBatch::class)->handle(500);

        self::assertSame(RecruitmentStage::Joined, $candidate->refresh()->stage);
        self::assertNotNull($candidate->joined_at);
        self::assertTrue(RecruitmentStageHistory::query()
            ->where('candidate_id', $candidate->id)
            ->where('to_stage', RecruitmentStage::Joined->value)
            ->exists());
    }

    public function test_decision_communication_is_idempotent_and_delivery_is_attributable_to_player(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2112, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'decision-pipeline-owner',
            'current_name' => 'Decision Pipeline Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Decision Pipeline', 'decision-pipeline');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $ownerPlayer,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            90,
            true,
        );
        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle($alliance, 'Declined Candidate', 'declined@example.com', []);
        $candidate = $this->app->make(ChangeRecruitmentStage::class)->handle(
            $ownerPlayer,
            $alliance,
            $candidate,
            RecruitmentStage::Declined,
            'Insufficient availability',
        );
        $template = $this->app->make(CreateRecruitmentDecisionTemplate::class)->handle(
            $ownerPlayer,
            $alliance,
            'Decline standard',
            RecruitmentStage::Declined,
            'Update from {{alliance_name}}',
            'Hello {{candidate_name}}, thank you for applying to {{alliance_name}}.',
        );

        $prepare = $this->app->make(PrepareRecruitmentDecisionCommunication::class);
        $first = $prepare->handle($ownerPlayer, $alliance, $candidate, $template);
        $second = $prepare->handle($ownerPlayer, $alliance, $candidate, $template);
        self::assertSame($first->id, $second->id);
        self::assertSame($ownerPlayer->id, $first->created_by_player_id);
        self::assertSame('Update from Decision Pipeline', $first->subject);
        self::assertStringContainsString('Declined Candidate', $first->body);
        self::assertSame(RecruitmentCommunicationStatus::Prepared, $first->status);

        $sent = $this->app->make(MarkRecruitmentCommunicationSent::class)->handle($ownerPlayer, $alliance, $first);
        self::assertSame(RecruitmentCommunicationStatus::Sent, $sent->status);
        self::assertNotNull($sent->sent_at);
        self::assertSame($sent->id, $this->app->make(MarkRecruitmentCommunicationSent::class)
            ->handle($ownerPlayer, $alliance, $sent)->id);
    }

    public function test_metrics_are_alliance_scoped_and_expired_unsuccessful_candidate_is_anonymized(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 12:00:00', 'UTC'));

        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 2113, 'status' => 'active']);
        $secondKingdom = Kingdom::query()->create(['number' => 2114, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'metrics-recruitment-owner',
            'current_name' => 'Metrics Recruitment Owner',
        ]);
        $otherOwnerPlayer = Player::query()->create([
            'user_id' => $otherOwner->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'other-recruitment-owner',
            'current_name' => 'Other Recruitment Owner',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $alliance = $createAlliance->handle($ownerPlayer, 'Metrics Recruiting', 'metrics-recruiting');
        $other = $createAlliance->handle($otherOwnerPlayer, 'Other Recruiting', 'other-recruiting');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $ownerPlayer,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            1,
            true,
        );
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $otherOwnerPlayer,
            $other,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            90,
            true,
        );
        $submit = $this->app->make(SubmitRecruitmentApplication::class);
        $active = $submit->handle($alliance, 'Active Candidate', 'active@example.com', [], source: 'discord');
        $declined = $submit->handle($alliance, 'Expired Candidate', 'expired@example.com', [], source: 'referral');
        $submit->handle($other, 'Other Candidate', 'other@example.com', [], source: 'discord');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 14:00:00', 'UTC'));
        $active = $this->app->make(ChangeRecruitmentStage::class)->handle($ownerPlayer, $alliance, $active, RecruitmentStage::Screening);
        $declined = $this->app->make(ChangeRecruitmentStage::class)->handle($ownerPlayer, $alliance, $declined, RecruitmentStage::Declined, 'Retention test reason');

        $metrics = $this->app->make(RecruitmentMetricsQuery::class)->summary($alliance);
        self::assertSame(2, $metrics['total']);
        self::assertSame(1, $metrics['byStage'][RecruitmentStage::Screening->value]);
        self::assertSame(1, $metrics['byStage'][RecruitmentStage::Declined->value]);
        self::assertSame(['discord' => 1, 'referral' => 1], $metrics['bySource']);
        self::assertSame(2.0, $metrics['averageResponseHours']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-09 15:00:00', 'UTC'));
        self::assertSame(1, $this->app->make(PurgeExpiredRecruitmentCandidates::class)->handle());
        $declined->refresh();
        self::assertNotNull($declined->anonymized_at);
        self::assertSame('Deleted candidate', $declined->full_name);
        self::assertStringStartsWith('deleted+', $declined->email);
        self::assertNull($declined->contact_handle);
        self::assertNull($declined->retention_due_at);
        self::assertNull(RecruitmentStageHistory::query()
            ->where('candidate_id', $declined->id)
            ->latest('changed_at')
            ->value('reason'));
        self::assertSame('Active Candidate', $active->refresh()->full_name);
    }
}
