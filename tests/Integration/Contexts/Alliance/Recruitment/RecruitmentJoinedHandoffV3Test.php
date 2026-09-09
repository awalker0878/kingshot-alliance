<?php

declare(strict_types=1);

namespace Tests\Integration\Contexts\Alliance\Recruitment;

use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Actions\BulkChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\ChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\ConvertAcceptedRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Actions\MarkRecruitmentCandidateJoined;
use App\Contexts\Alliance\Recruitment\Actions\PreviewRecruitmentStageBulkChange;
use App\Contexts\Alliance\Recruitment\Actions\PurgeExpiredRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\ValueObjects\ConvertedRecruitmentCandidate;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentJoinedHandoffV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool}> */
    public static function invitationStates(): iterable
    {
        yield 'without invitation' => [false];
        yield 'pending invitation' => [true];
    }

    #[DataProvider('invitationStates')]
    public function test_manual_and_bulk_owners_cannot_record_joined_before_acceptance(bool $converted): void
    {
        [$ownerId, $allianceId, $candidate] = $this->fixture($converted);
        $before = $this->state();
        try {
            app(ChangeRecruitmentStage::class)->handle($ownerId, $allianceId, (string) $candidate->id, RecruitmentStage::Joined);
            self::fail('Issuance is not evidence of accepted Membership admission.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('stage', $exception->errors());
        }
        self::assertSame($before, $this->state());
        $preview = app(PreviewRecruitmentStageBulkChange::class)->handle($ownerId, $allianceId, [(string) $candidate->id], RecruitmentStage::Joined);
        self::assertSame(0, $preview['ready']);
        self::assertSame('transition-not-allowed', $preview['items'][0]['code']);
        $result = app(BulkChangeRecruitmentStage::class)->handle($ownerId, $allianceId, [(string) $candidate->id], RecruitmentStage::Joined)->toArray();
        self::assertSame(0, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertSame(RecruitmentStage::Accepted, $candidate->fresh()?->stage);
        self::assertSame(0, DB::table('audit_events')->where('event', 'recruitment.candidate.joined')->count());
    }

    /** @return iterable<string,array{bool}> */
    public static function deliveryFailures(): iterable
    {
        yield 'successful projection' => [false];
        yield 'late projection rollback and retry' => [true];
    }

    #[DataProvider('deliveryFailures')]
    public function test_actual_invitation_acceptance_projects_joined_once_and_rolls_back_failed_delivery(bool $failDelivery): void
    {
        [, $allianceId, $candidate, $userId, $playerId, $converted] = $this->fixture(true);
        self::assertInstanceOf(ConvertedRecruitmentCandidate::class, $converted);
        self::assertIsString($converted->token);
        app(AcceptInvitation::class)->handle($userId, $converted->token, $playerId);
        self::assertTrue(AllianceMembership::query()->where('alliance_id', $allianceId)->where('player_id', $playerId)->where('status', MembershipStatus::Active)->exists());
        $event = $this->acceptedEvent($converted->invitationId);
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($failDelivery, &$failed): void {
            if ($failDelivery && ! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array('recruitment.candidate.joined', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected joined projection delivery failure.');
            }
        });
        try {
            app(MarkRecruitmentCandidateJoined::class)->handle($event);
            self::assertFalse($failDelivery);
        } catch (RuntimeException $exception) {
            self::assertTrue($failDelivery);
            self::assertSame('Injected joined projection delivery failure.', $exception->getMessage());
            self::assertSame($before, $this->state());
            app(MarkRecruitmentCandidateJoined::class)->handle($event);
        }
        self::assertSame($failDelivery, $failed);
        self::assertSame(RecruitmentStage::Joined, $candidate->fresh()?->stage);
        self::assertSame(1, DB::table('recruitment_stage_history')->where('candidate_id', $candidate->id)->where('to_stage', RecruitmentStage::Joined->value)->count());
        self::assertSame(1, DB::table('audit_events')->where('event', 'recruitment.candidate.joined')->where('actor_player_id', $playerId)->whereNull('actor_user_id')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'recruitment.candidate.joined')->count());
        $beforeReplay = $this->state();
        app(MarkRecruitmentCandidateJoined::class)->handle($event);
        self::assertSame($beforeReplay, $this->state());
    }

    /** @return iterable<string,array{bool}> */
    public static function terminalStates(): iterable
    {
        yield 'declined before delivery' => [false];
        yield 'anonymized before delivery' => [true];
    }

    #[DataProvider('terminalStates')]
    public function test_delayed_acceptance_event_does_not_reopen_a_terminal_candidate(bool $purged): void
    {
        [$ownerId, $allianceId, $candidate, $userId, $playerId, $converted] = $this->fixture(true);
        self::assertInstanceOf(ConvertedRecruitmentCandidate::class, $converted);
        self::assertIsString($converted->token);
        app(AcceptInvitation::class)->handle($userId, $converted->token, $playerId);
        $event = $this->acceptedEvent($converted->invitationId);
        app(ChangeRecruitmentStage::class)->handle($ownerId, $allianceId, (string) $candidate->id, RecruitmentStage::Declined, 'Decision changed before projection');
        if ($purged) {
            $this->travel(91)->days();
            self::assertSame(1, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        }
        $before = $this->state();
        app(MarkRecruitmentCandidateJoined::class)->handle($event);
        app(MarkRecruitmentCandidateJoined::class)->handle($event);
        self::assertSame($before, $this->state());
        self::assertSame(RecruitmentStage::Declined, $candidate->fresh()?->stage);
    }

    /** @return array{string,string,RecruitmentCandidate,int,string,ConvertedRecruitmentCandidate|null} */
    private function fixture(bool $convert): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59288);
        $alliance = $factory->alliance($owner);
        $account = $factory->account();
        $target = $factory->player($account->userId, 59288);
        $factory->roster($owner, $alliance, $target);
        $candidate = RecruitmentCandidate::query()->create([
            'alliance_id' => $alliance->allianceId, 'full_name' => 'Accepted applicant', 'email' => $account->email,
            'stage' => RecruitmentStage::Accepted, 'submitted_at' => now(), 'accepted_at' => now(),
        ]);
        $converted = $convert ? app(ConvertAcceptedRecruitmentCandidate::class)->handle($owner->playerId, $alliance->allianceId, (string) $candidate->id, $target->playerId) : null;

        return [$owner->playerId, $alliance->allianceId, $candidate, $account->userId, $target->playerId, $converted];
    }

    private function acceptedEvent(string $invitationId): OutboxPublished
    {
        $message = OutboxMessage::query()->where('event_type', 'invitation.accepted')->where('aggregate_id', $invitationId)->sole();

        return new OutboxPublished(
            (string) $message->id, $message->alliance_id, $message->event_type, $message->aggregate_type,
            $message->aggregate_id, $message->idempotency_key, $message->payload, $message->occurred_at->toIso8601String(),
        );
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['recruitment_candidates', 'recruitment_stage_history', 'recruitment_candidate_onboarding', 'invitations', 'alliance_memberships', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
