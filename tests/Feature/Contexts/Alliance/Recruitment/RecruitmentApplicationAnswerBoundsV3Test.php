<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\Alliance\Recruitment;

use App\Contexts\Alliance\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentApplicationAnswerBoundsV3Test extends TestCase
{
    use RefreshDatabase;

    private int $publicRequestNumber = 0;

    /** @return iterable<string,array{RecruitmentQuestionType,int,bool}> */
    public static function textBoundaries(): iterable
    {
        foreach ([false, true] as $http) {
            yield 'short '.($http ? 'HTTP' : 'owner') => [RecruitmentQuestionType::ShortText, 240, $http];
            yield 'long '.($http ? 'HTTP' : 'owner') => [RecruitmentQuestionType::LongText, 10000, $http];
        }
    }

    #[DataProvider('textBoundaries')]
    public function test_text_answers_reject_oversize_and_wrong_shape_before_any_application_effect(RecruitmentQuestionType $type, int $limit, bool $http): void
    {
        [$allianceId, $slug, $questionId] = $this->fixture($type);
        foreach ([str_repeat('界', $limit + 1), ['nested'], " \t\n "] as $value) {
            $this->reject($http, $allianceId, $slug, $questionId, $value);
        }
        $text = str_repeat('界', $limit);
        $this->submit($http, $allianceId, $slug, [$questionId => " \t".$text."\n "]);
        self::assertSame(['value' => $text], RecruitmentAnswer::query()->sole()->answer);
        self::assertSame(1, DB::table('recruitment_candidates')->count());
        self::assertSame(1, DB::table('audit_events')->where('event', 'recruitment.application.submitted')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'recruitment.application.submitted')->count());
    }

    /** @return iterable<string,array{bool}> */
    public static function entryPoints(): iterable
    {
        yield 'owner' => [false];
        yield 'HTTP' => [true];
    }

    #[DataProvider('entryPoints')]
    public function test_multiselect_accepts_the_full_unique_catalogue_and_rejects_unbounded_or_unknown_values(bool $http): void
    {
        $options = array_map(static fn (int $i): string => 'Choice '.$i, range(1, 30));
        [$allianceId, $slug, $questionId] = $this->fixture(RecruitmentQuestionType::MultiSelect, options: $options);
        foreach ([[], ['Unknown'], ['Choice 1', 'Choice 1'], array_fill(0, 31, 'Choice 1'), [['nested']], ['named' => 'Choice 1']] as $value) {
            $this->reject($http, $allianceId, $slug, $questionId, $value);
        }
        $this->submit($http, $allianceId, $slug, [$questionId => $options]);
        self::assertSame(['values' => $options], RecruitmentAnswer::query()->sole()->answer);
    }

    #[DataProvider('entryPoints')]
    public function test_optional_blank_text_and_empty_selection_remain_absent(bool $http): void
    {
        [$allianceId, $slug, $questionId, $ownerId] = $this->fixture(RecruitmentQuestionType::ShortText, required: false);
        $long = app(CreateRecruitmentQuestion::class)->handle($ownerId, $allianceId, 'Long optional', RecruitmentQuestionType::LongText, false);
        $multi = app(CreateRecruitmentQuestion::class)->handle($ownerId, $allianceId, 'Optional choices', RecruitmentQuestionType::MultiSelect, false, options: ['Choice']);
        $this->submit($http, $allianceId, $slug, [$questionId => " \t\n ", $long => null, $multi => []]);
        self::assertSame(1, DB::table('recruitment_candidates')->count());
        self::assertSame(0, RecruitmentAnswer::query()->count());
    }

    #[DataProvider('entryPoints')]
    public function test_required_checkbox_keeps_its_confirmation_semantics(bool $http): void
    {
        [$allianceId, $slug, $questionId] = $this->fixture(RecruitmentQuestionType::Checkbox);
        foreach ([false, 'true', 1] as $value) {
            $this->reject($http, $allianceId, $slug, $questionId, $value);
        }
        $this->submit($http, $allianceId, $slug, [$questionId => true]);
        self::assertSame(['value' => true], RecruitmentAnswer::query()->sole()->answer);
    }

    /**
     * @param  list<string>  $options
     * @return array{string,string,string,string}
     */
    private function fixture(RecruitmentQuestionType $type, bool $required = true, array $options = []): array
    {
        $factory = new ScenarioFactory;
        $owner = $factory->player($factory->account()->userId, 59298);
        $alliance = $factory->alliance($owner);
        app(ConfigureRecruitmentSettings::class)->handle($owner->playerId, $alliance->allianceId, RecruitmentApplicationMode::Public, 'Apply', null, 90, true, false);
        $questionId = app(CreateRecruitmentQuestion::class)->handle($owner->playerId, $alliance->allianceId, 'Question', $type, $required, options: $options);

        return [$alliance->allianceId, $alliance->slug, $questionId, $owner->playerId];
    }

    public function test_public_application_rate_limit_remains_enforced_for_repeated_requests_from_one_client(): void
    {
        [, $slug] = $this->fixture(RecruitmentQuestionType::ShortText, false);
        $this->usePublicClient($slug);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/alliances/'.$slug.'/apply', ['email' => 'throttled@example.test'])->assertUnprocessable();
        }
        $this->postJson('/alliances/'.$slug.'/apply', ['email' => 'throttled@example.test'])->assertTooManyRequests();
    }

    private function reject(bool $http, string $allianceId, string $slug, string $questionId, mixed $value): void
    {
        $before = $this->state();
        if ($http) {
            $this->usePublicClient($slug);
            $this->postJson('/alliances/'.$slug.'/apply', ['full_name' => 'Applicant', 'email' => 'applicant@example.test', 'answers' => [$questionId => $value]])
                ->assertUnprocessable()->assertJsonValidationErrors('answers.'.$questionId);
        } else {
            try {
                app(SubmitRecruitmentApplication::class)->handle($allianceId, 'Applicant', 'applicant@example.test', [$questionId => $value]);
                self::fail('Invalid answers must not create a partial application.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('answers.'.$questionId, $exception->errors());
            }
        }
        self::assertSame($before, $this->state());
    }

    /** @param array<string,mixed> $answers */
    private function submit(bool $http, string $allianceId, string $slug, array $answers): void
    {
        if ($http) {
            $this->usePublicClient($slug);
            $this->postJson('/alliances/'.$slug.'/apply', ['full_name' => 'Applicant', 'email' => 'applicant@example.test', 'answers' => $answers])->assertRedirect();
        } else {
            app(SubmitRecruitmentApplication::class)->handle($allianceId, 'Applicant', 'applicant@example.test', $answers);
        }
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

    private function usePublicClient(string $slug): void
    {
        $prefix = hash('sha256', $slug);
        $this->withServerVariables(['REMOTE_ADDR' => sprintf('2001:db8:%s:%s::%x', substr($prefix, 0, 4), substr($prefix, 4, 4), ++$this->publicRequestNumber)]);
    }
}
