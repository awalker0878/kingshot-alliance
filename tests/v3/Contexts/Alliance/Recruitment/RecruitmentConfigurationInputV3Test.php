<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Recruitment;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\Alliance\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentDecisionTemplate;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Actions\IssueRecruitmentApplicationInvite;
use App\Contexts\Alliance\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Contexts\Alliance\Recruitment\Actions\UpdateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentInput;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RecruitmentConfigurationInputV3Test extends TestCase
{
    use RefreshDatabase;

    private int $publicRequestNumber = 0;

    /** @return iterable<string,array{string,bool}> */
    public static function entryPoints(): iterable
    {
        foreach (['settings', 'question-create', 'question-update', 'template', 'onboarding', 'invite', 'application'] as $operation) {
            yield $operation.' owner' => [$operation, false];
            yield $operation.' HTTP' => [$operation, true];
        }
    }

    #[DataProvider('entryPoints')]
    public function test_configuration_and_identity_bounds_are_atomic_at_both_entry_points(string $operation, bool $http): void
    {
        $fixture = $this->fixture();
        $valid = $this->validInput($operation, $fixture['user']);
        foreach ($this->invalidInputs($operation, $valid) as [$data, $field]) {
            $before = $this->state();
            if ($http) {
                [$method, $url] = $this->endpoint($operation, $fixture['slug']);
                if ($operation === 'application') {
                    $this->usePublicClient($fixture['slug']);
                }
                $this->json($method, $url, $data + ($operation === 'question-update' ? ['question_id' => $fixture['questionId']] : []))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            } else {
                try {
                    $this->perform($operation, $fixture, $data);
                    self::fail('Invalid '.$field.' must be rejected before owner effects.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey($field, $exception->errors());
                }
            }
            self::assertSame($before, $this->state());
        }
        $beforeAudit = DB::table('audit_events')->count();
        $beforeOutbox = DB::table('outbox_messages')->count();
        if ($http) {
            [$method, $url] = $this->endpoint($operation, $fixture['slug']);
            if ($operation === 'application') {
                $this->usePublicClient($fixture['slug']);
            }
            $this->json($method, $url, $valid + ($operation === 'question-update' ? ['question_id' => $fixture['questionId']] : []))->assertRedirect();
        } else {
            $this->perform($operation, $fixture, $valid);
        }
        self::assertSame($beforeAudit + 1, DB::table('audit_events')->count());
        self::assertSame($beforeOutbox + 1, DB::table('outbox_messages')->count());
        $this->assertStored($operation, $fixture, $valid);
    }

    public function test_management_and_public_pages_publish_the_authoritative_input_limits(): void
    {
        $fixture = $this->fixture();
        foreach (['/alliance/recruitment', '/alliances/'.$fixture['slug'].'/apply'] as $url) {
            $this->get($url)->assertOk()->assertInertia(static function (Assert $page): void {
                $page->where('inputLimits', RecruitmentInput::LIMITS);
            });
        }
    }

    /** @return array<string,mixed> */
    private function validInput(string $operation, User $user): array
    {
        $text = static fn (int $length): string => str_repeat('界', $length);

        return match ($operation) {
            'settings' => ['mode' => 'public', 'title' => $text(160), 'introduction' => $text(5000), 'retention_days' => 3650, 'open' => true, 'listed' => false],
            'question-create', 'question-update' => ['prompt' => $text(240), 'help_text' => $text(2000), 'type' => 'multi_select', 'required' => true, 'position' => 65535, 'active' => true, 'options' => array_map(static fn (int $i): string => str_pad((string) $i, 2, '0', STR_PAD_LEFT).$text(158), range(1, 30))],
            'template' => ['name' => $text(120), 'decision_stage' => 'accepted', 'subject' => $text(200), 'body' => $text(10000), 'active' => true],
            'onboarding' => ['name' => $text(160), 'description' => $text(5000), 'position' => 65535, 'required' => true, 'active' => true],
            'invite' => ['email' => '  '.strtoupper((string) $user->email).'  ', 'ttl_hours' => 720],
            'application' => ['full_name' => $text(160), 'email' => (string) $user->email, 'contact_handle' => $text(160), 'source' => $text(120), 'answers' => []],
        };
    }

    /**
     * @param  array<string,mixed>  $valid
     * @return iterable<array{array<string,mixed>,string}>
     */
    private function invalidInputs(string $operation, array $valid): iterable
    {
        $limits = match ($operation) {
            'settings' => ['title' => 160, 'introduction' => 5000],
            'question-create', 'question-update' => ['prompt' => 240, 'help_text' => 2000],
            'template' => ['name' => 120, 'subject' => 200, 'body' => 10000],
            'onboarding' => ['name' => 160, 'description' => 5000],
            'application' => ['full_name' => 160, 'contact_handle' => 160, 'source' => 120],
            'invite' => [],
        };
        foreach ($limits as $field => $limit) {
            yield [array_replace($valid, [$field => str_repeat('界', $limit + 1)]), $field];
        }
        $required = match ($operation) {
            'settings' => ['title'],
            'question-create', 'question-update' => ['prompt'],
            'template' => ['name', 'subject', 'body'],
            'onboarding' => ['name'],
            'application' => ['full_name', 'email'],
            'invite' => [],
        };
        foreach ($required as $field) {
            yield [array_replace($valid, [$field => '']), $field];
        }
        if (in_array($operation, ['question-create', 'question-update', 'onboarding'], true)) {
            foreach ([-1, 65536] as $position) {
                yield [array_replace($valid, ['position' => $position]), 'position'];
            }
        }
        if (str_starts_with($operation, 'question-')) {
            foreach ([[], array_fill(0, 31, 'Choice'), ['named' => 'Choice']] as $options) {
                yield [array_replace($valid, ['options' => $options]), 'options'];
            }
            foreach ([[str_repeat('界', 161)], [['nested']]] as $options) {
                yield [array_replace($valid, ['options' => $options]), 'options.0'];
            }
        }
        if (in_array($operation, ['invite', 'application'], true)) {
            foreach (['invalid email', str_repeat('a', 321).'@example.test'] as $email) {
                yield [array_replace($valid, ['email' => $email]), 'email'];
            }
        }
        if ($operation === 'settings' || $operation === 'invite') {
            $field = $operation === 'settings' ? 'retention_days' : 'ttl_hours';
            foreach ([0, $operation === 'settings' ? 3651 : 721] as $value) {
                yield [array_replace($valid, [$field => $value]), $field];
            }
        }
    }

    /**
     * @param  array{actorId:string,allianceId:string,slug:string,user:User,questionId:string}  $fixture
     * @param  array<string,mixed>  $data
     */
    private function perform(string $operation, array $fixture, array $data): void
    {
        $actorId = $fixture['actorId'];
        $allianceId = $fixture['allianceId'];
        match ($operation) {
            'settings' => app(ConfigureRecruitmentSettings::class)->handle($actorId, $allianceId, RecruitmentApplicationMode::Public, $data['title'], $data['introduction'], $data['retention_days'], $data['open'], $data['listed']),
            'question-create' => app(CreateRecruitmentQuestion::class)->handle($actorId, $allianceId, $data['prompt'], RecruitmentQuestionType::MultiSelect, $data['required'], $data['position'], $data['help_text'], $data['options'], $data['active']),
            'question-update' => app(UpdateRecruitmentQuestion::class)->handle($actorId, $allianceId, $fixture['questionId'], $data['prompt'], RecruitmentQuestionType::MultiSelect, $data['required'], $data['position'], $data['help_text'], $data['options'], $data['active']),
            'template' => app(CreateRecruitmentDecisionTemplate::class)->handle($actorId, $allianceId, $data['name'], RecruitmentStage::Accepted, $data['subject'], $data['body'], $data['active']),
            'onboarding' => app(CreateRecruitmentOnboardingItem::class)->handle($actorId, $allianceId, $data['name'], $data['description'], $data['position'], $data['required'], $data['active']),
            'invite' => app(IssueRecruitmentApplicationInvite::class)->handle($actorId, $allianceId, $data['email'], $data['ttl_hours']),
            'application' => app(SubmitRecruitmentApplication::class)->handle($allianceId, $data['full_name'], $data['email'], $data['answers'], $data['contact_handle'], $data['source']),
        };
    }

    /** @return array{string,string} */
    private function endpoint(string $operation, string $slug): array
    {
        return match ($operation) {
            'settings' => ['PATCH', '/alliance/recruitment/settings'],
            'question-create', 'question-update' => ['POST', '/alliance/recruitment/questions'],
            'template' => ['POST', '/alliance/recruitment/decision-templates'],
            'onboarding' => ['POST', '/alliance/recruitment/onboarding-items'],
            'invite' => ['POST', '/alliance/recruitment/application-invites'],
            'application' => ['POST', '/alliances/'.$slug.'/apply'],
        };
    }

    /**
     * @param  array{actorId:string,allianceId:string,slug:string,user:User,questionId:string}  $fixture
     * @param  array<string,mixed>  $data
     */
    private function assertStored(string $operation, array $fixture, array $data): void
    {
        [$table, $fields] = match ($operation) {
            'settings' => ['recruitment_settings', ['title' => 'title', 'introduction' => 'introduction', 'retention_days' => 'retention_unsuccessful_days']],
            'question-create', 'question-update' => ['recruitment_questions', ['prompt' => 'prompt', 'help_text' => 'help_text', 'position' => 'position']],
            'template' => ['recruitment_decision_templates', ['name' => 'name', 'subject' => 'subject', 'body' => 'body']],
            'onboarding' => ['recruitment_onboarding_items', ['name' => 'name', 'description' => 'description', 'position' => 'position']],
            'invite' => ['recruitment_application_invites', []],
            'application' => ['recruitment_candidates', ['full_name' => 'full_name', 'contact_handle' => 'contact_handle', 'source' => 'source']],
        };
        $row = DB::table($table)->where('alliance_id', $fixture['allianceId'])->orderByDesc('id')->first();
        self::assertNotNull($row);
        foreach ($fields as $input => $column) {
            self::assertSame($data[$input], $row->{$column});
        }
        if (str_starts_with($operation, 'question-')) {
            self::assertSame($data['options'], json_decode($row->options, true, flags: JSON_THROW_ON_ERROR));
        }
        if (in_array($operation, ['invite', 'application'], true)) {
            self::assertSame((string) $fixture['user']->email, $row->email);
        }
    }

    /** @return array{actorId:string,allianceId:string,slug:string,user:User,questionId:string} */
    private function fixture(): array
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $owner = $factory->player((int) $user->id, 59297);
        $alliance = $factory->alliance($owner);
        app(ConfigureRecruitmentSettings::class)->handle($owner->playerId, $alliance->allianceId, RecruitmentApplicationMode::Public, 'Apply', null, 90, true, false);
        $questionId = app(CreateRecruitmentQuestion::class)->handle($owner->playerId, $alliance->allianceId, 'Existing question', RecruitmentQuestionType::ShortText, false);
        $allianceFacts = app(PlayerIdentityContextQuery::class)->forPlayers([$owner->playerId])[$owner->playerId] ?? null;
        $kingdom = app(KingdomAuthorityFactsQuery::class)->findCurrent($owner->playerId, $owner->kingdomId)->permissionKeysObservedAtRead ?? [];
        $version = app(PlayerAuthorityContextVersion::class)->issue($owner, $allianceFacts, $kingdom);
        $this->actingAs($user)->withSession([
            (string) config('game_world.active_player_session_key') => $owner->playerId,
            'accounts.recent_authentication_at' => now()->timestamp,
        ])->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $version);

        return ['actorId' => $owner->playerId, 'allianceId' => $alliance->allianceId, 'slug' => $alliance->slug, 'user' => $user, 'questionId' => $questionId];
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['recruitment_settings', 'recruitment_questions', 'recruitment_decision_templates', 'recruitment_onboarding_items', 'recruitment_application_invites', 'recruitment_candidates', 'recruitment_answers', 'audit_events', 'outbox_messages'] as $table) {
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
