<?php

declare(strict_types=1);

namespace Tests\Feature\ReadModels\RecruitmentManagement;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Actions\MergeRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCommunication;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentNote;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\RecruitmentManagement\Queries\RecruitmentCandidateDetailQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentCandidateHistoryPaginationV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string}> */
    public static function sections(): iterable
    {
        foreach (['notes', 'history', 'communications', 'duplicates'] as $section) {
            yield $section => [$section];
        }
    }

    #[DataProvider('sections')]
    public function test_history_pages_bound_hydration_and_preserve_deterministic_ties(string $section): void
    {
        [$owner, $alliance, $candidate] = $this->fixture();
        $ids = $this->history($section, $owner, $alliance, $candidate, 51);
        $model = match ($section) {
            'notes' => RecruitmentNote::class,
            'history' => RecruitmentStageHistory::class,
            'communications' => RecruitmentCommunication::class,
            'duplicates' => RecruitmentCandidate::class,
        };
        $retrieved = 0;
        Event::listen('eloquent.retrieved: '.$model, static function () use (&$retrieved): void {
            $retrieved++;
        });
        $cursor = null;
        $seen = [];
        foreach ([25, 25, 1] as $pageNumber => $count) {
            $retrieved = 0;
            DB::enableQueryLog();
            DB::flushQueryLog();
            $projection = app(RecruitmentCandidateDetailQuery::class)->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, [$section => $cursor]);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            if ($section === 'communications') {
                self::assertSame(51, $projection['transferCampaign']['communications']['total']);
            }
            $page = $projection[$section.'Page'];
            self::assertArrayNotHasKey($section, $projection);
            self::assertCount($count, $page['items']);
            self::assertSame(25, $page['pageSize']);
            self::assertSame($pageNumber === 0, $page['isFirstPage']);
            self::assertSame($pageNumber < 2, $page['hasMore']);
            self::assertLessThanOrEqual(27, $retrieved);
            self::assertLessThanOrEqual(40, $queries);
            $seen = array_merge($seen, array_column($page['items'], 'id'));
            $cursor = $page['nextCursor'];
        }
        self::assertNull($cursor);
        self::assertSame($ids, $seen);
        self::assertCount(51, array_unique($seen));
    }

    /** @return iterable<string,array{string}> */
    public static function invalidScopes(): iterable
    {
        foreach (['section', 'candidate', 'Alliance', 'tampered'] as $change) {
            yield $change => [$change];
        }
    }

    #[DataProvider('invalidScopes')]
    public function test_history_continuation_cannot_be_reused_in_a_different_scope(string $change): void
    {
        [$owner, $alliance, $candidate] = $this->fixture();
        $this->history('notes', $owner, $alliance, $candidate, 26);
        $cursor = app(RecruitmentCandidateDetailQuery::class)->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id)['notesPage']['nextCursor'];
        $section = 'notes';
        if ($change === 'section') {
            $section = 'history';
        } elseif ($change === 'candidate') {
            $candidate = RecruitmentCandidate::query()->create(['alliance_id' => $alliance->allianceId, 'full_name' => 'Other candidate', 'email' => 'other@example.test', 'stage' => RecruitmentStage::New, 'submitted_at' => now()]);
        } elseif ($change === 'Alliance') {
            [$owner, $alliance, $candidate] = $this->fixture();
        } else {
            $cursor = 'invalid-cursor';
        }
        try {
            app(RecruitmentCandidateDetailQuery::class)->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, [$section => $cursor]);
            self::fail('A history cursor must remain bound to its authorized view.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
    }

    public function test_note_continuation_preserves_other_history_pages_and_newer_insertions(): void
    {
        [$owner, $alliance, $candidate] = $this->fixture();
        $noteIds = $this->history('notes', $owner, $alliance, $candidate, 51);
        $historyIds = $this->history('history', $owner, $alliance, $candidate, 30);
        $first = app(RecruitmentCandidateDetailQuery::class)->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id);
        $this->travel(1)->minutes();
        RecruitmentNote::query()->create(['alliance_id' => $alliance->allianceId, 'candidate_id' => $candidate->id, 'author_player_id' => $owner->playerId, 'body' => 'Newer note']);
        RecruitmentNote::query()->whereKey($noteIds[24])->delete();
        $second = app(RecruitmentCandidateDetailQuery::class)->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, ['notes' => $first['notesPage']['nextCursor']]);
        self::assertSame(array_slice($noteIds, 25, 25), array_column($second['notesPage']['items'], 'id'));
        self::assertSame(array_slice($historyIds, 0, 25), array_column($second['historyPage']['items'], 'id'));
        self::assertTrue($second['historyPage']['isFirstPage']);
        self::assertFalse($second['notesPage']['isFirstPage']);
    }

    public function test_duplicate_continuation_expires_when_current_match_facts_change(): void
    {
        [$owner, $alliance, $candidate] = $this->fixture();
        $this->history('duplicates', $owner, $alliance, $candidate, 26);
        $cursor = app(RecruitmentCandidateDetailQuery::class)->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id)['duplicatesPage']['nextCursor'];
        $source = RecruitmentCandidate::query()->create(['alliance_id' => $alliance->allianceId, 'full_name' => 'Merge source', 'email' => 'source@example.test', 'contact_handle' => 'new-contact', 'stage' => RecruitmentStage::New, 'submitted_at' => now()]);
        app(MergeRecruitmentCandidates::class)->handle($owner->playerId, $alliance->allianceId, (string) $source->id, (string) $candidate->id);
        try {
            app(RecruitmentCandidateDetailQuery::class)->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, ['duplicates' => $cursor]);
            self::fail('Duplicate continuation must use its original current match facts.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
    }

    public function test_valid_cursor_does_not_preserve_revoked_recruiter_authority(): void
    {
        [$owner, $alliance, $candidate] = $this->fixture();
        $factory = app(ScenarioFactory::class);
        $manager = $factory->player($factory->account()->userId, 59290);
        $membership = AllianceMembership::query()->create(['alliance_id' => $alliance->allianceId, 'player_id' => $manager->playerId, 'rank' => AllianceRank::R3, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $roleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, 'History reviewer', [AlliancePermission::RecruitmentManage]);
        app(AssignMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, $roleId);
        $this->history('notes', $owner, $alliance, $candidate, 26);
        $cursor = app(RecruitmentCandidateDetailQuery::class)->forCandidate($manager->playerId, $alliance->allianceId, (string) $candidate->id)['notesPage']['nextCursor'];
        app(RemoveMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, $roleId);
        $this->expectException(AuthorizationException::class);
        app(RecruitmentCandidateDetailQuery::class)->forCandidate($manager->playerId, $alliance->allianceId, (string) $candidate->id, ['notes' => $cursor]);
    }

    public function test_foreign_candidate_is_not_projected_under_an_authorized_alliance(): void
    {
        [$owner, $alliance] = $this->fixture();
        [, , $foreign] = $this->fixture();
        $this->expectException(ModelNotFoundException::class);
        app(RecruitmentCandidateDetailQuery::class)->forCandidate($owner->playerId, $alliance->allianceId, (string) $foreign->id);
    }

    public function test_candidate_http_contract_exposes_scoped_history_pages_and_validates_cursors(): void
    {
        [$owner, , $candidate] = $this->fixture();
        $user = User::query()->findOrFail($owner->userId);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $owner->playerId])
            ->get(route('alliance.recruitment.candidates.show', $candidate))
            ->assertOk()->assertInertia(static function (Assert $page): void {
                $page->component('Alliance/Recruitment/Candidate')
                    ->has('notesPage.items', 0)->has('historyPage.items', 0)->has('communicationsPage.items', 0)->has('duplicatesPage.items', 0)
                    ->where('notesPage.pageSize', 25)->missing('notes')->missing('history')->missing('communications')->missing('duplicates');
            });
        $this->getJson(route('alliance.recruitment.candidates.show', ['candidate' => $candidate->id, 'notes_cursor' => ['invalid']]))->assertUnprocessable();
    }

    /** @return array{PlayerReference,AllianceReference,RecruitmentCandidate} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59290);
        $alliance = $factory->alliance($owner);
        $candidate = RecruitmentCandidate::query()->create(['alliance_id' => $alliance->allianceId, 'full_name' => 'History candidate', 'email' => 'history@example.test', 'stage' => RecruitmentStage::New, 'submitted_at' => now()]);

        return [$owner, $alliance, $candidate];
    }

    /** @return list<string> */
    private function history(string $section, PlayerReference $owner, AllianceReference $alliance, RecruitmentCandidate $candidate, int $count): array
    {
        $rows = [];
        $ids = [];
        $at = now()->subDay()->format('Y-m-d H:i:s');
        for ($index = 0; $index < $count; $index++) {
            $id = (string) Str::ulid();
            $ids[] = $id;
            $common = ['id' => $id, 'alliance_id' => $alliance->allianceId, 'created_at' => $at, 'updated_at' => $at];
            $rows[] = match ($section) {
                'notes' => $common + ['candidate_id' => $candidate->id, 'author_player_id' => $owner->playerId, 'body' => 'Historical note '.$index],
                'history' => $common + ['candidate_id' => $candidate->id, 'from_stage' => RecruitmentStage::New->value, 'to_stage' => RecruitmentStage::Screening->value, 'changed_at' => $at, 'changed_by_player_id' => $owner->playerId],
                'communications' => $common + ['candidate_id' => $candidate->id, 'subject' => 'Historical decision '.$index, 'body' => 'Historical body', 'status' => 'prepared', 'idempotency_key' => hash('sha256', $id), 'created_by_player_id' => $owner->playerId],
                'duplicates' => $common + ['full_name' => 'Historical candidate '.$index, 'email' => $candidate->email, 'stage' => RecruitmentStage::Declined->value, 'submitted_at' => $at],
            };
        }
        $table = match ($section) {
            'notes' => 'recruitment_notes',
            'history' => 'recruitment_stage_history',
            'communications' => 'recruitment_communications',
            'duplicates' => 'recruitment_candidates',
        };
        DB::table($table)->insert($rows);
        sort($ids, SORT_STRING);

        return $section === 'duplicates' ? $ids : array_reverse($ids);
    }
}
