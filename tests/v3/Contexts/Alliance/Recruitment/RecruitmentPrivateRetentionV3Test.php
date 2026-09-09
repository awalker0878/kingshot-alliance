<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Recruitment;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Recruitment\Actions\ChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Contexts\Alliance\Recruitment\Actions\ConvertAcceptedRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Actions\PurgeExpiredRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Actions\SetRecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Contexts\Alliance\Recruitment\Actions\TagRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RecruitmentPrivateRetentionV3Test extends TestCase
{
    use DatabaseMigrations;

    public function test_private_control_is_audited_until_purge_then_redacted_and_unavailable(): void
    {
        [$actorId, $allianceId, $candidate, $user] = $this->fixture();
        $this->travel(91)->days();
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $actorId])
            ->get(route('alliance.recruitment.reentry.show', $candidate))->assertOk();
        $audit = AuditEvent::query()->where('event', 'recruitment.reentry_control_changed')->sole();
        self::assertSame('Private applicant review', $audit->metadata['to']['reason']);
        self::assertNotNull($audit->metadata['to']['review_at']);
        $delivery = OutboxMessage::query()->where('event_type', 'recruitment.reentry_control_changed')->sole();
        self::assertSame('normal', $delivery->payload['from_control']);
        self::assertSame('review_required', $delivery->payload['to_control']);
        self::assertTrue($delivery->payload['reason_changed']);
        self::assertTrue($delivery->payload['review_at_changed']);
        self::assertArrayNotHasKey('from', $delivery->payload);
        self::assertArrayNotHasKey('to', $delivery->payload);
        self::assertStringNotContainsString('Private applicant review', json_encode($delivery->payload, JSON_THROW_ON_ERROR));

        $auditIdentity = $audit->only(['id', 'alliance_id', 'actor_player_id', 'actor_user_id', 'event', 'subject_type', 'subject_id', 'created_at']);
        $handoff = $this->handoffState();
        self::assertSame(1, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        $candidate->refresh();
        foreach (['player_id', 'source', 'reentry_reason', 'reentry_review_at', 'reentry_set_by_player_id', 'reentry_set_at', 'membership_invitation_id'] as $field) {
            self::assertNull($candidate->getAttribute($field), $field);
        }
        self::assertSame(RecruitmentReentryControl::Normal, $candidate->reentry_control);
        self::assertSame(RecruitmentStage::Declined, $candidate->stage);
        self::assertNotNull($candidate->submitted_at);
        self::assertNotNull($candidate->declined_at);
        self::assertNotNull($candidate->anonymized_at);
        self::assertSame(['retention_redacted' => true], $audit->refresh()->metadata);
        self::assertEquals($auditIdentity, $audit->only(array_keys($auditIdentity)));
        self::assertSame($handoff, $this->handoffState());
        self::assertSame(0, DB::table('recruitment_stage_history')->where('candidate_id', $candidate->id)->whereNotNull('reason')->count());
        $this->get(route('alliance.recruitment.reentry.show', $candidate))->assertNotFound();
        $this->get(route('alliance.recruitment.candidates.show', $candidate))->assertNotFound();
        self::assertSame(1, AuditEvent::query()->where('event', 'recruitment.candidate.anonymized')->count());
        $beforeReplay = $this->state();
        self::assertSame(0, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        self::assertSame($beforeReplay, $this->state());
    }

    /** @return iterable<string,array{string}> */
    public static function failurePoints(): iterable
    {
        yield 'audit redaction' => ['audit'];
        yield 'terminal candidate update' => ['candidate'];
    }

    #[DataProvider('failurePoints')]
    public function test_retention_failure_restores_private_audit_and_candidate_before_retry(string $point): void
    {
        [, , $candidate] = $this->fixture();
        $this->travel(91)->days();
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($point, &$failed): void {
            $matches = $point === 'audit'
                ? str_starts_with($query->sql, 'update "audit_events"')
                : str_starts_with($query->sql, 'update "recruitment_candidates"') && str_contains($query->sql, '"anonymized_at"');
            if (! $failed && $matches) {
                $failed = true;
                throw new RuntimeException('Injected private-retention failure.');
            }
        });
        try {
            app(PurgeExpiredRecruitmentCandidates::class)->handle();
            self::fail('Private detail and its audit redaction must commit together.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected private-retention failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        self::assertSame(1, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        self::assertNull($candidate->fresh()?->reentry_reason);
        self::assertSame(['retention_redacted' => true], AuditEvent::query()->where('event', 'recruitment.reentry_control_changed')->sole()->metadata);
    }

    /** @return iterable<string,array{bool}> */
    public static function competingOrders(): iterable
    {
        yield 'purge first' => [true];
        yield 'reentry first' => [false];
    }

    #[DataProvider('competingOrders')]
    public function test_reentry_and_purge_serialize_private_details_in_both_orders(bool $purgeFirst): void
    {
        [$actorId, $allianceId, $candidate] = $this->fixture();
        $this->travel(91)->days();
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.private_retention_writer', array_replace(DB::connection()->getConfig(), ['name' => 'private_retention_writer']));
        DB::connection('private_retention_writer')->statement("SET lock_timeout = '100ms'");
        $write = static fn () => app(SetRecruitmentReentryControl::class)->handle($actorId, $allianceId, (string) $candidate->id, RecruitmentReentryControl::DoNotInvite, 'Newest private reason');
        $purge = static fn () => app(PurgeExpiredRecruitmentCandidates::class)->handle();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $candidate, $purgeFirst, $write, $purge, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "recruitment_candidates"') || ! str_contains($query->sql, 'for update') || ! in_array((string) $candidate->id, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('private_retention_writer');
            try {
                try {
                    $purgeFirst ? $write() : $purge();
                    self::fail('Private state must share the candidate retention barrier.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $purgeFirst ? $purge() : $write();
            self::assertTrue($attempted);
            if (! $purgeFirst) {
                self::assertSame(1, $purge());
            }
            foreach (AuditEvent::query()->where('event', 'recruitment.reentry_control_changed')->get() as $audit) {
                self::assertSame(['retention_redacted' => true], $audit->metadata);
            }
            self::assertNull($candidate->fresh()?->reentry_reason);
            $before = $this->state();
            try {
                $write();
                self::fail('A completed purge cannot acquire new private review state.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('candidate', $exception->errors());
            }
            self::assertSame($before, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('private_retention_writer');
        }
    }

    public function test_audit_redaction_is_limited_to_the_current_subject_event_type_and_alliance(): void
    {
        [, $allianceId, $candidate] = $this->fixture();
        $factory = app(ScenarioFactory::class);
        $foreign = $factory->alliance($factory->player($factory->account()->userId, 59292));
        $other = RecruitmentCandidate::query()->create(['alliance_id' => $allianceId, 'full_name' => 'Other candidate', 'email' => 'other@example.test', 'stage' => RecruitmentStage::New, 'submitted_at' => now()]);
        $base = ['alliance_id' => $allianceId, 'event' => 'recruitment.reentry_control_changed', 'subject_type' => $candidate->getMorphClass(), 'subject_id' => (string) $candidate->id, 'metadata' => ['unrelated' => 'Keep this metadata'], 'created_at' => now()];
        $others = [];
        foreach ([['alliance_id' => $foreign->allianceId], ['subject_type' => User::class], ['subject_id' => (string) $other->id], ['event' => 'recruitment.candidate.converted']] as $difference) {
            $others[] = AuditEvent::query()->create(array_replace($base, $difference));
        }
        $this->travel(91)->days();
        self::assertSame(1, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        foreach ($others as $audit) {
            self::assertSame(['unrelated' => 'Keep this metadata'], $audit->refresh()->metadata);
        }
        self::assertSame(['retention_redacted' => true], AuditEvent::query()->where('alliance_id', $allianceId)->where('event', 'recruitment.reentry_control_changed')->where('subject_type', $candidate->getMorphClass())->where('subject_id', $candidate->id)->sole()->metadata);
    }

    public function test_application_source_and_candidate_tag_audits_follow_candidate_retention(): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59292);
        $alliance = $factory->alliance($owner);
        app(ConfigureRecruitmentSettings::class)->handle($owner->playerId, $alliance->allianceId, RecruitmentApplicationMode::Public, 'Apply', null, 90, true, false);
        $candidateId = app(SubmitRecruitmentApplication::class)->handle($alliance->allianceId, 'Applicant', 'applicant@example.test', [], source: 'Private source detail');
        app(TagRecruitmentCandidate::class)->handle($owner->playerId, $alliance->allianceId, $candidateId, 'Private applicant label');
        self::assertSame('Private source detail', AuditEvent::query()->where('event', 'recruitment.application.submitted')->sole()->metadata['source']);
        self::assertSame('Private applicant label', AuditEvent::query()->where('event', 'recruitment.candidate.tagged')->sole()->metadata['tag']);
        $delivery = OutboxMessage::query()->where('event_type', 'recruitment.application.submitted')->sole();
        self::assertTrue($delivery->payload['has_source']);
        self::assertArrayNotHasKey('source', $delivery->payload);
        app(ChangeRecruitmentStage::class)->handle($owner->playerId, $alliance->allianceId, $candidateId, RecruitmentStage::Declined);
        $this->travel(91)->days();
        self::assertSame(1, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        foreach (['recruitment.application.submitted', 'recruitment.candidate.tagged'] as $event) {
            self::assertSame(['retention_redacted' => true], AuditEvent::query()->where('event', $event)->sole()->metadata);
        }
        self::assertNull(RecruitmentCandidate::query()->findOrFail($candidateId)->source);
        self::assertSame(0, DB::table('recruitment_candidate_tags')->where('candidate_id', $candidateId)->count());
    }

    /** @return array{string,string,RecruitmentCandidate,User} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $owner = $factory->player((int) $user->id, 59292);
        $alliance = $factory->alliance($owner);
        $target = $factory->unclaimedPlayer(59292);
        $factory->roster($owner, $alliance, $target);
        $candidate = RecruitmentCandidate::query()->create([
            'alliance_id' => $alliance->allianceId, 'full_name' => 'Private applicant', 'email' => 'private@example.test',
            'source' => 'Application source detail', 'stage' => RecruitmentStage::Accepted, 'submitted_at' => now(), 'accepted_at' => now(),
        ]);
        app(ConvertAcceptedRecruitmentCandidate::class)->handle($owner->playerId, $alliance->allianceId, (string) $candidate->id, $target->playerId);
        app(ChangeRecruitmentStage::class)->handle($owner->playerId, $alliance->allianceId, (string) $candidate->id, RecruitmentStage::Declined, 'Private stage reason');
        app(SetRecruitmentReentryControl::class)->handle($owner->playerId, $alliance->allianceId, (string) $candidate->id, RecruitmentReentryControl::ReviewRequired, 'Private applicant review', now()->addMonth()->toIso8601String());

        return [$owner->playerId, $alliance->allianceId, $candidate, $user];
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = $this->handoffState();
        foreach (['recruitment_candidates', 'recruitment_stage_history', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }

    /** @return array<string,string> */
    private function handoffState(): array
    {
        $state = [];
        foreach (['players', 'invitations', 'alliance_memberships', 'alliance_roster_entries'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
