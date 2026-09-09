<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Recruitment;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\Alliance\Recruitment\Actions\AddRecruitmentNote;
use App\Contexts\Alliance\Recruitment\Actions\BulkChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\ChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\MergeRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Actions\SetRecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RecruitmentTextBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string,int,string}> */
    public static function operations(): iterable
    {
        yield 'note' => ['note', 10000, 'body'];
        yield 'bulk reason' => ['bulk', 5000, 'reason'];
        yield 'stage reason' => ['stage', 5000, 'reason'];
        yield 'merge reason' => ['merge', 5000, 'reason'];
        yield 'reentry reason' => ['reentry', 5000, 'reason'];
    }

    #[DataProvider('operations')]
    public function test_owner_accepts_exact_unicode_boundary_and_rejects_overflow_before_effects(string $operation, int $limit, string $field): void
    {
        $fixture = $this->fixture();
        $before = $this->state();
        try {
            $this->perform($operation, $fixture, str_repeat('界', $limit + 1));
            self::fail('Oversized direct-owner text must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($field, $exception->errors());
        }
        self::assertSame($before, $this->state());

        $text = str_repeat('界', $limit);
        $this->perform($operation, $fixture, " \t".$text."\n ");
        $this->assertStored($operation, $fixture, $text);
        self::assertSame(1, DB::table('audit_events')->where('event', $this->event($operation))->count());
        self::assertSame($operation === 'bulk' ? 2 : 1, DB::table('outbox_messages')->where('event_type', $operation === 'bulk' ? 'recruitment.candidate.stage_changed' : $this->event($operation))->count());
    }

    #[DataProvider('operations')]
    public function test_http_and_page_contract_share_the_owner_text_boundary(string $operation, int $limit, string $field): void
    {
        $fixture = $this->fixture();
        $player = app(PlayerReferenceQuery::class)->require($fixture['actorId']);
        $allianceFacts = app(PlayerIdentityContextQuery::class)->forPlayers([$player->playerId])[$player->playerId] ?? null;
        $kingdom = app(KingdomAuthorityFactsQuery::class)->findCurrent($player->playerId, $player->kingdomId)->permissionKeysObservedAtRead ?? [];
        $version = app(PlayerAuthorityContextVersion::class)->issue($player, $allianceFacts, $kingdom);
        $this->actingAs($fixture['user'])->withSession([(string) config('game_world.active_player_session_key') => $fixture['actorId']])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $version);
        $this->get(route('alliance.recruitment.candidates.show', $fixture['candidate']))
            ->assertOk()->assertInertia(static function (Assert $page): void {
                $page->component('Alliance/Recruitment/Candidate')->where('inputLimits.note', 10000)->where('inputLimits.reason', 5000);
            });
        if ($operation === 'bulk') {
            $this->get('/alliance/recruitment')->assertOk()->assertInertia(static function (Assert $page): void {
                $page->component('Alliance/Recruitment/Index')->where('reasonMaxLength', 5000);
            });
        }
        if ($operation === 'reentry') {
            $this->get(route('alliance.recruitment.reentry.show', $fixture['candidate']))
                ->assertOk()->assertInertia(static function (Assert $page): void {
                    $page->component('Alliance/Recruitment/Reentry')->where('reasonMaxLength', 5000);
                });
        }
        $candidateId = (string) $fixture['candidate']->id;
        [$method, $url, $data] = match ($operation) {
            'bulk' => ['POST', '/alliance/recruitment/bulk-stage', ['candidate_ids' => [$candidateId, (string) $fixture['target']->id], 'stage' => 'screening']],
            'note' => ['POST', '/alliance/recruitment/'.$candidateId.'/notes', []],
            'stage' => ['PATCH', '/alliance/recruitment/'.$candidateId.'/stage', ['stage' => 'screening']],
            'merge' => ['POST', '/alliance/recruitment/'.$candidateId.'/merge/'.$fixture['target']->id, []],
            'reentry' => ['PATCH', '/alliance/recruitment/'.$candidateId.'/reentry', ['control' => 'review_required']],
        };
        $before = $this->state();
        $this->json($method, $url, $data + [$field => str_repeat('界', $limit + 1)])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        self::assertSame($before, $this->state());
        $text = str_repeat('界', $limit);
        $this->json($method, $url, $data + [$field => " \t".$text."\n "])->assertRedirect();
        $this->assertStored($operation, $fixture, $text);
    }

    /** @return iterable<string,array{string,?string}> */
    public static function emptyReasons(): iterable
    {
        foreach (['stage', 'bulk', 'merge', 'reentry'] as $operation) {
            yield $operation.' null' => [$operation, null];
            yield $operation.' whitespace' => [$operation, " \t\n "];
        }
    }

    #[DataProvider('emptyReasons')]
    public function test_optional_reasons_remain_absent_without_empty_retained_text(string $operation, ?string $reason): void
    {
        $fixture = $this->fixture();
        $this->perform($operation, $fixture, $reason);
        $this->assertStored($operation, $fixture, null);
    }

    public function test_empty_note_has_matching_field_feedback_and_no_side_effects(): void
    {
        $fixture = $this->fixture();
        $before = $this->state();
        try {
            $this->perform('note', $fixture, " \t\n ");
            self::fail('A note requires visible text.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('body', $exception->errors());
        }
        self::assertSame($before, $this->state());
    }

    /** @param array{user:User,actorId:string,allianceId:string,candidate:RecruitmentCandidate,target:RecruitmentCandidate} $fixture */
    private function perform(string $operation, array $fixture, ?string $text): void
    {
        $actorId = $fixture['actorId'];
        $allianceId = $fixture['allianceId'];
        $candidateId = (string) $fixture['candidate']->id;
        match ($operation) {
            'bulk' => app(BulkChangeRecruitmentStage::class)->handle($actorId, $allianceId, [$candidateId, (string) $fixture['target']->id], RecruitmentStage::Screening, $text),
            'note' => app(AddRecruitmentNote::class)->handle($actorId, $allianceId, $candidateId, $text ?? ''),
            'stage' => app(ChangeRecruitmentStage::class)->handle($actorId, $allianceId, $candidateId, RecruitmentStage::Screening, $text),
            'merge' => app(MergeRecruitmentCandidates::class)->handle($actorId, $allianceId, $candidateId, (string) $fixture['target']->id, $text),
            'reentry' => app(SetRecruitmentReentryControl::class)->handle($actorId, $allianceId, $candidateId, RecruitmentReentryControl::ReviewRequired, $text),
        };
    }

    /** @param array{user:User,actorId:string,allianceId:string,candidate:RecruitmentCandidate,target:RecruitmentCandidate} $fixture */
    private function assertStored(string $operation, array $fixture, ?string $text): void
    {
        $candidate = $fixture['candidate']->refresh();
        if ($operation === 'note') {
            self::assertSame($text, DB::table('recruitment_notes')->where('candidate_id', $candidate->id)->sole()->body);
        } elseif ($operation === 'stage' || $operation === 'bulk') {
            if ($operation === 'bulk') {
                self::assertSame(RecruitmentStage::Screening, $fixture['target']->fresh()?->stage);
                self::assertSame($text, DB::table('recruitment_stage_history')->where('candidate_id', $fixture['target']->id)->sole()->reason);
            }
            self::assertSame(RecruitmentStage::Screening, $candidate->stage);
            self::assertSame($text, DB::table('recruitment_stage_history')->where('candidate_id', $candidate->id)->sole()->reason);
        } elseif ($operation === 'merge') {
            self::assertSame((string) $fixture['target']->id, $candidate->merged_into_id);
            $notes = DB::table('recruitment_notes')->where('candidate_id', $fixture['target']->id);
            if ($text === null) {
                self::assertFalse($notes->exists());
            } else {
                self::assertSame('Merge reason: '.$text, $notes->sole()->body);
            }
        } else {
            self::assertSame(RecruitmentReentryControl::ReviewRequired, $candidate->reentry_control);
            self::assertSame($text, $candidate->reentry_reason);
        }
    }

    private function event(string $operation): string
    {
        return match ($operation) {
            'bulk' => 'recruitment.candidates.bulk_stage_changed',
            'note' => 'recruitment.note.created',
            'stage' => 'recruitment.candidate.stage_changed',
            'merge' => 'recruitment.candidate.merged',
            'reentry' => 'recruitment.reentry_control_changed',
        };
    }

    /** @return array{user:User,actorId:string,allianceId:string,candidate:RecruitmentCandidate,target:RecruitmentCandidate} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $owner = $factory->player((int) $user->id, 59291);
        $alliance = $factory->alliance($owner);
        $create = static fn (string $name): RecruitmentCandidate => RecruitmentCandidate::query()->create([
            'alliance_id' => $alliance->allianceId, 'full_name' => $name, 'email' => $name.'@example.test',
            'stage' => RecruitmentStage::New, 'submitted_at' => now(),
        ]);

        return ['user' => $user, 'actorId' => $owner->playerId, 'allianceId' => $alliance->allianceId, 'candidate' => $create('source'), 'target' => $create('target')];
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['recruitment_candidates', 'recruitment_notes', 'recruitment_stage_history', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
