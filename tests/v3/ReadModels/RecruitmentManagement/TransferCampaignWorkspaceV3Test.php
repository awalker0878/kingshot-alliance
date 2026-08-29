<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\RecruitmentManagement;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\ReadModels\RecruitmentManagement\Queries\TransferCampaignWorkspaceQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TransferCampaignWorkspaceV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_unlinked_and_linked_candidates_keep_missing_transfer_state_explicit(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78301);
        $alliance = $scenario->alliance($actor);
        $candidate = $this->candidate($alliance->allianceId, null, 'Unlinked Candidate');

        $unlinked = app(TransferCampaignWorkspaceQuery::class)->forCandidate(
            $actor->playerId,
            $alliance->allianceId,
            $candidate,
        );
        self::assertSame('unlinked', $unlinked['playerLink']);
        self::assertNull($unlinked['transfer']);

        $candidate->forceFill(['player_id' => $actor->playerId])->save();
        $linked = app(TransferCampaignWorkspaceQuery::class)->forCandidate(
            $actor->playerId,
            $alliance->allianceId,
            $candidate->fresh(),
        );
        self::assertSame('linked', $linked['playerLink']);
        self::assertNull($linked['transfer']);
        self::assertSame('active', $linked['membership']['status']);
    }

    public function test_recruitment_authority_is_checked_inside_workspace_query(): void
    {
        $scenario = new ScenarioFactory;
        $ownerAccount = $scenario->account();
        $owner = $scenario->player($ownerAccount->userId, 78302);
        $alliance = $scenario->alliance($owner);
        $candidate = $this->candidate($alliance->allianceId, null, 'Protected Candidate');

        $memberAccount = $scenario->account();
        $member = $scenario->player($memberAccount->userId, 78302);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId,
            'player_id' => $member->playerId,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        app(TransferCampaignWorkspaceQuery::class)->forCandidate(
            $member->playerId,
            $alliance->allianceId,
            $candidate,
        );
    }

    public function test_candidate_identity_must_belong_to_the_authorized_alliance(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78304);
        $alliance = $scenario->alliance($actor);

        $otherAccount = $scenario->account();
        $other = $scenario->player($otherAccount->userId, 78305);
        $otherAlliance = $scenario->alliance($other);
        $candidate = $this->candidate($otherAlliance->allianceId, null, 'Other Candidate');

        $this->expectException(AuthorizationException::class);
        app(TransferCampaignWorkspaceQuery::class)->forCandidate(
            $actor->playerId,
            $alliance->allianceId,
            $candidate,
        );
    }

    private function candidate(string $allianceId, ?string $playerId, string $name): RecruitmentCandidate
    {
        return RecruitmentCandidate::query()->create([
            'alliance_id' => $allianceId,
            'player_id' => $playerId,
            'full_name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'@example.test',
            'source' => 'referral',
            'stage' => RecruitmentStage::Screening,
            'submitted_at' => now()->subDay(),
        ]);
    }
}
