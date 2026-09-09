<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Alliance\Recruitment;

use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Membership\Actions\EndMembershipForTransfer;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Actions\AssignRecruitmentReviewer;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentMembershipLockOrderV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{string,bool}> */
    public static function opposingOrders(): iterable
    {
        foreach (['reviewer', 'handoff'] as $operation) {
            yield $operation.' first officer' => [$operation, false];
            yield $operation.' second officer' => [$operation, true];
        }
    }

    #[DataProvider('opposingOrders')]
    public function test_opposing_officers_serialize_before_actor_or_target_membership(string $operation, bool $reverse): void
    {
        [, $allianceId, $first, $second, $candidateId] = $this->fixture();
        [$actor, $other] = $reverse ? [$second, $first] : [$first, $second];
        $write = fn () => $this->write($operation, $actor, $allianceId, $other, $candidateId);
        $otherWrite = fn () => $this->write($operation, $other, $allianceId, $actor, $candidateId);
        $primary = $this->competitor();
        $attempted = false;
        $competingMembershipLocks = 0;
        $before = $this->state();
        DB::listen(static function (QueryExecuted $query) use ($primary, $actor, $otherWrite, &$attempted, &$competingMembershipLocks): void {
            if ($query->connectionName === 'reviewer_writer' && str_starts_with($query->sql, 'select * from "alliance_memberships"') && str_contains($query->sql, 'for update')) {
                $competingMembershipLocks++;
            }
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliance_memberships"') || ! str_contains($query->sql, 'for update') || ! in_array($actor, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('reviewer_writer');
            try {
                try {
                    $otherWrite();
                    self::fail('Opposing target authority must wait before acquiring its actor membership.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertSame(0, $competingMembershipLocks);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            if ($operation === 'reviewer') {
                $write();
                $otherWrite();
                self::assertSame(2, DB::table('recruitment_candidate_reviewers')->where('candidate_id', $candidateId)->count());
                $beforeRetry = $this->state();
                $write();
                $otherWrite();
                self::assertSame($beforeRetry, $this->state());
            } else {
                foreach ([$write, $otherWrite] as $attempt) {
                    try {
                        $attempt();
                        self::fail('Equal officers must remain protected by the membership hierarchy.');
                    } catch (AuthorizationException) {
                        self::assertSame($before, $this->state());
                    }
                }
            }
            self::assertTrue($attempted);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('reviewer_writer');
        }
    }

    /** @return iterable<string,array{bool}> */
    public static function revocationOrders(): iterable
    {
        yield 'assignment first' => [false];
        yield 'suspension first' => [true];
    }

    #[DataProvider('revocationOrders')]
    public function test_reviewer_eligibility_uses_current_membership_after_suspension(bool $suspendFirst): void
    {
        [$owner, $allianceId, $reviewer, , $candidateId] = $this->fixture();
        $membership = AllianceMembership::query()->where('alliance_id', $allianceId)->where('player_id', $reviewer)->sole();
        $assign = static fn () => app(AssignRecruitmentReviewer::class)->handle($owner, $allianceId, $candidateId, $reviewer);
        $suspend = static fn () => app(UpdateMembershipStatus::class)->handle(allianceId: $allianceId, actorPlayerId: $owner, membershipId: (string) $membership->id, status: MembershipStatus::Suspended);
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $suspendFirst, $assign, $suspend, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliances"') || ! str_contains($query->sql, 'for ')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('reviewer_writer');
            try {
                try {
                    $suspendFirst ? $assign() : $suspend();
                    self::fail('Reviewer eligibility and membership revocation must serialize.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $suspendFirst ? $suspend() : $assign();
            self::assertTrue($attempted);
            if (! $suspendFirst) {
                $suspend();
            }
            $beforeRetry = $this->state();
            try {
                $assign();
                self::fail('A suspended reviewer cannot receive a current assignment.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('reviewer_player_id', $exception->errors());
            }
            self::assertSame($beforeRetry, $this->state());
            self::assertSame($suspendFirst ? 0 : 1, DB::table('recruitment_candidate_reviewers')->where('candidate_id', $candidateId)->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('reviewer_writer');
        }
    }

    /** @return iterable<string,array{string}> */
    public static function operations(): iterable
    {
        yield 'reviewer' => ['reviewer'];
        yield 'handoff' => ['handoff'];
    }

    #[DataProvider('operations')]
    public function test_unrelated_alliance_operations_continue_while_scope_is_held(string $operation): void
    {
        [$owner, $allianceId, $target, , $candidateId] = $this->fixture();
        [$otherOwner, $otherAllianceId, $otherTarget, , $otherCandidateId] = $this->fixture();
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(function (QueryExecuted $query) use ($primary, $operation, $otherOwner, $otherAllianceId, $otherTarget, $otherCandidateId, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliances"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('reviewer_writer');
            try {
                $this->write($operation, $otherOwner, $otherAllianceId, $otherTarget, $otherCandidateId);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $this->write($operation, $owner, $allianceId, $target, $candidateId);
            self::assertTrue($attempted);
            $event = $operation === 'reviewer' ? 'recruitment.reviewer.assigned' : 'membership.transfer_handoff_completed';
            self::assertSame(2, DB::table('audit_events')->where('event', $event)->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('reviewer_writer');
        }
    }

    #[DataProvider('operations')]
    public function test_late_delivery_failure_restores_protected_target_state(string $operation): void
    {
        [$owner, $allianceId, $target, , $candidateId] = $this->fixture();
        $event = $operation === 'reviewer' ? 'recruitment.reviewer.assigned' : 'membership.transfer_handoff_completed';
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($event, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array($event, $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected protected target delivery failure.');
            }
        });
        try {
            $this->write($operation, $owner, $allianceId, $target, $candidateId);
            self::fail('Protected target effects must roll back with failed delivery.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected protected target delivery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        $this->write($operation, $owner, $allianceId, $target, $candidateId);
        $beforeRetry = $this->state();
        $this->write($operation, $owner, $allianceId, $target, $candidateId);
        self::assertSame($beforeRetry, $this->state());
    }

    private function write(string $operation, string $actor, string $allianceId, string $target, string $candidateId): void
    {
        if ($operation === 'reviewer') {
            app(AssignRecruitmentReviewer::class)->handle($actor, $allianceId, $candidateId, $target);
        } else {
            app(EndMembershipForTransfer::class)->handle($allianceId, $actor, $target);
        }
    }

    /** @return array{string,string,string,string,string} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59286);
        $alliance = $factory->alliance($owner);
        $recruiterRoleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, 'Current recruiter', [AlliancePermission::RecruitmentManage]);
        $officers = [];
        foreach ([1, 2] as $number) {
            $officer = $factory->player($factory->account()->userId, 59286);
            $membership = AllianceMembership::query()->create([
                'alliance_id' => $alliance->allianceId, 'player_id' => $officer->playerId,
                'rank' => AllianceRank::R4, 'status' => MembershipStatus::Active, 'joined_at' => now(),
            ]);
            app(AssignMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, $recruiterRoleId);
            $officers[] = $officer->playerId;
        }
        $candidate = RecruitmentCandidate::query()->create([
            'alliance_id' => $alliance->allianceId, 'full_name' => 'Review candidate',
            'email' => 'review@example.test', 'stage' => RecruitmentStage::New, 'submitted_at' => now(),
        ]);

        return [$owner->playerId, $alliance->allianceId, $officers[0], $officers[1], (string) $candidate->id];
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.reviewer_writer', array_replace(DB::connection()->getConfig(), ['name' => 'reviewer_writer']));
        DB::connection('reviewer_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['alliance_memberships', 'recruitment_candidates', 'recruitment_candidate_reviewers', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $state['membership_roles'] = DB::table('membership_roles')->orderBy('membership_id')->orderBy('role_id')->get()->toJson();

        return $state;
    }
}
