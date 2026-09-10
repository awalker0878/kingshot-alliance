<?php

declare(strict_types=1);

namespace Tests\ReadModels\RecruitmentManagement\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Recruitment\Actions\MergeRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\RecruitmentManagement\Queries\RecruitmentCandidateDetailQuery;
use App\ReadModels\RecruitmentManagement\Queries\RecruitmentCandidateSelectionQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RecruitmentCollectionFactory;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentCandidateCollectionsV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string}> */
    public static function selectionKinds(): iterable
    {
        foreach (['members', 'roster', 'templates'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('selectionKinds')]
    public function test_selection_pages_reach_all_scoped_choices_and_retain_an_off_page_selection(string $kind): void
    {
        [$owner, $alliance, $candidate, $ids] = $this->fixture();
        [, , , $foreignIds] = $this->fixture(2);
        $query = app(RecruitmentCandidateSelectionQuery::class);
        self::assertNull($query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind, selectedId: $foreignIds[$kind][0])['selected']);
        if ($kind !== 'templates') {
            DB::table('players')->where('id', $ids[$kind][25])->update(['current_name' => 'Collection choice 024']);
        }
        $cursor = null;
        $seen = [];
        do {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $result = $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind, 'Collection choice', $cursor, $ids[$kind][54]);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            self::assertSame($ids[$kind][54], $result['selected']['id']);
            self::assertLessThanOrEqual(25, count($result['page']['items']));
            self::assertLessThanOrEqual(16, count($queries));
            self::assertSame($cursor === null, $result['page']['isFirstPage']);
            $choiceQueries = array_filter($queries, static fn (array $entry): bool => str_contains($entry['query'], 'recruitment_choices'));
            self::assertCount(2, $choiceQueries);
            foreach ($choiceQueries as $entry) {
                self::assertMatchesRegularExpression('/limit (1|26)\b/', $entry['query']);
            }
            array_push($seen, ...array_column($result['page']['items'], 'id'));
            $cursor = $result['page']['nextCursor'];
        } while ($cursor !== null);
        self::assertSame($ids[$kind], $seen);
        self::assertCount(55, array_unique($seen));
        $search = $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind, 'choice 050', selectedId: $ids[$kind][54]);
        self::assertSame([$ids[$kind][50]], array_column($search['page']['items'], 'id'));
        self::assertSame($ids[$kind][54], $search['selected']['id']);
        self::assertSame($kind === 'members' ? 'r3' : null, $search['page']['items'][0]['rank']);
        self::assertSame($kind === 'roster' ? false : null, $search['page']['items'][0]['claimed']);
        self::assertSame([], $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind, '%_')['page']['items']);
    }

    #[DataProvider('selectionKinds')]
    public function test_selection_cursor_rejects_changed_scope_and_rechecks_current_selected_eligibility(string $kind): void
    {
        [$owner, $alliance, $candidate, $ids] = $this->fixture();
        [$foreignOwner, $foreignAlliance, $foreign] = $this->fixture(0);
        $query = app(RecruitmentCandidateSelectionQuery::class);
        $cursor = $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind)['page']['nextCursor'];
        self::assertIsString($cursor);
        $another = RecruitmentCandidate::query()->create(['alliance_id' => $alliance->allianceId, 'full_name' => 'Other', 'email' => 'other@example.test', 'stage' => RecruitmentStage::Accepted, 'submitted_at' => now()]);
        foreach ([
            fn () => $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind, 'changed search', $cursor),
            fn () => $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $another->id, $kind, cursor: $cursor),
            fn () => $query->forCandidate($foreignOwner->playerId, $foreignAlliance->allianceId, (string) $foreign->id, $kind, cursor: $cursor),
            fn () => $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind === 'members' ? 'roster' : 'members', cursor: $cursor),
            fn () => $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind, cursor: 'tampered'),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('A cursor cannot authorize another selection scope.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('cursor', $exception->errors());
            }
        }
        $selectedId = $ids[$kind][54];
        if ($kind === 'members') {
            DB::table('alliance_memberships')->where('player_id', $selectedId)->update(['status' => 'suspended']);
        } elseif ($kind === 'roster') {
            DB::table('alliance_roster_entries')->where('player_id', $selectedId)->update(['state' => 'left']);
        } else {
            DB::table('recruitment_decision_templates')->where('id', $selectedId)->update(['is_active' => false]);
        }
        self::assertNull($query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind, selectedId: $selectedId)['selected']);
        DB::table('alliance_memberships')->where('player_id', $owner->playerId)->update(['status' => 'suspended']);
        $this->expectException(AuthorizationException::class);
        $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, $kind, cursor: $cursor);
    }

    public function test_template_stage_changes_invalidate_continuation_and_current_selection(): void
    {
        [$owner, $alliance, $candidate, $ids] = $this->fixture();
        $query = app(RecruitmentCandidateSelectionQuery::class);
        $cursor = $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, 'templates')['page']['nextCursor'];
        $candidate->forceFill(['stage' => RecruitmentStage::Declined])->save();
        self::assertNull($query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, 'templates', selectedId: $ids['templates'][0])['selected']);
        $this->expectException(ValidationException::class);
        $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, 'templates', cursor: $cursor);
    }

    public function test_attachments_page_independently_across_deleted_boundaries_without_full_selectors(): void
    {
        [$owner, $alliance, $candidate, $ids] = $this->fixture();
        $query = app(RecruitmentCandidateDetailQuery::class);
        $first = $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id);
        foreach (['members', 'conversionPlayers', 'decisionTemplates', 'tags', 'reviewers'] as $obsolete) {
            self::assertArrayNotHasKey($obsolete, $first);
        }
        foreach (['tags', 'reviewers'] as $kind) {
            self::assertSame(array_slice($ids[$kind], 0, 25), array_column($first[$kind.'Page']['items'], 'id'));
            $table = $kind === 'tags' ? 'recruitment_candidate_tags' : 'recruitment_candidate_reviewers';
            $column = $kind === 'tags' ? 'tag_id' : 'reviewer_player_id';
            DB::table($table)->where('candidate_id', $candidate->id)->where($column, $ids[$kind][24])->delete();
            $seen = array_column($first[$kind.'Page']['items'], 'id');
            $cursor = $first[$kind.'Page']['nextCursor'];
            do {
                $page = $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, [$kind => $cursor]);
                self::assertLessThanOrEqual(25, count($page[$kind.'Page']['items']));
                self::assertTrue($page[($kind === 'tags' ? 'reviewers' : 'tags').'Page']['isFirstPage']);
                array_push($seen, ...array_column($page[$kind.'Page']['items'], 'id'));
                $cursor = $page[$kind.'Page']['nextCursor'];
            } while ($cursor !== null);
            self::assertSame($ids[$kind], $seen);
        }
        $this->expectException(ValidationException::class);
        $query->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, ['tags' => $first['reviewersPage']['nextCursor']]);
    }

    public function test_merge_copies_all_attachment_batches_atomically_and_replay_does_not_duplicate(): void
    {
        [$owner, $alliance, $source, $ids] = $this->fixture(205);
        $target = RecruitmentCandidate::query()->create(['alliance_id' => $alliance->allianceId, 'full_name' => 'Merge target', 'email' => 'target@example.test', 'stage' => RecruitmentStage::New, 'submitted_at' => now()]);
        $merge = app(MergeRecruitmentCandidates::class);
        $failOnce = true;
        DB::listen(static function (QueryExecuted $event) use (&$failOnce): void {
            if ($failOnce && str_starts_with($event->sql, 'insert into "outbox_messages"') && in_array('recruitment.candidate.merged', $event->bindings, true)) {
                $failOnce = false;
                throw new RuntimeException('Injected merge outbox failure');
            }
        });
        try {
            $merge->handle($owner->playerId, $alliance->allianceId, (string) $source->id, (string) $target->id);
            self::fail('A late outbox failure must roll back every attachment batch.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected merge outbox failure', $exception->getMessage());
        }
        self::assertNull($source->fresh()->merged_into_id);
        self::assertSame(0, DB::table('recruitment_candidate_reviewers')->where('candidate_id', $target->id)->count());
        self::assertSame(0, DB::table('recruitment_candidate_tags')->where('candidate_id', $target->id)->count());
        DB::enableQueryLog();
        DB::flushQueryLog();
        $merge->handle($owner->playerId, $alliance->allianceId, (string) $source->id, (string) $target->id);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach (['tags' => 'tag_id', 'reviewers' => 'reviewer_player_id'] as $kind => $column) {
            $expected = $ids[$kind];
            sort($expected);
            self::assertSame($expected, DB::table('recruitment_candidate_'.$kind)->where('candidate_id', $target->id)->orderBy($column)->pluck($column)->all());
            $reads = array_filter($queries, static fn (array $entry): bool => str_starts_with($entry['query'], 'select * from "recruitment_candidate_'.$kind.'"'));
            self::assertCount(3, $reads);
            foreach ($reads as $entry) {
                self::assertStringContainsString('limit 100', $entry['query']);
            }
        }
        $auditCount = DB::table('audit_events')->count();
        $merge->handle($owner->playerId, $alliance->allianceId, (string) $source->id, (string) $target->id);
        self::assertSame($auditCount, DB::table('audit_events')->count());
        self::assertSame(205, DB::table('recruitment_candidate_reviewers')->where('candidate_id', $target->id)->count());
        self::assertSame(205, DB::table('recruitment_candidate_tags')->where('candidate_id', $target->id)->count());
    }

    public function test_http_options_validate_input_and_authorized_candidate_scope(): void
    {
        [$owner, $alliance, $candidate, $ids] = $this->fixture();
        [, , $foreign] = $this->fixture(0);
        $user = User::query()->findOrFail($owner->userId);
        $user->forceFill(['email_verified_at' => now()])->save();
        $url = route('alliance.recruitment.candidates.options', ['candidate' => $candidate->id, 'kind' => 'templates']);
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $owner->playerId]);
        $this->getJson($url)->assertOk()->assertJsonCount(25, 'page.items')->assertJsonPath('page.hasMore', true);
        $this->getJson($url.'?selected='.$ids['templates'][54])->assertOk()->assertJsonPath('selected.id', $ids['templates'][54]);
        foreach (['q[]=invalid', 'q='.str_repeat('a', 161), 'selected=invalid', 'cursor[]=invalid'] as $invalid) {
            $this->getJson($url.'?'.$invalid)->assertUnprocessable();
        }
        $this->getJson(route('alliance.recruitment.candidates.options', ['candidate' => $foreign->id, 'kind' => 'templates']))->assertNotFound();
        $this->get(route('alliance.recruitment.candidates.show', $candidate))->assertOk()->assertInertia(static function (Assert $page): void {
            $page->has('tagsPage.items', 25)->has('reviewersPage.items', 25)->missing('tags')->missing('reviewers')->missing('members')->missing('conversionPlayers')->missing('decisionTemplates');
        });
        $candidate->forceFill(['anonymized_at' => now()])->save();
        $this->expectException(ModelNotFoundException::class);
        app(RecruitmentCandidateSelectionQuery::class)->forCandidate($owner->playerId, $alliance->allianceId, (string) $candidate->id, 'templates');
    }

    /** @return array{PlayerReference,AllianceReference,RecruitmentCandidate,array{tags:list<string>,reviewers:list<string>,members:list<string>,roster:list<string>,templates:list<string>}} */
    private function fixture(int $count = 55): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59306);
        $alliance = $factory->alliance($owner);
        $candidate = RecruitmentCandidate::query()->create(['alliance_id' => $alliance->allianceId, 'full_name' => 'Collection candidate', 'email' => 'collection@example.test', 'stage' => RecruitmentStage::Accepted, 'submitted_at' => now()]);

        return [$owner, $alliance, $candidate, app(RecruitmentCollectionFactory::class)->seed($alliance, $owner->playerId, $candidate, $count)];
    }
}
