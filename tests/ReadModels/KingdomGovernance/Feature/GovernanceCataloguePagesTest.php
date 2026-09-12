<?php

declare(strict_types=1);

namespace Tests\ReadModels\KingdomGovernance\Feature;

use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\ReadModels\KingdomGovernance\Queries\KingdomGovernanceProjectionQuery;
use App\ReadModels\KingdomGovernance\Queries\KingdomGovernanceTimelineQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ReadModels\KingdomGovernance\Support\GovernanceCatalogueFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class GovernanceCataloguePagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string}> */
    public static function catalogues(): iterable
    {
        foreach (['roles', 'assignments', 'holders', 'players', 'role-choices', 'history'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('catalogues')]
    public function test_every_record_is_reachable_with_bounded_queries_and_private_history_is_never_projected(string $kind): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player((int) $factory->authUser()->id, 61641);
        $fixture = GovernanceCatalogueFixture::seed($actor);
        $cursor = null;
        $seen = [];
        $queries = app(KingdomGovernanceProjectionQuery::class);
        do {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $page = match ($kind) {
                'roles' => $queries->roles($actor->playerId, $actor->kingdomId, $cursor),
                'assignments' => $queries->assignments($actor->playerId, $actor->kingdomId, $cursor),
                'holders' => $queries->holders($actor->playerId, $actor->kingdomId, OperationsPermission::EventKingdomView->key(), $cursor),
                'players' => $queries->choices($actor->playerId, $actor->kingdomId, 'players', cursor: $cursor)['page'],
                'role-choices' => $queries->choices($actor->playerId, $actor->kingdomId, 'roles', cursor: $cursor)['page'],
                'history' => app(KingdomGovernanceTimelineQuery::class)->forKingdom($actor->playerId, $actor->kingdomId, $cursor),
                default => throw new \LogicException,
            };
            $sql = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();
            self::assertLessThanOrEqual(12, count($sql));
            self::assertLessThanOrEqual(25, count($page['items']));
            self::assertTrue(collect($sql)->contains(static fn (string $query): bool => str_contains($query, 'limit 26')));
            self::assertSame($cursor === null, $page['isFirstPage']);
            foreach ($page['items'] as $row) {
                $id = (string) ($kind === 'holders' ? $row['playerId'] : $row['id']);
                self::assertNotContains($id, $seen);
                $seen[] = $id;
                if ($kind === 'holders' && $id === $fixture['players'][60]) {
                    self::assertSame(62, $row['roleCount']);
                    self::assertArrayNotHasKey('roles', $row);
                }
                if ($kind === 'assignments') {
                    self::assertStringNotContainsString(GovernanceCatalogueFixture::PRIVATE_REASON, json_encode($row, JSON_THROW_ON_ERROR));
                    self::assertSame($row['role']['id'] === $fixture['viewer'] ? 'Catalogue viewer grant' : null, $row['reason']);
                }
                if ($kind === 'history') {
                    $encoded = json_encode($row, JSON_THROW_ON_ERROR);
                    self::assertStringNotContainsString(GovernanceCatalogueFixture::PRIVATE_REASON, $encoded);
                    self::assertStringNotContainsString('private-operator', $encoded);
                    self::assertStringNotContainsString('private bytes', $encoded);
                    self::assertArrayNotHasKey('userId', $row['actor']);
                }
            }
            $cursor = $page['nextCursor'];
        } while ($cursor !== null);
        self::assertGreaterThan(50, count($seen));
        self::assertSame($page['total'], count($seen));
    }

    /** @return iterable<string,array{string}> */
    public static function invalidScopes(): iterable
    {
        foreach (['actor', 'kind', 'kingdom', 'filter', 'tampered'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('invalidScopes')]
    public function test_continuations_cannot_cross_current_authority_or_catalogue_scope(string $case): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->unclaimedPlayer(61642);
        GovernanceCatalogueFixture::seed($actor);
        $other = $factory->unclaimedPlayer(61642);
        $adminRole = (string) DB::table('kingdom_roles')->where('kingdom_id', $actor->kingdomId)->where('key', DefaultKingdomRole::Administrator->value)->value('id');
        app(AssignKingdomRole::class)->handle($actor->playerId, $actor->kingdomId, $other->playerId, $adminRole);
        $foreign = $factory->unclaimedPlayer(61643);
        GovernanceCatalogueFixture::seed($foreign, 31);
        $query = app(KingdomGovernanceProjectionQuery::class);
        $cursor = $query->choices($actor->playerId, $actor->kingdomId, 'players')['page']['nextCursor'];
        self::assertNotNull($cursor);
        $this->expectException(ValidationException::class);
        $query->choices($case === 'kingdom' ? $foreign->playerId : ($case === 'actor' ? $other->playerId : $actor->playerId),
            $case === 'kingdom' ? $foreign->kingdomId : $actor->kingdomId, $case === 'kind' ? 'roles' : 'players',
            search: $case === 'filter' ? 'Governor' : '', cursor: $case === 'tampered' ? $cursor.'invalid' : $cursor);
    }

    public function test_current_choice_search_selected_identity_and_finite_frontier_remain_complete(): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->unclaimedPlayer(61644);
        $fixture = GovernanceCatalogueFixture::seed($actor);
        $query = app(KingdomGovernanceProjectionQuery::class);
        $selected = $fixture['players'][0];
        $first = $query->choices($actor->playerId, $actor->kingdomId, 'players', selectedId: $selected);
        self::assertSame($selected, $first['selected']['id'] ?? null);
        self::assertNotContains($selected, array_column($first['page']['items'], 'id'));
        $new = $factory->unclaimedPlayer(61644);
        $seen = array_column($first['page']['items'], 'id');
        $cursor = $first['page']['nextCursor'];
        while ($cursor !== null) {
            $next = $query->choices($actor->playerId, $actor->kingdomId, 'players', cursor: $cursor);
            array_push($seen, ...array_column($next['page']['items'], 'id'));
            $cursor = $next['page']['nextCursor'];
        }
        self::assertNotContains($new->playerId, $seen);
        self::assertContains($new->playerId, array_column($query->choices($actor->playerId, $actor->kingdomId, 'players')['page']['items'], 'id'));
        self::assertSame([$selected], array_column($query->choices($actor->playerId, $actor->kingdomId, 'players', search: 'Governor 000')['page']['items'], 'id'));
        $foreign = $factory->unclaimedPlayer(61645);
        self::assertNull($query->choices($actor->playerId, $actor->kingdomId, 'players', selectedId: $foreign->playerId)['selected']);
        DB::table('players')->where('id', $selected)->update(['canonical_player_id' => $fixture['players'][1]]);
        self::assertNull($query->choices($actor->playerId, $actor->kingdomId, 'players', selectedId: $selected)['selected']);
    }

    public function test_thousands_of_grants_collapse_before_holder_paging_and_revoked_actor_cannot_continue(): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->unclaimedPlayer(61646);
        $fixture = GovernanceCatalogueFixture::seed($actor);
        $rows = [];
        for ($i = 0; $i < 1001; $i++) {
            $rows[] = ['id' => (string) Str::ulid(), 'kingdom_id' => $actor->kingdomId, 'player_id' => $fixture['players'][60],
                'kingdom_role_id' => $fixture['viewer'], 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('kingdom_role_assignments')->insert($rows);
        $query = app(KingdomGovernanceProjectionQuery::class);
        $page = $query->holders($actor->playerId, $actor->kingdomId, OperationsPermission::EventKingdomView->key());
        self::assertSame(62, $page['total']);
        self::assertCount(25, $page['items']);
        self::assertSame(62, $page['items'][0]['roleCount']);
        $other = $factory->unclaimedPlayer(61646);
        $adminRole = (string) DB::table('kingdom_roles')->where('kingdom_id', $actor->kingdomId)->where('key', DefaultKingdomRole::Administrator->value)->value('id');
        app(AssignKingdomRole::class)->handle($actor->playerId, $actor->kingdomId, $other->playerId, $adminRole);
        app(RemoveKingdomRole::class)->handle($other->playerId, $actor->kingdomId, $fixture['administratorAssignment']);
        $this->expectException(AuthorizationException::class);
        $query->holders($actor->playerId, $actor->kingdomId, OperationsPermission::EventKingdomView->key(), $page['nextCursor']);
    }
}
