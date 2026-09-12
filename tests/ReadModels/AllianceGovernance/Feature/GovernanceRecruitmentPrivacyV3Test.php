<?php

declare(strict_types=1);

namespace Tests\ReadModels\AllianceGovernance\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\Alliance\Recruitment\Actions\SetRecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;
use App\ReadModels\AllianceAssistant\Queries\AllianceAssistantQuery;
use App\ReadModels\AllianceGovernance\Queries\AllianceGovernanceTimelineQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class GovernanceRecruitmentPrivacyV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{bool}> */
    public static function recruitmentAccess(): iterable
    {
        yield 'governance only' => [false];
        yield 'governance and recruitment' => [true];
    }

    #[DataProvider('recruitmentAccess')]
    public function test_direct_http_and_assistant_history_use_current_recruitment_access(bool $recruiter): void
    {
        [$owner, $viewer, $user, $scope, , $recruitmentRole] = $this->fixture();
        if (! $recruiter) {
            app(RemoveMembershipRole::class)->handle($scope->allianceId, $owner->playerId, $scope->membershipId, $recruitmentRole);
        }

        $timeline = app(AllianceGovernanceTimelineQuery::class)->forAlliance($viewer->playerId, $scope->allianceId, eventPrefix: 'recruitment');
        self::assertCount($recruiter ? 1 : 0, $timeline['items']);
        $assistant = app(AllianceAssistantQuery::class)->ask($viewer, $scope, 'What changed in Alliance governance history?');
        self::assertSame(AssistantStatus::Answered, $assistant->status);
        $serialized = json_encode($assistant->toArray(), JSON_THROW_ON_ERROR);
        if ($recruiter) {
            self::assertSame('Private applicant reason', $timeline['items'][0]['metadata']['to']['reason']);
            self::assertStringContainsString('Private applicant reason', $serialized);
        } else {
            self::assertStringNotContainsString('Private applicant reason', $serialized);
            self::assertStringNotContainsString('recruitment.reentry_control_changed', $serialized);
        }
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $viewer->playerId])
            ->get(route('alliance.history.index', ['capability' => 'recruitment']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Alliance/History/Index')->has('timeline.items', $recruiter ? 1 : 0));
    }

    public function test_private_records_are_filtered_before_the_page_limit_and_continuation(): void
    {
        [$owner, $viewer, , $scope, , $recruitmentRole] = $this->fixture();
        app(RemoveMembershipRole::class)->handle($scope->allianceId, $owner->playerId, $scope->membershipId, $recruitmentRole);
        $older = $this->audit($scope, 'alliance.settings_changed');
        $newer = $this->audit($scope, 'alliance.settings_changed');
        for ($i = 0; $i < 3; $i++) {
            $this->audit($scope, 'recruitment.application.submitted');
        }
        $query = app(AllianceGovernanceTimelineQuery::class);
        $first = $query->forAlliance($viewer->playerId, $scope->allianceId, limit: 1);
        self::assertSame($newer->id, $first['items'][0]['id']);
        self::assertSame($newer->id, $first['nextCursor']);
        $second = $query->forAlliance($viewer->playerId, $scope->allianceId, beforeId: $first['nextCursor'], limit: 1);
        self::assertSame($older->id, $second['items'][0]['id']);
    }

    public function test_recruitment_revocation_applies_to_existing_query_instances_and_cursor(): void
    {
        [$owner, $viewer, $user, $scope, , $recruitmentRole] = $this->fixture();
        $query = app(AllianceGovernanceTimelineQuery::class);
        $assistant = app(AllianceAssistantQuery::class);
        $newer = $this->audit($scope, 'recruitment.application.submitted');
        $first = $query->forAlliance($viewer->playerId, $scope->allianceId, eventPrefix: 'recruitment', limit: 1);
        self::assertSame($newer->id, $first['nextCursor']);
        self::assertStringContainsString('Private applicant reason', json_encode($assistant->ask($viewer, $scope, 'What changed in Alliance governance history?')->toArray(), JSON_THROW_ON_ERROR));
        app(RemoveMembershipRole::class)->handle($scope->allianceId, $owner->playerId, $scope->membershipId, $recruitmentRole);
        $next = $query->forAlliance($viewer->playerId, $scope->allianceId, eventPrefix: 'recruitment', beforeId: $first['nextCursor']);
        self::assertSame(['items' => [], 'nextCursor' => null], $next);
        self::assertStringNotContainsString('Private applicant reason', json_encode($assistant->ask($viewer, $scope, 'What changed in Alliance governance history?')->toArray(), JSON_THROW_ON_ERROR));
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $viewer->playerId])
            ->get(route('alliance.history.index', ['capability' => 'recruitment', 'before' => $first['nextCursor']]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('timeline.items', 0)->where('timeline.nextCursor', null));
    }

    public function test_recruitment_permission_does_not_replace_current_governance_admission(): void
    {
        [$owner, $viewer, $user, $scope, $governanceRole] = $this->fixture();
        $query = app(AllianceGovernanceTimelineQuery::class);
        $assistant = app(AllianceAssistantQuery::class);
        self::assertNotEmpty($query->forAlliance($viewer->playerId, $scope->allianceId)['items']);
        app(RemoveMembershipRole::class)->handle($scope->allianceId, $owner->playerId, $scope->membershipId, $governanceRole);
        foreach ([
            static fn () => $query->forAlliance($viewer->playerId, $scope->allianceId),
            static fn () => $assistant->ask($viewer, $scope, 'What changed in Alliance governance history?'),
        ] as $read) {
            try {
                $read();
                self::fail('Recruitment access alone must not admit a governance read.');
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $viewer->playerId])
            ->get(route('alliance.history.index'))->assertForbidden();
    }

    public function test_viewer_and_events_remain_bound_to_the_current_alliance(): void
    {
        [, $viewer, , $scope] = $this->fixture();
        $factory = new ScenarioFactory;
        $foreignUser = $factory->authUser();
        $foreignOwner = $factory->player((int) $foreignUser->id, 59294);
        $foreignAlliance = $factory->alliance($foreignOwner);
        $foreign = AuditEvent::query()->create([
            'alliance_id' => $foreignAlliance->allianceId, 'actor_player_id' => $foreignOwner->playerId,
            'event' => 'recruitment.application.submitted', 'subject_type' => Alliance::class,
            'subject_id' => $foreignAlliance->allianceId, 'metadata' => ['source' => 'Foreign private source'], 'created_at' => now(),
        ]);
        $query = app(AllianceGovernanceTimelineQuery::class);
        self::assertNotContains($foreign->id, array_column($query->forAlliance($viewer->playerId, $scope->allianceId)['items'], 'id'));
        $this->expectException(AuthorizationException::class);
        $query->forAlliance($viewer->playerId, $foreignAlliance->allianceId);
    }

    /** @return array{PlayerReference,PlayerReference,User,AllianceScopeReference,string,string} */
    private function fixture(): array
    {
        $factory = new ScenarioFactory;
        $ownerUser = $factory->authUser();
        $owner = $factory->player((int) $ownerUser->id, 59293);
        $alliance = $factory->alliance($owner);
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $viewer = $factory->player((int) $user->id, 59293);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $viewer->playerId,
            'status' => MembershipStatus::Active, 'rank' => AllianceRank::R3, 'joined_at' => now(),
        ]);
        $governanceRole = app(CreateAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, 'Governance reader', [AlliancePermission::RoleManage]);
        $recruitmentRole = app(CreateAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, 'Recruitment reader', [AlliancePermission::RecruitmentManage]);
        foreach ([$governanceRole, $recruitmentRole] as $role) {
            app(AssignMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, $role);
        }
        $candidate = RecruitmentCandidate::query()->create([
            'alliance_id' => $alliance->allianceId, 'full_name' => 'Private applicant', 'stage' => RecruitmentStage::New,
            'submitted_at' => now(), 'email' => 'private@example.test',
        ]);
        app(SetRecruitmentReentryControl::class)->handle($owner->playerId, $alliance->allianceId, (string) $candidate->id, RecruitmentReentryControl::ReviewRequired, 'Private applicant reason');
        $scope = new AllianceScopeReference($viewer->playerId, $viewer->kingdomId, $alliance->allianceId, (string) $membership->id);

        return [$owner, $viewer, $user, $scope, $governanceRole, $recruitmentRole];
    }

    private function audit(AllianceScopeReference $scope, string $event): AuditEvent
    {
        return AuditEvent::query()->create([
            'alliance_id' => $scope->allianceId, 'actor_player_id' => $scope->playerId, 'event' => $event,
            'subject_type' => Alliance::class, 'subject_id' => $scope->allianceId, 'metadata' => [], 'created_at' => now(),
        ]);
    }
}
