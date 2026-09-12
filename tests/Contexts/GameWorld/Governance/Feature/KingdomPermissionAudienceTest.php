<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\Governance\Feature;

use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomPermissionAudienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_ids_are_paged_before_hydration_and_a_removed_boundary_still_continues(): void
    {
        [$owner, $role] = $this->owner();
        $players = [$owner->playerId];
        $factory = new ScenarioFactory;
        for ($i = 0; $i < 5; $i++) {
            $player = $factory->player($factory->account()->userId, 87104);
            $this->assign($player, $role);
            $this->assign($player, $role); // Multiple assignments must not consume page positions.
            $players[] = $player->playerId;
        }
        sort($players);
        $first = $this->page($owner, null, 2);
        self::assertSame(array_slice($players, 0, 2), $first);
        KingdomRoleAssignment::query()->where('player_id', $first[1])->delete();
        self::assertSame(array_slice($players, 2, 2), $this->page($owner, $first[1], 2));
        self::assertSame(array_slice($players, 4), $this->page($owner, $players[3], 2));
        self::assertSame([], $this->page($owner, $players[5], 2));
    }

    public function test_unclaimed_other_kingdom_archived_revoked_future_and_expired_authority_is_not_an_audience(): void
    {
        [$owner, $role] = $this->owner();
        $factory = new ScenarioFactory;
        $unclaimed = $factory->unclaimedPlayer(87104);
        $this->assign($unclaimed, $role);
        foreach (['revoked_at' => now(), 'effective_from' => now()->addDay(), 'expires_at' => now()->subDay()] as $field => $value) {
            $player = $factory->player($factory->account()->userId, 87104);
            $this->assign($player, $role)->update([$field => $value]);
        }
        $other = $factory->player($factory->account()->userId, 87105);
        app(BootstrapKingdomAdministrator::class)->handle($other->kingdomId, $other->playerId);
        self::assertSame([$owner->playerId], $this->page($owner, null, 20));
        KingdomRole::query()->whereKey($role)->update(['archived_at' => now()]);
        self::assertSame([], $this->page($owner, null, 20));
    }

    public function test_audience_workload_is_clamped_in_sql_and_does_not_eager_load_models(): void
    {
        [$owner] = $this->owner();
        $queries = [];
        DB::listen(static function (QueryExecuted $event) use (&$queries): void {
            $queries[] = $event->sql;
        });
        self::assertSame([$owner->playerId], $this->page($owner, null, 9000));
        self::assertCount(1, $queries);
        self::assertStringContainsString('select distinct "player_id"', $queries[0]);
        self::assertStringContainsString('limit 1000', $queries[0]);
        self::assertSame([$owner->playerId], $this->page($owner, null, 0));
        self::assertStringContainsString('limit 1', $queries[1]);
    }

    /** @return array{PlayerReference, string} */
    private function owner(): array
    {
        $factory = new ScenarioFactory;
        $owner = $factory->player($factory->account()->userId, 87104);
        app(BootstrapKingdomAdministrator::class)->handle($owner->kingdomId, $owner->playerId);

        return [$owner, (string) KingdomRoleAssignment::query()->where('player_id', $owner->playerId)->value('kingdom_role_id')];
    }

    private function assign(PlayerReference $player, string $role): KingdomRoleAssignment
    {
        return KingdomRoleAssignment::query()->create([
            'kingdom_id' => $player->kingdomId, 'player_id' => $player->playerId,
            'kingdom_role_id' => $role, 'effective_from' => now()->subDay(),
        ]);
    }

    /** @return list<string> */
    private function page(PlayerReference $owner, ?string $after, int $limit): array
    {
        return app(KingdomAuthorityFactsQuery::class)->playerIdsWithPermissionAfter(
            $owner->kingdomId, OperationsPermission::EventKingdomManage->value, $after, $limit,
        );
    }
}
