<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomTransfers\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferBlockerState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferReadinessTransition;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferWorkflowHistoryQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\HandleInertiaRequests;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Contexts\GameWorld\KingdomTransfers\Fixtures\TransferWorkflowHistoryVisualFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TransferWorkflowHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_returns_complete_history_counts_without_materializing_child_records(): void
    {
        $f = $this->fixture();
        $this->seedHistory($f, 31, 53, 61);
        $hydrated = 0;
        TransferBlocker::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        TransferReadinessTransition::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        $response = $this->actingAs($f['user'])
            ->withSession(['players.selected_id' => $f['actor']->playerId])
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? ''])
            ->get('/alliance/transfers/readiness')->assertOk();
        self::assertSame(0, $hydrated, 'The workspace must not hydrate the full blocker/transition history.');
        $response->assertJsonPath('props.participants.items.0.activeBlockerCount', 31)
            ->assertJsonPath('props.participants.items.0.resolvedBlockerCount', 53)
            ->assertJsonPath('props.participants.items.0.readinessTransitionCount', 61)
            ->assertJsonMissingPath('props.participants.items.0.blockers')
            ->assertJsonMissingPath('props.participants.items.0.readinessHistory');
    }

    /** @return iterable<string,array{string,string,string|null,int}> */
    public static function histories(): iterable
    {
        yield 'old active blockers' => ['blockers', 'transfer_blockers', 'active', 31];
        yield 'resolved blockers' => ['blockers', 'transfer_blockers', 'resolved', 53];
        yield 'readiness transitions' => ['readiness-history', 'transfer_readiness_transitions', null, 61];
    }

    #[DataProvider('histories')]
    public function test_each_history_is_complete_and_keyset_bounded_after_a_boundary_row_is_deleted(string $endpoint, string $table, ?string $state, int $count): void
    {
        $f = $this->fixture();
        $this->seedHistory($f, 31, 53, 61);
        $query = DB::table($table)->where('transfer_participant_id', $f['participant']->id);
        if ($state !== null) {
            $query->where('state', $state);
        }
        $expected = $query->orderByDesc('created_at')->orderByDesc('id')->pluck('id')->all();
        self::assertCount($count, $expected);
        $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId]);
        $url = '/alliance/transfers/'.$f['plan']->id.'/participants/'.$f['participant']->id.'/'.$endpoint;
        $filters = $state === null ? [] : ['state' => $state];
        $first = $this->getJson($url.'?'.http_build_query($filters))->assertOk()->assertJsonCount(25, 'items');
        self::assertSame(array_slice($expected, 0, 25), array_column($first->json('items'), 'id'));
        self::assertTrue($first->json('isFirstPage'));
        self::assertTrue($first->json('hasMore'));
        $boundary = $expected[24];
        DB::table($table)->where('id', $boundary)->delete();
        $seen = array_column($first->json('items'), 'id');
        $cursor = $first->json('nextCursor');
        while ($cursor !== null) {
            $page = $this->getJson($url.'?'.http_build_query([...$filters, 'cursor' => $cursor]))->assertOk()
                ->assertJsonPath('isFirstPage', false)->assertJsonPath('pageSize', 25);
            self::assertLessThanOrEqual(25, count($page->json('items')));
            $seen = [...$seen, ...array_column($page->json('items'), 'id')];
            $cursor = $page->json('nextCursor');
        }
        self::assertSame($expected, $seen);
        self::assertCount($count, array_unique($seen));
    }

    public function test_cursor_cannot_cross_participants_states_or_history_kinds_and_input_failures_are_visible(): void
    {
        $f = $this->fixture();
        $this->seedHistory($f, 31, 53, 61);
        $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId]);
        $url = '/alliance/transfers/'.$f['plan']->id.'/participants/'.$f['participant']->id;
        $cursor = $this->getJson($url.'/blockers?state=active')->assertOk()->json('nextCursor');
        $this->getJson($url.'/blockers?'.http_build_query(['state' => 'resolved', 'cursor' => $cursor]))->assertUnprocessable()->assertJsonValidationErrors('cursor');
        $this->getJson($url.'/readiness-history?'.http_build_query(['cursor' => $cursor]))->assertUnprocessable()->assertJsonValidationErrors('cursor');
        $other = $f['participant']->replicate();
        $player = app(ScenarioFactory::class)->unclaimedPlayer(59303);
        $other->player_id = $player->playerId;
        $other->save();
        $this->getJson('/alliance/transfers/'.$f['plan']->id.'/participants/'.$other->id.'/blockers?'.http_build_query(['cursor' => $cursor]))->assertUnprocessable()->assertJsonValidationErrors('cursor');
        foreach (['cursor[]=bad', 'cursor=forged', 'state=unknown', 'state[]=active'] as $query) {
            $this->getJson($url.'/blockers?'.$query)->assertUnprocessable();
        }
        $this->getJson($url.'/readiness-history?cursor[]=bad')->assertUnprocessable();
        $this->getJson('/alliance/transfers/'.Str::ulid().'/participants/'.$f['participant']->id.'/blockers')->assertNotFound();
        $this->getJson('/alliance/transfers/'.$f['plan']->id.'/participants/'.Str::ulid().'/readiness-history')->assertNotFound();
    }

    #[DataProvider('histories')]
    public function test_every_page_rechecks_current_membership_and_scope_before_history_hydration(string $endpoint, string $table, ?string $state, int $count): void
    {
        $f = $this->fixture();
        $this->seedHistory($f, 31, 53, 61);
        $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId]);
        $url = '/alliance/transfers/'.$f['plan']->id.'/participants/'.$f['participant']->id.'/'.$endpoint;
        $filters = $state === null ? [] : ['state' => $state];
        $cursor = $this->getJson($url.'?'.http_build_query($filters))->assertOk()->json('nextCursor');
        AllianceMembership::query()->where('alliance_id', $f['alliance']->allianceId)->where('player_id', $f['actor']->playerId)->update(['status' => 'suspended']);
        $hydrated = 0;
        TransferBlocker::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        TransferReadinessTransition::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        $this->getJson($url.'?'.http_build_query([...$filters, 'cursor' => $cursor]))->assertStatus(409)->assertJsonMissingPath('items');
        try {
            $history = app(TransferWorkflowHistoryQuery::class);
            if ($state === null) {
                $history->transitions($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) $f['participant']->id, $cursor);
            } else {
                $history->blockers($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) $f['participant']->id, TransferBlockerState::from($state), $cursor);
            }
            self::fail('The owner query must reauthorize even outside HTTP middleware.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        self::assertSame(0, $hydrated);
    }

    public function test_inconsistent_tenant_or_plan_history_is_not_counted_or_disclosed(): void
    {
        $f = $this->fixture();
        $this->seedHistory($f, 1, 1, 1);
        $factory = app(ScenarioFactory::class);
        $otherActor = $factory->player($factory->account()->userId, 59303);
        $otherAlliance = $factory->alliance($otherActor);
        $otherPlan = $f['plan']->replicate();
        $otherPlan->alliance_id = $otherAlliance->allianceId;
        $otherPlan->save();
        foreach (['transfer_blockers', 'transfer_readiness_transitions'] as $table) {
            $row = (array) DB::table($table)->where('transfer_participant_id', $f['participant']->id)->first();
            DB::table($table)->insert(array_replace($row, ['id' => (string) Str::ulid(), 'alliance_id' => $otherAlliance->allianceId]));
            DB::table($table)->insert(array_replace($row, ['id' => (string) Str::ulid(), 'transfer_plan_id' => $otherPlan->id]));
        }
        $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId]);
        $url = '/alliance/transfers/'.$f['plan']->id.'/participants/'.$f['participant']->id;
        $this->getJson($url.'/blockers')->assertOk()->assertJsonCount(1, 'items');
        $this->getJson($url.'/blockers?state=resolved')->assertOk()->assertJsonCount(1, 'items');
        $this->getJson($url.'/readiness-history')->assertOk()->assertJsonCount(1, 'items');
        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? ''])
            ->get('/alliance/transfers/readiness')->assertOk()->assertJsonPath('props.participants.items.0.activeBlockerCount', 1)
            ->assertJsonPath('props.participants.items.0.resolvedBlockerCount', 1)->assertJsonPath('props.participants.items.0.readinessTransitionCount', 1);
    }

    public function test_history_uses_the_exact_scope_indexes_and_a_bounded_probe(): void
    {
        $f = $this->fixture();
        $this->seedHistory($f, 60, 0, 60);
        $hydrated = 0;
        TransferBlocker::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId]);
        $this->getJson('/alliance/transfers/'.$f['plan']->id.'/participants/'.$f['participant']->id.'/blockers')->assertOk()->assertJsonCount(25, 'items');
        self::assertSame(26, $hydrated);
        $indexes = DB::table('pg_indexes')->whereIn('indexname', ['transfer_readiness_history_page', 'transfer_blocker_history_page'])->pluck('indexdef', 'indexname');
        self::assertCount(2, $indexes);
        foreach ($indexes as $definition) {
            self::assertStringContainsString('alliance_id, transfer_plan_id, transfer_participant_id', $definition);
            self::assertStringContainsString('created_at, id', $definition);
        }
    }

    public function test_invalid_cursor_positions_fail_validation_instead_of_reaching_postgresql(): void
    {
        $f = $this->fixture();
        $scope = 'transfer-blockers|'.$f['alliance']->allianceId.'|'.$f['plan']->id.'|'.$f['participant']->id.'|active';
        $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId]);
        $url = '/alliance/transfers/'.$f['plan']->id.'/participants/'.$f['participant']->id.'/blockers';
        foreach ([['at' => '2026-02-30 12:00:00.000000', 'id' => (string) Str::ulid()], ['at' => 'bad', 'id' => (string) Str::ulid()], ['at' => now()->format('Y-m-d H:i:s.u'), 'id' => 42]] as $position) {
            $cursor = app(ScopedCursorCodec::class)->encode($scope, $position);
            $this->getJson($url.'?'.http_build_query(['cursor' => $cursor]))->assertUnprocessable()->assertJsonValidationErrors('cursor');
        }
    }

    public function test_visual_history_fixture_creates_independent_real_project_scopes(): void
    {
        TransferWorkflowHistoryVisualFixture::seed();
        $alliances = [];
        foreach (['desktop', 'mobile'] as $project) {
            $this->flushSession();
            $user = User::query()->where('email', 'transfer-history-'.$project.'@example.test')->sole();
            $actor = Player::query()->where('user_id', $user->id)->sole();
            $participant = TransferParticipant::query()->where('player_id', $actor->id)->sole();
            $alliances[] = $participant->alliance_id;
            $this->actingAs($user)->withSession(['players.selected_id' => (string) $actor->id]);
            $url = '/alliance/transfers/'.$participant->transfer_plan_id.'/participants/'.$participant->id;
            $this->getJson($url.'/blockers')->assertOk()->assertJsonCount(25, 'items');
            $this->getJson($url.'/readiness-history')->assertOk()->assertJsonCount(25, 'items');
            self::assertSame(31, DB::table('transfer_blockers')->where('transfer_participant_id', $participant->id)->where('state', 'active')->count());
            self::assertSame(53, DB::table('transfer_blockers')->where('transfer_participant_id', $participant->id)->where('state', 'resolved')->count());
            self::assertSame(61, DB::table('transfer_readiness_transitions')->where('transfer_participant_id', $participant->id)->count());
        }
        self::assertCount(2, array_unique($alliances));
    }

    public function test_canonical_history_rows_require_a_timestamp_for_reachable_continuation(): void
    {
        $f = $this->fixture();
        foreach (['transfer_blockers', 'transfer_readiness_transitions'] as $table) {
            try {
                DB::transaction(function () use ($f, $table): void {
                    DB::table($table)->insert(['id' => (string) Str::ulid(), 'alliance_id' => $f['alliance']->allianceId,
                        'transfer_plan_id' => $f['plan']->id, 'transfer_participant_id' => $f['participant']->id,
                        'created_at' => null, ...($table === 'transfer_blockers' ? ['summary' => 'Undated'] : ['to_state' => 'preparing'])]);
                });
                self::fail('An undated workflow history row cannot have a valid continuation key.');
            } catch (QueryException $exception) {
                self::assertSame('23502', (string) $exception->getCode());
            }
        }
    }

    /** @param array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant,user:User} $f */
    private function seedHistory(array $f, int $active, int $resolved, int $transitions): void
    {
        $scope = ['alliance_id' => $f['alliance']->allianceId, 'transfer_plan_id' => (string) $f['plan']->id,
            'transfer_participant_id' => (string) $f['participant']->id];
        DB::table('transfer_readiness_transitions')->where($scope)->delete();
        foreach (['active' => $active, 'resolved' => $resolved] as $state => $count) {
            for ($i = 0; $i < $count; $i++) {
                DB::table('transfer_blockers')->insert($scope + ['id' => (string) Str::ulid(), 'state' => $state,
                    'summary' => $state.' blocker '.$i, 'details' => 'Private history '.$i,
                    'created_by_player_id' => $f['actor']->playerId,
                    'resolved_by_player_id' => $state === 'resolved' ? $f['actor']->playerId : null,
                    'resolved_at' => $state === 'resolved' ? now() : null,
                    'created_at' => now()->subDays($state === 'active' ? 10 : 1)->startOfSecond(), 'updated_at' => now()]);
            }
        }
        for ($i = 0; $i < $transitions; $i++) {
            DB::table('transfer_readiness_transitions')->insert($scope + ['id' => (string) Str::ulid(),
                'from_state' => 'not_started', 'to_state' => 'preparing', 'actor_player_id' => $f['actor']->playerId,
                'created_at' => now()->subDay()->startOfSecond()]);
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
