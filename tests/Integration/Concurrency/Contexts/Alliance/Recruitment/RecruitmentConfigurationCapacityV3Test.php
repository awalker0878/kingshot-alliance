<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Alliance\Recruitment;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\Alliance\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Contexts\Alliance\Recruitment\Actions\ConvertAcceptedRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Actions\SetRecruitmentOnboardingItemActive;
use App\Contexts\Alliance\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Contexts\Alliance\Recruitment\Actions\UpdateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentConfigurationCapacityV3Test extends TestCase
{
    use DatabaseMigrations;

    private int $itemNumber = 0;

    /** @return iterable<string,array{string}> */
    public static function kinds(): iterable
    {
        yield 'questions' => ['question'];
        yield 'onboarding items' => ['onboarding'];
    }

    #[DataProvider('kinds')]
    public function test_active_capacity_rejects_creation_and_reactivation_atomically_and_deactivation_frees_room(string $kind): void
    {
        $fixture = $this->fixture();
        $ids = $this->fill($kind, $fixture, 30);
        $inactive = $this->create($kind, $fixture, false);
        $before = $this->state();
        $this->rejects(fn () => $this->create($kind, $fixture), 'active');
        self::assertSame($before, $this->state());
        $this->rejects(fn () => $this->activate($kind, $fixture, $inactive, true), 'active');
        self::assertSame($before, $this->state());
        $this->activate($kind, $fixture, $ids[0], true);
        self::assertSame(30, DB::table($this->table($kind))->where('is_active', true)->count());
        $this->activate($kind, $fixture, $ids[0], false);
        $this->activate($kind, $fixture, $inactive, true);
        self::assertSame(30, DB::table($this->table($kind))->where('is_active', true)->count());
        self::assertFalse((bool) DB::table($this->table($kind))->where('id', $ids[0])->value('is_active'));
        $this->fill($kind, $fixture, 31, false);
        self::assertSame(62, DB::table($this->table($kind))->count());
    }

    public function test_public_form_and_intake_preserve_all_thirty_required_answers_and_reject_stale_or_excess_keys(): void
    {
        $fixture = $this->fixture();
        $ids = $this->fill('question', $fixture, 30);
        $answers = array_fill_keys($ids, 'Complete answer');
        $this->get('/alliances/'.$fixture['slug'].'/apply')->assertOk()->assertInertia(static fn (Assert $page) => $page->has('questions', 30));
        $before = $this->state();
        $missing = $answers;
        unset($missing[$ids[29]]);
        $this->rejects(fn () => $this->submit($fixture, $missing), 'answers.'.$ids[29]);
        $this->rejects(fn () => $this->submit($fixture, $answers + ['obsolete-question' => 'Stale']), 'answers');
        self::assertSame($before, $this->state());
        $candidateId = $this->submit($fixture, $answers);
        self::assertSame(30, DB::table('recruitment_answers')->where('candidate_id', $candidateId)->count());
        $this->activate('question', $fixture, $ids[0], false);
        $this->rejects(fn () => $this->submit($fixture, [$ids[0] => 'Removed question'], 'stale@example.test'), 'answers');
        self::assertSame(30, DB::table('recruitment_answers')->where('candidate_id', $candidateId)->count());
    }

    public function test_conversion_snapshots_all_active_onboarding_once_and_later_deactivation_preserves_assignments(): void
    {
        $fixture = $this->fixture();
        $ids = $this->fill('onboarding', $fixture, 30);
        $factory = app(ScenarioFactory::class);
        $owner = app(PlayerReferenceQuery::class)->findOwnedByUser($fixture['userId'], $fixture['actorId']);
        self::assertNotNull($owner);
        $alliance = app(AllianceReferenceQuery::class)->require($fixture['allianceId']);
        $account = $factory->account();
        $target = $factory->player($account->userId, 59304);
        $factory->roster($owner, $alliance, $target);
        $candidate = RecruitmentCandidate::query()->create([
            'alliance_id' => $fixture['allianceId'], 'full_name' => 'Complete onboarding', 'email' => $account->email,
            'stage' => RecruitmentStage::Accepted, 'submitted_at' => now(), 'accepted_at' => now(),
        ]);
        $convert = static fn () => app(ConvertAcceptedRecruitmentCandidate::class)->handle($fixture['actorId'], $fixture['allianceId'], (string) $candidate->id, $target->playerId);
        $first = $convert();
        self::assertSame(30, DB::table('recruitment_candidate_onboarding')->where('candidate_id', $candidate->id)->count());
        $this->activate('onboarding', $fixture, $ids[0], false);
        $replacement = $this->create('onboarding', $fixture);
        self::assertSame($first->invitationId, $convert()->invitationId);
        self::assertSame(30, DB::table('recruitment_candidate_onboarding')->where('candidate_id', $candidate->id)->count());
        self::assertTrue(DB::table('recruitment_candidate_onboarding')->where('candidate_id', $candidate->id)->where('onboarding_item_id', $ids[0])->exists());
        self::assertFalse(DB::table('recruitment_candidate_onboarding')->where('candidate_id', $candidate->id)->where('onboarding_item_id', $replacement)->exists());
    }

    public function test_http_onboarding_toggle_preserves_owner_capacity_and_validation_feedback(): void
    {
        $fixture = $this->fixture();
        $ids = $this->fill('onboarding', $fixture, 30);
        $inactive = $this->create('onboarding', $fixture, false);
        $base = '/alliance/recruitment/onboarding-items';
        $before = $this->state();
        $this->postJson($base, ['name' => 'Over capacity', 'position' => 0, 'required' => true, 'active' => true])->assertUnprocessable()->assertJsonValidationErrors('active');
        $this->patchJson($base.'/'.$inactive, ['active' => true])->assertUnprocessable()->assertJsonValidationErrors('active');
        $this->patchJson($base.'/'.$ids[0], ['active' => []])->assertUnprocessable()->assertJsonValidationErrors('active');
        self::assertSame($before, $this->state());
        $this->patchJson($base.'/'.$ids[0], ['active' => false])->assertRedirect();
        $this->patchJson($base.'/'.$inactive, ['active' => true])->assertRedirect();
        self::assertSame(30, DB::table('recruitment_onboarding_items')->where('is_active', true)->count());
    }

    public function test_onboarding_toggle_scopes_the_item_rechecks_authority_and_is_idempotent(): void
    {
        $fixture = $this->fixture();
        $item = $this->create('onboarding', $fixture);
        $factory = app(ScenarioFactory::class);
        $otherOwner = $factory->player($factory->account()->userId, 59304);
        $other = $factory->alliance($otherOwner);
        $foreign = app(CreateRecruitmentOnboardingItem::class)->handle($otherOwner->playerId, $other->allianceId, 'Other Alliance task');
        $before = $this->state();
        try {
            app(SetRecruitmentOnboardingItemActive::class)->handle($fixture['actorId'], $fixture['allianceId'], $foreign, false);
            self::fail('An item from another Alliance is outside the owner scope.');
        } catch (ModelNotFoundException) {
            self::assertSame($before, $this->state());
        }
        $this->activate('onboarding', $fixture, $item, false);
        $beforeRetry = $this->state();
        $this->activate('onboarding', $fixture, $item, false);
        self::assertSame($beforeRetry, $this->state());
        DB::table('alliance_memberships')->where('alliance_id', $fixture['allianceId'])->where('player_id', $fixture['actorId'])->update(['status' => 'suspended']);
        $this->expectException(AuthorizationException::class);
        $this->activate('onboarding', $fixture, $item, true);
    }

    /** @return iterable<string,array{bool}> */
    public static function intakeOrders(): iterable
    {
        yield 'intake holds the complete form' => [true];
        yield 'configuration changes the form first' => [false];
    }

    #[DataProvider('intakeOrders')]
    public function test_configuration_and_public_intake_serialize_complete_required_question_sets(bool $intakeFirst): void
    {
        $fixture = $this->fixture();
        $id = $this->create('question', $fixture);
        $submit = fn () => $this->submit($fixture, [$id => 'Complete answer']);
        $change = fn () => $this->activate('question', $fixture, $id, false);
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $fixture, $submit, $change, $intakeFirst, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliances"') || ! str_contains($query->sql, $intakeFirst ? 'for share' : 'for update') || ! in_array($fixture['allianceId'], $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('configuration_writer');
            try {
                try {
                    $intakeFirst ? $change() : $submit();
                    self::fail('Intake must retain one complete configuration through commit.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $intakeFirst ? $submit() : $change();
            self::assertTrue($attempted);
            if ($intakeFirst) {
                $change();
                self::assertSame(1, DB::table('recruitment_answers')->count());
            } else {
                $this->rejects($submit, 'answers');
                self::assertSame(0, DB::table('recruitment_candidates')->count());
            }
        } finally {
            DB::purge('configuration_writer');
        }
    }

    /** @return iterable<string,array{string,bool}> */
    public static function competingOrders(): iterable
    {
        foreach (['question', 'onboarding'] as $kind) {
            yield $kind.' create first' => [$kind, true];
            yield $kind.' activation first' => [$kind, false];
        }
    }

    #[DataProvider('competingOrders')]
    public function test_competing_creation_and_activation_serialize_the_last_available_slot(string $kind, bool $createFirst): void
    {
        $fixture = $this->fixture();
        $this->fill($kind, $fixture, 29);
        $inactive = $this->create($kind, $fixture, false);
        $create = fn () => $this->create($kind, $fixture);
        $activate = fn () => $this->activate($kind, $fixture, $inactive, true);
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($fixture, $primary, $create, $activate, $createFirst, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliances"') || ! str_contains($query->sql, 'for update') || ! in_array($fixture['allianceId'], $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('configuration_writer');
            try {
                try {
                    $createFirst ? $activate() : $create();
                    self::fail('The last active slot must have one serialized winner.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $createFirst ? $create() : $activate();
            self::assertTrue($attempted);
            $this->rejects($createFirst ? $activate : $create, 'active');
            self::assertSame(30, DB::table($this->table($kind))->where('is_active', true)->count());
        } finally {
            DB::purge('configuration_writer');
        }
    }

    #[DataProvider('kinds')]
    public function test_late_outbox_failure_restores_capacity_and_retry_can_take_the_slot(string $kind): void
    {
        $fixture = $this->fixture();
        $this->fill($kind, $fixture, 29);
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected configuration delivery failure.');
            }
        });
        try {
            $this->create($kind, $fixture);
            self::fail('Configuration and capacity must roll back together.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected configuration delivery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        $this->create($kind, $fixture);
        self::assertSame(30, DB::table($this->table($kind))->where('is_active', true)->count());
    }

    /** @return array{actorId:string,allianceId:string,slug:string,userId:int} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $owner = $factory->player((int) $user->id, 59304);
        $alliance = $factory->alliance($owner);
        app(ConfigureRecruitmentSettings::class)->handle($owner->playerId, $alliance->allianceId, RecruitmentApplicationMode::Public, 'Complete form', null, 90, true, false);
        $facts = app(PlayerIdentityContextQuery::class)->forPlayers([$owner->playerId])[$owner->playerId] ?? null;
        $kingdom = app(KingdomAuthorityFactsQuery::class)->findCurrent($owner->playerId, $owner->kingdomId)->permissionKeysObservedAtRead ?? [];
        $this->actingAs($user)->withSession([
            (string) config('game_world.active_player_session_key') => $owner->playerId,
            'accounts.recent_authentication_at' => now()->timestamp,
        ])->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, app(PlayerAuthorityContextVersion::class)->issue($owner, $facts, $kingdom));

        return ['actorId' => $owner->playerId, 'allianceId' => $alliance->allianceId, 'slug' => $alliance->slug, 'userId' => (int) $user->id];
    }

    /** @param array{actorId:string,allianceId:string,slug:string,userId:int} $fixture */
    private function create(string $kind, array $fixture, bool $active = true): string
    {
        return $kind === 'question'
            ? app(CreateRecruitmentQuestion::class)->handle($fixture['actorId'], $fixture['allianceId'], 'Complete required question', RecruitmentQuestionType::ShortText, true, isActive: $active)
            : app(CreateRecruitmentOnboardingItem::class)->handle($fixture['actorId'], $fixture['allianceId'], 'Complete onboarding task '.++$this->itemNumber, isActive: $active);
    }

    /** @param array{actorId:string,allianceId:string,slug:string,userId:int} $fixture */
    private function activate(string $kind, array $fixture, string $id, bool $active): void
    {
        if ($kind === 'question') {
            app(UpdateRecruitmentQuestion::class)->handle($fixture['actorId'], $fixture['allianceId'], $id, 'Edited complete question', RecruitmentQuestionType::ShortText, true, 0, isActive: $active);
        } else {
            app(SetRecruitmentOnboardingItemActive::class)->handle($fixture['actorId'], $fixture['allianceId'], $id, $active);
        }
    }

    /**
     * @param  array{actorId:string,allianceId:string,slug:string,userId:int}  $fixture
     * @return list<string>
     */
    private function fill(string $kind, array $fixture, int $count, bool $active = true): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->create($kind, $fixture, $active);
        }

        return $ids;
    }

    /**
     * @param  array{actorId:string,allianceId:string,slug:string,userId:int}  $fixture
     * @param  array<string,string>  $answers
     */
    private function submit(array $fixture, array $answers, string $email = 'complete@example.test'): string
    {
        return app(SubmitRecruitmentApplication::class)->handle($fixture['allianceId'], 'Complete applicant', $email, $answers);
    }

    private function rejects(callable $operation, string $field): void
    {
        try {
            $operation();
            self::fail('The invalid operation must have no effects.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($field, $exception->errors());
        }
    }

    private function table(string $kind): string
    {
        return $kind === 'question' ? 'recruitment_questions' : 'recruitment_onboarding_items';
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.configuration_writer', array_replace(DB::connection()->getConfig(), ['name' => 'configuration_writer']));
        DB::connection('configuration_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['recruitment_questions', 'recruitment_onboarding_items', 'recruitment_candidates', 'recruitment_answers', 'recruitment_candidate_onboarding', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
