<?php

declare(strict_types=1);

namespace Tests\ReadModels\KingdomGovernance\Feature;

use App\Contexts\GameWorld\Governance\Actions\ReconcileKingdomRolePermissions;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\ReadModels\KingdomGovernance\Queries\KingdomGovernanceHealthQuery;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use App\Workflows\KingdomGovernance\Actions\ReconcileKingdomGovernancePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomGovernanceHealthBoundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_custom_catalogue_does_not_expand_health_and_owner_policy_repair_restores_health(): void
    {
        $actor = app(ScenarioFactory::class)->unclaimedPlayer(61639);
        $roles = app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);
        $custom = [];
        for ($i = 0; $i < 1001; $i++) {
            $custom[] = ['id' => (string) Str::ulid(), 'kingdom_id' => $actor->kingdomId, 'key' => 'historical-'.$i,
                'name' => 'Historical '.$i, 'is_system' => false, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('kingdom_roles')->insert($custom);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $health = app(KingdomGovernanceHealthQuery::class)->forKingdom($actor->kingdomId);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        self::assertSame('healthy', $health['status']);
        self::assertLessThanOrEqual(15, count($queries));
        $materialized = array_values(array_filter($queries, static fn (string $query): bool => str_starts_with($query, 'select "id", "key", "name", "archived_at" from "kingdom_roles"')));
        self::assertCount(1, $materialized);
        self::assertStringContainsString('limit 3', $materialized[0]);
        self::assertFalse(collect($queries)->contains(static fn (string $query): bool => str_contains($query, 'select "permissions".*')));
        app(ReconcileKingdomRolePermissions::class)->handle($actor->kingdomId, OperationsPermission::ownerKey(), [$roles->viewerRoleId => []]);
        self::assertContains('operations_policy_drift', array_column(app(KingdomGovernanceHealthQuery::class)->forKingdom($actor->kingdomId)['issues'], 'code'));
        app(ReconcileKingdomGovernancePolicy::class)->handle($actor->playerId, $actor->kingdomId);
        self::assertSame('healthy', app(KingdomGovernanceHealthQuery::class)->forKingdom($actor->kingdomId)['status']);
    }

    public function test_alias_administrator_does_not_hide_absent_current_authority(): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->unclaimedPlayer(61640);
        $canonical = $factory->unclaimedPlayer(61640);
        app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);
        DB::table('players')->where('id', $actor->playerId)->update(['canonical_player_id' => $canonical->playerId]);
        $health = app(KingdomGovernanceHealthQuery::class)->forKingdom($actor->kingdomId);
        self::assertSame('critical', $health['status']);
        self::assertContains('no_effective_administrator', array_column($health['issues'], 'code'));
    }
}
