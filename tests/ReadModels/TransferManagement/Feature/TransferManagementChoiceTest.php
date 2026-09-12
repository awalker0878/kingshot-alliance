<?php

declare(strict_types=1);

namespace Tests\ReadModels\TransferManagement\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\Players\Http\Middleware\HandleInertiaRequests;
use App\Contexts\GameWorld\Players\Models\Player;
use App\ReadModels\TransferManagement\Enums\TransferChoiceKind;
use App\ReadModels\TransferManagement\Queries\TransferManagementChoiceQuery;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ReadModels\TransferManagement\Fixtures\TransferChoiceVisualFixture;
use Tests\ReadModels\TransferManagement\Support\TransferWorkspaceFixture;
use Tests\TestCase;

final class TransferManagementChoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_does_not_preload_all_coordinator_and_roster_options(): void
    {
        $f = TransferWorkspaceFixture::create();
        $this->choices($f);
        $rosterHydrated = 0;
        AllianceRosterEntry::retrieved(static function () use (&$rosterHydrated): void {
            $rosterHydrated++;
        });
        $response = $this->actingAs($f->user)->withSession(['players.selected_id' => $f->actor->playerId])
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(request()) ?? '')
            ->get('/alliance/transfers/manage')->assertOk();

        self::assertSame(0, $rosterHydrated, 'Management must not hydrate an entire selector audience.');
        self::assertArrayNotHasKey('players', $response->json('props'));
        self::assertArrayNotHasKey('rosterOptions', $response->json('props'));
    }

    public static function kinds(): iterable
    {
        foreach (TransferChoiceKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    #[DataProvider('kinds')]
    public function test_every_scoped_option_is_reachable_and_selected_values_are_resolved_independently(TransferChoiceKind $kind): void
    {
        $f = TransferWorkspaceFixture::create();
        $ids = $this->choices($f)[$kind->value];
        $foreign = TransferWorkspaceFixture::create();
        $foreignId = $this->choices($foreign, 1)[$kind->value][0];
        $query = app(TransferManagementChoiceQuery::class);
        $plan = $kind === TransferChoiceKind::Windows ? null : (string) $f->plan->id;
        self::assertNull($query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, selectedId: $foreignId)['selected']);
        $cursor = null;
        $seen = [];
        do {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $result = $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, 'Selection', $cursor, $ids[64]);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            self::assertSame(65, $result['total']);
            self::assertSame($ids[64], $result['selected']['id']);
            self::assertSame('Selection 064', $result['selected']['name']);
            self::assertLessThanOrEqual(25, count($result['page']['items']));
            self::assertSame($cursor === null, $result['page']['isFirstPage']);
            self::assertSame($result['page']['nextCursor'] !== null, $result['page']['hasMore']);
            self::assertLessThanOrEqual(18, count($queries));
            foreach ($queries as $sql) {
                if (! str_contains($sql['query'], 'transfer_choices') || str_starts_with($sql['query'], 'select count(') || str_starts_with($sql['query'], 'select max(')) {
                    continue;
                }
                self::assertMatchesRegularExpression('/limit (1|26)\b/', $sql['query'], 'All option materialization must be bounded.');
            }
            array_push($seen, ...array_column($result['page']['items'], 'id'));
            $cursor = $result['page']['nextCursor'];
        } while ($cursor !== null);
        self::assertSame($ids, $seen);
        self::assertCount(65, array_unique($seen));
        $search = $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, 'Selection 060', selectedId: $ids[64]);
        self::assertSame([$ids[60]], array_column($search['page']['items'], 'id'));
        self::assertSame($ids[64], $search['selected']['id']);
        self::assertSame(1, $search['total']);
        self::assertSame([], $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, '%_')['page']['items']);
    }

    #[DataProvider('kinds')]
    public function test_deleted_boundaries_and_changed_search_names_do_not_require_a_live_cursor_row(TransferChoiceKind $kind): void
    {
        $f = TransferWorkspaceFixture::create();
        $ids = $this->choices($f)[$kind->value];
        $query = app(TransferManagementChoiceQuery::class);
        $plan = $kind === TransferChoiceKind::Windows ? null : (string) $f->plan->id;
        $result = $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, 'Selection');
        $table = match ($kind) {
            TransferChoiceKind::Windows => 'transfer_windows', TransferChoiceKind::Coordinators => 'players', TransferChoiceKind::Roster => 'alliance_roster_entries'
        };
        $nameColumn = match ($kind) {
            TransferChoiceKind::Windows => 'label', TransferChoiceKind::Coordinators => 'current_name', TransferChoiceKind::Roster => 'observed_name'
        };
        if ($kind === TransferChoiceKind::Coordinators) {
            DB::table('alliance_memberships')->where('alliance_id', $f->alliance->allianceId)->where('player_id', $ids[24])->delete();
        } else {
            DB::table($table)->where('id', $ids[24])->delete();
        }
        DB::table($table)->where('id', $ids[25])->update([$nameColumn => 'Selection renamed before prior names']);
        $next = $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, 'Selection', $result['page']['nextCursor']);
        self::assertSame(array_slice($ids, 25, 25), array_column($next['page']['items'], 'id'));
        self::assertSame(64, $next['total']);
        self::assertNull($query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, selectedId: $ids[24])['selected']);
    }

    #[DataProvider('kinds')]
    public function test_cursor_scope_includes_current_actor_alliance_kind_plan_and_search(TransferChoiceKind $kind): void
    {
        $f = TransferWorkspaceFixture::create();
        $ids = $this->choices($f)[$kind->value];
        $query = app(TransferManagementChoiceQuery::class);
        $plan = $kind === TransferChoiceKind::Windows ? null : (string) $f->plan->id;
        $cursor = $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan)['page']['nextCursor'];
        self::assertIsString($cursor);
        $foreign = TransferWorkspaceFixture::create();
        $otherActor = Player::query()->create(['current_kingdom_id' => $f->actor->kingdomId, 'user_id' => $f->user->id, 'current_name' => 'Other manager', 'game_player_id' => 'choices-manager-'.$f->plan->id]);
        AllianceMembership::query()->create(['alliance_id' => $f->alliance->allianceId, 'player_id' => $otherActor->id, 'rank' => 'r4', 'status' => 'active', 'joined_at' => now()]);
        foreach ([
            fn () => $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, 'different', $cursor),
            fn () => $query->page($foreign->actor->playerId, $foreign->alliance->allianceId, $kind, $kind === TransferChoiceKind::Windows ? null : (string) $foreign->plan->id, cursor: $cursor),
            fn () => $query->page((string) $otherActor->id, $f->alliance->allianceId, $kind, $plan, cursor: $cursor),
            fn () => $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, cursor: 'tampered'),
            fn () => $query->page($f->actor->playerId, $f->alliance->allianceId, $kind === TransferChoiceKind::Windows ? TransferChoiceKind::Roster : TransferChoiceKind::Windows, $kind === TransferChoiceKind::Windows ? (string) $f->plan->id : null, cursor: $cursor),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('A choice cursor cannot authorize another request scope.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('cursor', $exception->errors());
            }
        }
        AllianceMembership::query()->where('alliance_id', $f->alliance->allianceId)->where('player_id', $f->actor->playerId)->update(['rank' => 'r1']);
        $this->expectException(AuthorizationException::class);
        $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, $plan, cursor: $cursor, selectedId: $ids[64]);
    }

    public function test_noncurrent_membership_and_roster_states_are_not_choices_and_expired_plan_access_is_rejected(): void
    {
        $f = TransferWorkspaceFixture::create();
        $ids = $this->choices($f, 3);
        DB::table('alliance_memberships')->where('player_id', $ids['coordinators'][1])->update(['status' => 'suspended']);
        DB::table('alliance_roster_entries')->where('id', $ids['roster'][1])->update(['state' => 'left']);
        DB::table('alliance_roster_entries')->where('id', $ids['roster'][2])->update(['state' => 'tracked']);
        $query = app(TransferManagementChoiceQuery::class);
        $coordinators = $query->page($f->actor->playerId, $f->alliance->allianceId, TransferChoiceKind::Coordinators, (string) $f->plan->id, 'Selection', selectedId: $ids['coordinators'][1]);
        self::assertSame([$ids['coordinators'][0], $ids['coordinators'][2]], array_column($coordinators['page']['items'], 'id'));
        self::assertNull($coordinators['selected']);
        $roster = $query->page($f->actor->playerId, $f->alliance->allianceId, TransferChoiceKind::Roster, (string) $f->plan->id, selectedId: $ids['roster'][1]);
        self::assertSame([$ids['roster'][0], $ids['roster'][2]], array_column($roster['page']['items'], 'id'));
        self::assertNull($roster['selected']);
        $f->plan->forceFill(['state' => 'closed'])->save();
        $this->expectException(ModelNotFoundException::class);
        $query->page($f->actor->playerId, $f->alliance->allianceId, TransferChoiceKind::Roster, (string) $f->plan->id);
    }

    public function test_http_endpoint_uses_current_governor_scope_and_validates_query_shape(): void
    {
        $f = TransferWorkspaceFixture::create();
        $ids = $this->choices($f, 2);
        $foreign = TransferWorkspaceFixture::create();
        $foreignIds = $this->choices($foreign, 1);
        $this->actingAs($f->user)->withSession(['players.selected_id' => $f->actor->playerId]);
        $url = '/alliance/transfers/manage/choices/roster?'.http_build_query(['plan' => $f->plan->id, 'selected' => $ids['roster'][1]]);
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'page.items')->assertJsonPath('selected.id', $ids['roster'][1])->assertJsonPath('total', 2)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson($url.'&alliance='.$foreign->alliance->allianceId)->assertOk()->assertJsonPath('page.items.0.id', $ids['roster'][0]);
        $this->getJson('/alliance/transfers/manage/choices/roster?'.http_build_query(['plan' => $foreign->plan->id, 'selected' => $foreignIds['roster'][0]]))->assertNotFound();
        $this->getJson('/alliance/transfers/manage/choices/unknown')->assertNotFound();
        $this->getJson('/alliance/transfers/manage/choices/windows?q[]='.'x')->assertUnprocessable();
        $this->getJson('/alliance/transfers/manage/choices/windows?q='.str_repeat('x', 161))->assertUnprocessable();
        $this->getJson('/alliance/transfers/manage/choices/coordinators')->assertUnprocessable();
        DB::table('kingdoms')->where('id', $f->actor->kingdomId)->update(['status' => 'archived']);
        $this->getJson($url)->assertForbidden();
    }

    public function test_plan_boundaries_and_choice_indexes_are_part_of_the_current_schema(): void
    {
        $f = TransferWorkspaceFixture::create();
        $ids = $this->choices($f, 30);
        $query = app(TransferManagementChoiceQuery::class);
        $cursor = $query->page($f->actor->playerId, $f->alliance->allianceId, TransferChoiceKind::Roster, (string) $f->plan->id)['page']['nextCursor'];
        $plan = $f->plan->replicate();
        $plan->transfer_window_id = $ids['windows'][0];
        $plan->save();
        try {
            $query->page($f->actor->playerId, $f->alliance->allianceId, TransferChoiceKind::Roster, (string) $plan->id, cursor: $cursor);
            self::fail('A cursor from another mutable plan must not be accepted.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
        foreach (['alliance_member_choice_cursor' => '(alliance_id, status, player_id)',
            'alliance_roster_choice_cursor' => '(alliance_id, id, state)', 'transfer_window_choice_cursor' => '(alliance_id, id)'] as $name => $columns) {
            $index = DB::table('pg_indexes')->where('schemaname', DB::raw('current_schema()'))->where('indexname', $name)->value('indexdef');
            self::assertIsString($index);
            self::assertStringContainsString($columns, $index);
        }
    }

    public function test_malformed_but_authentic_cursor_positions_are_rejected(): void
    {
        $f = TransferWorkspaceFixture::create();
        $ids = $this->choices($f, 2)['windows'];
        $scope = implode('|', ['transfer-choices', $f->actor->playerId, $f->alliance->allianceId, 'windows', '', hash('sha256', '')]);
        foreach ([['after' => 'invalid', 'through' => $ids[1]], ['after' => $ids[1], 'through' => $ids[0]], ['after' => $ids[0], 'through' => $ids[1], 'extra' => 1], ['after' => $ids[0], 'through' => null]] as $position) {
            $cursor = app(ScopedCursorCodec::class)->encode($scope, $position);
            try {
                app(TransferManagementChoiceQuery::class)->page($f->actor->playerId, $f->alliance->allianceId, TransferChoiceKind::Windows, cursor: $cursor);
                self::fail('Invalid decoded position must fail closed.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('cursor', $exception->errors());
            }
        }
    }

    public function test_browser_projects_have_distinct_authorized_choice_fixture_scopes(): void
    {
        TransferChoiceVisualFixture::seed();
        $seen = [];
        foreach (['desktop', 'mobile'] as $project) {
            $user = User::query()->where('email', 'transfer-choices-'.$project.'@example.test')->sole();
            $actor = Player::query()->where('user_id', $user->id)->sole();
            $allianceId = (string) AllianceMembership::query()->where('player_id', $actor->id)->sole()->alliance_id;
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->sole();
            $query = app(TransferManagementChoiceQuery::class);
            foreach (TransferChoiceKind::cases() as $kind) {
                $result = $query->page((string) $actor->id, $allianceId, $kind, $kind === TransferChoiceKind::Windows ? null : (string) $plan->id, 'Fixture choice');
                self::assertSame(55, $result['total']);
                self::assertCount(25, $result['page']['items']);
                self::assertTrue($result['page']['hasMore']);
            }
            $seen[] = $allianceId;
        }
        self::assertNotSame($seen[0], $seen[1]);
    }

    /** @return array{windows:list<string>,coordinators:list<string>,roster:list<string>} */
    private function choices(TransferWorkspaceFixture $f, int $count = 65): array
    {
        $ids = ['windows' => [], 'coordinators' => [], 'roster' => []];
        $base = TransferWindow::query()->findOrFail($f->plan->transfer_window_id);
        for ($i = 0; $i < $count; $i++) {
            $name = sprintf('Selection %03d', $i);
            $player = Player::query()->create(['current_kingdom_id' => $f->actor->kingdomId, 'current_name' => $name,
                'game_player_id' => 'selection-'.$f->plan->id.'-'.$i]);
            AllianceMembership::query()->create(['alliance_id' => $f->alliance->allianceId, 'player_id' => $player->id,
                'rank' => 'r3', 'status' => 'active', 'joined_at' => now()]);
            $roster = AllianceRosterEntry::query()->create(['alliance_id' => $f->alliance->allianceId, 'player_id' => $player->id,
                'observed_name' => $name, 'state' => 'active', 'source' => 'manual']);
            $window = $base->replicate();
            $window->label = $name;
            $window->save();
            $ids['coordinators'][] = (string) $player->id;
            $ids['roster'][] = (string) $roster->id;
            $ids['windows'][] = (string) $window->id;
        }

        return $ids;
    }
}
