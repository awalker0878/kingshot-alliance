<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceGovernance;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\ReadModels\AllianceGovernance\Queries\AllianceGovernanceTimelineQuery;
use App\ReadModels\AllianceGovernance\Queries\MembershipGovernanceHistoryQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceGovernanceReadModelsBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_governance_timeline_is_scope_bound_filterable_and_cursor_paginated(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59230);
        $alliance = $scenario->alliance($owner);
        $otherAccount = $scenario->authUser();
        $otherOwner = $scenario->player((int) $otherAccount->id, 59231);
        $otherAlliance = $scenario->alliance($otherOwner);

        $rank = $this->audit(
            $alliance->allianceId,
            $owner->playerId,
            'membership.rank_changed',
            ['target_player_id' => $owner->playerId, 'old_rank' => 'r4', 'new_rank' => 'r5'],
        );
        $reentry = $this->audit(
            $alliance->allianceId,
            $owner->playerId,
            'recruitment.reentry_control_changed',
            ['candidate_id' => 'candidate-1', 'control' => 'review_required'],
        );
        $this->audit(
            $alliance->allianceId,
            $owner->playerId,
            'security.session_revoked',
            ['target_player_id' => $owner->playerId],
        );
        $this->audit(
            $otherAlliance->allianceId,
            $otherOwner->playerId,
            'alliance.settings_changed',
            ['changed_fields' => ['name']],
        );

        $query = app(AllianceGovernanceTimelineQuery::class);
        $firstPage = $query->forAlliance($alliance->allianceId, limit: 1);

        self::assertCount(1, $firstPage['items']);
        self::assertSame($reentry->id, $firstPage['items'][0]['id']);
        self::assertSame('/alliance/recruitment', $firstPage['items'][0]['handoff']);
        self::assertNotNull($firstPage['nextCursor']);

        $secondPage = $query->forAlliance(
            allianceId: $alliance->allianceId,
            beforeId: $firstPage['nextCursor'],
            limit: 1,
        );
        self::assertCount(1, $secondPage['items']);
        self::assertSame($rank->id, $secondPage['items'][0]['id']);
        self::assertSame('/alliance', $secondPage['items'][0]['handoff']);

        $membershipOnly = $query->forAlliance(
            allianceId: $alliance->allianceId,
            eventPrefix: 'membership',
            actorPlayerId: $owner->playerId,
        );
        self::assertCount(1, $membershipOnly['items']);
        self::assertSame('membership.rank_changed', $membershipOnly['items'][0]['type']);
        self::assertSame($owner->playerId, $membershipOnly['items'][0]['actor']['playerId']);
    }

    public function test_member_history_uses_only_supported_events_that_factually_touch_the_target_player(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59232);
        $alliance = $scenario->alliance($owner);
        $otherAccount = $scenario->authUser();
        $other = $scenario->player((int) $otherAccount->id, 59232);

        $included = $this->audit(
            $alliance->allianceId,
            $owner->playerId,
            'membership.role_assigned',
            ['target_player_id' => $owner->playerId, 'role_key' => 'recruiter'],
        );
        $this->audit(
            $alliance->allianceId,
            $owner->playerId,
            'membership.rank_changed',
            ['target_player_id' => $other->playerId, 'new_rank' => 'r3'],
        );
        $this->audit(
            $alliance->allianceId,
            $owner->playerId,
            'alliance.settings_changed',
            ['target_player_id' => $owner->playerId],
        );

        $history = app(MembershipGovernanceHistoryQuery::class)->forPlayer(
            $alliance->allianceId,
            $owner->playerId,
        );

        self::assertCount(1, $history);
        self::assertSame($included->id, $history[0]['id']);
        self::assertSame('membership.role_assigned', $history[0]['type']);
        self::assertSame('audit', $history[0]['source']);
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $allianceId, string $actorPlayerId, string $event, array $metadata): AuditEvent
    {
        return AuditEvent::query()->create([
            'alliance_id' => $allianceId,
            'actor_player_id' => $actorPlayerId,
            'event' => $event,
            'subject_type' => Alliance::class,
            'subject_id' => $allianceId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
