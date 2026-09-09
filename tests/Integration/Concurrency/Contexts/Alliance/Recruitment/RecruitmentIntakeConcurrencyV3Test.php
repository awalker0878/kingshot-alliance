<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Alliance\Recruitment;

use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Profile\Actions\PromotePendingAccountEmail;
use App\Contexts\Alliance\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Actions\IssueRecruitmentApplicationInvite;
use App\Contexts\Alliance\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentIntakeConcurrencyV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{string,bool}> */
    public static function lifecycleOrders(): iterable
    {
        foreach (['Kingdom archival', 'account email', 'account finalization', 'settings closure'] as $change) {
            yield $change.' intake first' => [$change, true];
            yield $change.' lifecycle first' => [$change, false];
        }
    }

    #[DataProvider('lifecycleOrders')]
    public function test_intake_revalidates_current_lifecycle_in_both_commit_orders(string $change, bool $intakeFirst): void
    {
        $fixture = $this->fixture();
        $account = app(ScenarioFactory::class)->account();
        $newEmail = 'changed-'.$account->userId.'@example.test';
        if ($change === 'account email') {
            User::query()->whereKey($account->userId)->update(['pending_email' => $newEmail, 'pending_email_requested_at' => now()]);
        }
        $submit = static fn () => app(SubmitRecruitmentApplication::class)->handle($fixture['allianceId'], 'Current applicant', $account->email, [], applicantUserId: $account->userId);
        $mutate = static function () use ($fixture, $account, $newEmail, $change): void {
            match ($change) {
                'Kingdom archival' => app(ArchiveKingdom::class)->handle($fixture['kingdomId']),
                'account email' => app(PromotePendingAccountEmail::class)->handle($account->userId, sha1($newEmail)),
                'account finalization' => app(AnonymizeAccount::class)->handle($account->userId, (string) Str::ulid()),
                'settings closure' => app(ConfigureRecruitmentSettings::class)->handle($fixture['ownerId'], $fixture['allianceId'], RecruitmentApplicationMode::Public, 'Current recruitment', null, 90, false, false),
            };
        };
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $fixture, $account, $change, $intakeFirst, $submit, $mutate, &$attempted): void {
            $barrier = match ($change) {
                'Kingdom archival' => str_starts_with($query->sql, 'select * from "kingdoms"') && str_contains($query->sql, $intakeFirst ? 'for share' : 'for update') && in_array($fixture['kingdomId'], $query->bindings, true),
                'account email', 'account finalization' => str_starts_with($query->sql, 'select * from "users"') && preg_match('/for (?:no key )?update/', $query->sql) === 1 && in_array((string) $account->userId, array_map('strval', $query->bindings), true),
                'settings closure' => str_starts_with($query->sql, 'select * from "alliances"') && str_contains($query->sql, $intakeFirst ? 'for share' : 'for update'),
            };
            if ($attempted || $query->connectionName !== $primary || ! $barrier) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('intake_writer');
            try {
                try {
                    $intakeFirst ? $mutate() : $submit();
                    self::fail('The competing lifecycle owner must serialize with intake.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $intakeFirst ? $submit() : $mutate();
            self::assertTrue($attempted);
            if ($intakeFirst) {
                self::assertSame($account->email, RecruitmentCandidate::query()->sole()->email);
                self::assertSame($account->userId, RecruitmentCandidate::query()->sole()->applicant_user_id);
                $mutate();
            }
            $beforeRetry = $this->state();
            try {
                $submit();
                self::fail('Committed lifecycle changes must reject stale applicant intent.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey(match ($change) {
                    'account email' => 'email',
                    'account finalization' => 'account',
                    default => 'application',
                }, $exception->errors());
            }
            self::assertSame($beforeRetry, $this->state());
            self::assertSame($intakeFirst ? 1 : 0, RecruitmentCandidate::query()->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('intake_writer');
        }
    }

    public function test_finalized_identity_cannot_submit_even_using_its_current_anonymized_email(): void
    {
        $fixture = $this->fixture();
        $account = app(ScenarioFactory::class)->account();
        app(AnonymizeAccount::class)->handle($account->userId, (string) Str::ulid());
        $email = (string) User::query()->findOrFail($account->userId)->email;
        $before = $this->state();
        try {
            app(SubmitRecruitmentApplication::class)->handle($fixture['allianceId'], 'Rejected terminal applicant', $email, [], applicantUserId: $account->userId);
            self::fail('Current account lifecycle is authoritative independently of email equality.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('account', $exception->errors());
            self::assertSame($before, $this->state());
        }
    }

    public function test_anonymous_public_intake_preserves_duplicate_email_policy(): void
    {
        $fixture = $this->fixture();
        $id = app(SubmitRecruitmentApplication::class)->handle($fixture['allianceId'], 'Anonymous applicant', '  Applicant@Example.test  ', []);
        $candidate = RecruitmentCandidate::query()->findOrFail($id);
        self::assertNull($candidate->applicant_user_id);
        self::assertSame('applicant@example.test', $candidate->email);
        $beforeRetry = $this->state();
        try {
            app(SubmitRecruitmentApplication::class)->handle($fixture['allianceId'], 'Duplicate applicant', 'applicant@example.test', []);
            self::fail('Anonymous intake must retain current active-email duplicate protection.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('email', $exception->errors());
        }
        self::assertSame($beforeRetry, $this->state());
    }

    /** @return iterable<string,array{bool}> */
    public static function modes(): iterable
    {
        yield 'public application' => [false];
        yield 'invitation application' => [true];
    }

    #[DataProvider('modes')]
    public function test_late_delivery_failure_rolls_back_candidate_answers_and_invitation_consumption(bool $invited): void
    {
        $fixture = $this->fixture($invited ? RecruitmentApplicationMode::Invitation : RecruitmentApplicationMode::Public);
        $questionId = app(CreateRecruitmentQuestion::class)->handle(
            $fixture['ownerId'], $fixture['allianceId'], 'Recruitment answer', RecruitmentQuestionType::ShortText, true,
        );
        $invitation = $invited ? app(IssueRecruitmentApplicationInvite::class)->handle($fixture['ownerId'], $fixture['allianceId']) : null;
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array('recruitment.application.submitted', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected recruitment intake delivery failure.');
            }
        });
        $submit = static fn () => app(SubmitRecruitmentApplication::class)->handle($fixture['allianceId'], 'Current applicant', 'applicant@example.test', [$questionId => 'Current answer'], applicationToken: $invitation?->token);
        try {
            $submit();
            self::fail('Late delivery failure must roll back the complete application.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected recruitment intake delivery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        $id = $submit();
        self::assertSame(1, DB::table('recruitment_answers')->where('candidate_id', $id)->count());
        self::assertSame(1, DB::table('recruitment_stage_history')->where('candidate_id', $id)->count());
        if ($invitation !== null) {
            self::assertNotNull($invitation->invite->fresh()?->used_at);
            $beforeReplay = $this->state();
            try {
                app(SubmitRecruitmentApplication::class)->handle($fixture['allianceId'], 'Another applicant', 'another@example.test', [$questionId => 'Other answer'], applicationToken: $invitation->token);
                self::fail('A consumed application invitation cannot admit another candidate.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('application_token', $exception->errors());
            }
            self::assertSame($beforeReplay, $this->state());
        }
    }

    /** @return array{ownerId:string,allianceId:string,kingdomId:string} */
    private function fixture(RecruitmentApplicationMode $mode = RecruitmentApplicationMode::Public): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59284);
        $alliance = $factory->alliance($owner);
        app(ConfigureRecruitmentSettings::class)->handle($owner->playerId, $alliance->allianceId, $mode, 'Current recruitment', null, 90, true, false);

        return ['ownerId' => $owner->playerId, 'allianceId' => $alliance->allianceId, 'kingdomId' => $owner->kingdomId];
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.intake_writer', array_replace(DB::connection()->getConfig(), ['name' => 'intake_writer']));
        DB::connection('intake_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['recruitment_candidates', 'recruitment_answers', 'recruitment_stage_history', 'recruitment_application_invites', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
