<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomTransfers\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCompletion;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\HandleInertiaRequests;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Contexts\GameWorld\KingdomTransfers\Fixtures\TransferParticipantPageVisualFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TransferParticipantPaginationTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string}> */
    public static function workspaces(): iterable
    {
        foreach (['', '/readiness', '/manage', '/completion'] as $suffix) {
            yield $suffix ?: 'overview' => ['/alliance/transfers'.$suffix];
        }
    }

    #[DataProvider('workspaces')]
    public function test_workspace_materializes_one_participant_page_not_the_complete_plan(string $url): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 66);
        $hydrated = 0;
        TransferParticipant::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        $response = $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId])
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? ''])
            ->get($url)->assertOk();
        self::assertLessThanOrEqual(26, $hydrated, 'A workspace must not hydrate every plan participant.');
        $response->assertJsonCount(25, 'props.participants.items')->assertJsonPath('props.participantSummary.total', 67)
            ->assertJsonPath('props.participants.hasMore', true);
    }

    public function test_keyset_visits_every_participant_once_despite_changed_labels_and_deleted_boundary(): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 66);
        $query = app(TransferParticipantQuery::class);
        $expected = TransferParticipant::query()->where('transfer_plan_id', $f['plan']->id)->orderBy('id')->pluck('id')->all();
        $first = $query->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true);
        self::assertCount(25, $first->items);
        self::assertTrue($first->isFirstPage);
        self::assertNotNull($first->nextCursor);
        DB::table('transfer_participants')->where('id', $expected[24])->delete();
        DB::table('transfer_participants')->where('id', $expected[30])->update(['observed_name' => 'Now alphabetically first', 'readiness_state' => 'confirmed']);
        $seen = array_map(static fn (TransferParticipant $row): string => (string) $row->id, $first->items);
        $cursor = $first->nextCursor;
        $pages = 1;
        while ($cursor !== null) {
            self::assertLessThan(5, $pages, 'Continuation must not loop.');
            $page = $query->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true, $cursor);
            self::assertFalse($page->isFirstPage);
            self::assertLessThanOrEqual(25, count($page->items));
            $seen = [...$seen, ...array_map(static fn (TransferParticipant $row): string => (string) $row->id, $page->items)];
            $cursor = $page->nextCursor;
            $pages++;
        }
        self::assertSame($expected, $seen);
        self::assertCount(67, array_unique($seen));
        self::assertSame(3, $pages);
    }

    public function test_complete_aggregate_counts_do_not_depend_on_page_or_foreign_completion_rows(): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 66);
        $rows = TransferParticipant::query()->where('transfer_plan_id', $f['plan']->id)->orderBy('id')->get();
        foreach ([30, 40, 50] as $index) {
            $rows[$index]->update(['readiness_state' => 'confirmed']);
        }
        $rows[60]->update(['withdrawn_at' => now()]);
        TransferCompletion::query()->create(['alliance_id' => $f['alliance']->allianceId, 'transfer_plan_id' => $f['plan']->id,
            'transfer_participant_id' => $rows[30]->id, 'direction' => 'staying', 'completed_at' => now()]);
        $other = $this->otherPlan($f);
        TransferCompletion::query()->create(['alliance_id' => $f['alliance']->allianceId, 'transfer_plan_id' => $other->id,
            'transfer_participant_id' => $rows[40]->id, 'direction' => 'staying', 'completed_at' => now()]);
        $hydrated = 0;
        TransferParticipant::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        $query = app(TransferParticipantQuery::class);
        self::assertSame(['total' => 67, 'incoming' => 0, 'outgoing' => 1, 'staying' => 66,
            'completed' => 1, 'confirmed' => 2, 'withdrawn' => 1],
            $query->summary($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true));
        self::assertSame(0, $hydrated, 'Summary SQL must not hydrate the participant set.');
        $active = $query->summary($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id);
        self::assertSame(66, $active['total']);
        self::assertSame(0, $active['withdrawn']);
    }

    public function test_cursor_binds_actor_plan_view_and_privilege_and_never_leaks_foreign_scope(): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 30);
        $q = app(TransferParticipantQuery::class);
        $page = $q->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true);
        $actor = (new ScenarioFactory)->player($f['user']->id, 59301);
        AllianceMembership::query()->create(['alliance_id' => $f['alliance']->allianceId, 'player_id' => $actor->playerId,
            'status' => 'active', 'rank' => AllianceRank::R4, 'joined_at' => now()]);
        $other = $this->otherPlan($f);
        foreach ([
            [$actor->playerId, (string) $f['plan']->id, true, TransferPermission::View],
            [$f['actor']->playerId, (string) $other->id, true, TransferPermission::View],
            [$f['actor']->playerId, (string) $f['plan']->id, false, TransferPermission::View],
            [$f['actor']->playerId, (string) $f['plan']->id, true, TransferPermission::Manage],
        ] as [$who, $plan, $all, $permission]) {
            try {
                $q->page($who, $f['alliance']->allianceId, $plan, $all, $page->nextCursor, $permission);
                self::fail('A cursor must not cross its authorized view.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('cursor', $error->errors());
            }
        }
        $foreignActor = (new ScenarioFactory)->player((new ScenarioFactory)->account()->userId, 59401);
        $foreignAlliance = (new ScenarioFactory)->alliance($foreignActor);
        $foreignPlan = $f['plan']->replicate();
        $foreignPlan->alliance_id = $foreignAlliance->allianceId;
        $foreignPlan->save();
        try {
            $q->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $foreignPlan->id, true);
            self::fail('A plan must belong to the authorized Alliance.');
        } catch (ModelNotFoundException) {
            self::assertTrue(true);
        }
        $this->expectException(AuthorizationException::class);
        $q->page($f['actor']->playerId, $foreignAlliance->allianceId, (string) $foreignPlan->id, true, $page->nextCursor);
    }

    public function test_cursor_and_summary_require_current_membership_before_hydration(): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 30);
        $q = app(TransferParticipantQuery::class);
        $first = $q->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true);
        AllianceMembership::query()->where('player_id', $f['actor']->playerId)->where('alliance_id', $f['alliance']->allianceId)->update(['status' => 'suspended']);
        $hydrated = 0;
        TransferParticipant::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        foreach (['page', 'summary'] as $operation) {
            try {
                if ($operation === 'page') {
                    $q->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true, $first->nextCursor);
                } else {
                    $q->summary($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true);
                }
                self::fail('Every read must reauthorize.');
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }
        self::assertSame(0, $hydrated);
    }

    public function test_plain_member_cannot_reuse_a_management_cursor_after_rank_revocation(): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 30);
        $q = app(TransferParticipantQuery::class);
        $first = $q->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true, permission: TransferPermission::Manage);
        AllianceMembership::query()->where('player_id', $f['actor']->playerId)->where('alliance_id', $f['alliance']->allianceId)->update(['rank' => AllianceRank::R1->value]);
        // Ordinary member view remains allowed, but does not inherit the old Manage capability.
        self::assertCount(25, $q->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true)->items);
        $this->expectException(AuthorizationException::class);
        $q->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true, $first->nextCursor, TransferPermission::Manage);
    }

    public function test_invalid_positions_and_oversized_input_fail_instead_of_returning_first_page(): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 30);
        $scope = implode('|', ['transfer-participants', $f['actor']->playerId, $f['alliance']->allianceId, $f['plan']->id, 'all', TransferPermission::View->value]);
        foreach ([['id' => 'not-an-id'], ['id' => 12], ['id' => (string) $f['participant']->id, 'extra' => true]] as $position) {
            $cursor = app(ScopedCursorCodec::class)->encode($scope, $position);
            try {
                app(TransferParticipantQuery::class)->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true, $cursor);
                self::fail('Malformed positions must not reset navigation.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('participant_cursor', $error->errors());
            }
        }
        $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId]);
        $this->getJson('/alliance/transfers/readiness?participant_cursor='.str_repeat('x', 4097))->assertUnprocessable()->assertJsonValidationErrors('participant_cursor');
        $this->getJson('/alliance/transfers/readiness?participant_cursor=corrupt')->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    public function test_related_rows_are_scoped_to_the_same_plan_and_tenant(): void
    {
        $f = $this->fixture();
        $other = $this->otherPlan($f);
        $cohort = TransferCohort::query()->create(['alliance_id' => $f['alliance']->allianceId, 'transfer_plan_id' => $other->id,
            'name' => 'Wrong plan private cohort', 'direction' => 'outgoing', 'state' => 'active']);
        $f['participant']->update(['transfer_cohort_id' => $cohort->id]);
        TransferCompletion::query()->create(['alliance_id' => $f['alliance']->allianceId, 'transfer_plan_id' => $other->id,
            'transfer_participant_id' => $f['participant']->id, 'direction' => 'outgoing', 'completed_at' => now()]);
        $row = app(TransferParticipantQuery::class)->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true)->items[0];
        self::assertNull($row->cohort);
        self::assertNull($row->completion);
        self::assertSame(0, app(TransferParticipantQuery::class)->summary($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true)['completed']);
    }

    public function test_page_query_uses_tenant_plan_id_seek_index_and_limit(): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 30);
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $q = app(TransferParticipantQuery::class);
        $first = $q->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true);
        $q->page($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, true, $first->nextCursor);
        $scans = array_values(array_filter($queries, static fn (string $sql): bool => str_starts_with($sql, 'select "transfer_participants".*')));
        self::assertCount(2, $scans);
        foreach ($scans as $sql) {
            self::assertStringContainsString('limit 26', $sql);
        }
        self::assertStringContainsString('"id" > ?', $scans[1]);
        self::assertStringNotContainsString('offset', $scans[1]);
        $index = DB::selectOne("select indexdef from pg_indexes where schemaname = current_schema() and indexname = 'transfer_participant_page_idx'");
        self::assertNotNull($index);
        self::assertStringContainsString('(alliance_id, transfer_plan_id, id)', $index->indexdef);
        $active = DB::selectOne("select indexdef from pg_indexes where schemaname = current_schema() and indexname = 'transfer_participant_active_page_idx'");
        self::assertNotNull($active);
        self::assertStringContainsString('(alliance_id, transfer_plan_id, id)', $active->indexdef);
        self::assertStringContainsString('withdrawn_at IS NULL', $active->indexdef);
    }

    public function test_visual_page_fixtures_are_complete_and_isolated_per_project(): void
    {
        $original = $this->fixture();
        TransferParticipantPageVisualFixture::seed();
        $owners = [];
        foreach (['desktop', 'mobile'] as $project) {
            $user = User::query()->where('email', 'transfer-pages-'.$project.'@example.test')->sole();
            self::assertNotNull($user->email_verified_at);
            $player = Player::query()->where('user_id', $user->id)->sole();
            $membership = AllianceMembership::query()->where('player_id', $player->id)->sole();
            $plan = TransferPlan::query()->where('alliance_id', $membership->alliance_id)->sole();
            $owners[] = (string) $membership->alliance_id;
            $q = app(TransferParticipantQuery::class);
            $page = $q->page((string) $player->id, (string) $membership->alliance_id, (string) $plan->id, true);
            self::assertCount(25, $page->items);
            self::assertSame(61, $q->summary((string) $player->id, (string) $membership->alliance_id, (string) $plan->id, true)['total']);
            self::assertSame('Page Captain '.$project, $page->items[0]->observed_name);
        }
        self::assertCount(2, array_unique($owners));
        self::assertSame(1, TransferParticipant::query()->where('transfer_plan_id', $original['plan']->id)->count());
    }

    /** @param array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant,user:User} $f */
    private function otherPlan(array $f): TransferPlan
    {
        $window = $f['plan']->window->replicate();
        $window->label = 'Other scoped window';
        $window->save();
        $other = $f['plan']->replicate();
        $other->transfer_window_id = $window->id;
        $other->label = 'Other scoped plan';
        $other->state = 'closed';
        $other->save();

        return $other;
    }

    /** @param array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant,user:User} $f */
    private function seedParticipants(array $f, int $count): void
    {
        $factory = new ScenarioFactory;
        for ($i = 0; $i < $count; $i++) {
            $player = $factory->unclaimedPlayer(59301);
            TransferParticipant::query()->create(['alliance_id' => $f['alliance']->allianceId,
                'transfer_plan_id' => $f['plan']->id, 'player_id' => $player->playerId,
                'direction' => 'staying', 'readiness_state' => 'preparing', 'source_kingdom_id' => $player->kingdomId,
                'observed_name' => 'Participant '.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }
    }

    /** @return array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant,user:User} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $user = User::query()->findOrFail($account->userId);
        $user->forceFill(['email_verified_at' => now()])->save();
        $actor = $factory->player($account->userId, 59301);
        $alliance = $factory->alliance($actor);
        $roster = $factory->roster($actor, $alliance, $actor);
        $factory->kingdom(59302);
        $window = app(SaveTransferWindow::class)->handle($alliance->allianceId, $actor->playerId, [
            'label' => 'Bounded evidence window', 'pre_transfer_starts_at' => now()->subDays(3)->toIso8601String(),
            'invitational_starts_at' => now()->subDays(2)->toIso8601String(), 'transfer_opens_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(), 'source_type' => TransferSourceType::OfficialPublication,
            'source_reference' => 'Official window', 'observed_at' => now()->subDays(4)->toIso8601String(),
        ]);
        app(CreateTransferPlan::class)->handle($alliance->allianceId, $actor->playerId, ['label' => 'Bounded evidence plan', 'transfer_window_id' => $window]);
        $plan = TransferPlan::query()->where('alliance_id', $alliance->allianceId)->sole();
        app(SaveTransferParticipant::class)->handle($alliance->allianceId, $actor->playerId, (string) $plan->id, ['direction' => TransferDirection::Outgoing, 'roster_entry_id' => $roster->rosterEntryId, 'destination_kingdom' => 59302]);
        $participant = TransferParticipant::query()->where('transfer_plan_id', $plan->id)->sole();

        return compact('actor', 'alliance', 'plan', 'participant', 'user');
    }
}
