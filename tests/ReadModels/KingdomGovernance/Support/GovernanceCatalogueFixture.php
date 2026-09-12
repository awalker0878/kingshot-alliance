<?php

declare(strict_types=1);

namespace Tests\ReadModels\KingdomGovernance\Support;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GovernanceCatalogueFixture
{
    public const PRIVATE_REASON = 'Private recovery incident and operator details must not reach Kingdom history.';

    /** @return array{roles:list<string>,players:list<string>,viewer:string,administratorAssignment:string} */
    public static function seed(PlayerReference $actor, int $count = 61): array
    {
        $defaults = app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);
        $permissionId = DB::table('permissions')->where('key', OperationsPermission::EventKingdomView->key())->value('id');
        $roles = $players = $assignments = $permissions = $audit = [];
        for ($i = 0; $i < $count; $i++) {
            $roleId = strtolower((string) Str::ulid());
            $playerId = strtolower((string) Str::ulid());
            $roles[] = ['id' => $roleId, 'kingdom_id' => $actor->kingdomId, 'key' => sprintf('catalogue-role-%03d', $i),
                'name' => sprintf('Governance Role %03d', $i), 'description' => 'Catalogue role', 'is_system' => false, 'created_at' => now(), 'updated_at' => now()];
            $players[] = ['id' => $playerId, 'current_kingdom_id' => $actor->kingdomId, 'current_name' => sprintf('Governance Governor %03d', $i),
                'game_player_id' => null, 'created_at' => now(), 'updated_at' => now()];
            $assignments[] = ['id' => strtolower((string) Str::ulid()), 'kingdom_id' => $actor->kingdomId,
                'player_id' => $playerId, 'kingdom_role_id' => $defaults->viewerRoleId, 'assigned_by_player_id' => $actor->playerId, 'reason' => 'Catalogue viewer grant', 'created_at' => now(), 'updated_at' => now()];
            $permissions[] = ['kingdom_role_id' => $roleId, 'permission_id' => $permissionId];
            $audit[] = ['id' => strtolower((string) Str::ulid()), 'event' => 'kingdom.administrator_recovered',
                'actor_user_id' => $actor->userId, 'subject_type' => 'kingdom_role_assignment', 'subject_id' => $assignments[$i]['id'],
                'metadata' => json_encode(['kingdom_id' => $actor->kingdomId, 'role_id' => $roleId, 'target_player_id' => $playerId,
                    'reason' => self::PRIVATE_REASON, 'operator_email' => 'private-operator@example.test', 'diagnostic' => str_repeat('private bytes ', 1000)], JSON_THROW_ON_ERROR), 'created_at' => now()];
        }
        DB::table('kingdom_roles')->insert($roles);
        DB::table('players')->insert($players);
        DB::table('kingdom_role_permissions')->insert($permissions);
        DB::table('kingdom_role_assignments')->insert($assignments);
        DB::table('audit_events')->insert($audit);
        $roleIds = array_column($roles, 'id');
        $playerIds = array_column($players, 'id');
        $extra = [];
        foreach ($roleIds as $roleId) {
            $extra[] = ['id' => strtolower((string) Str::ulid()), 'kingdom_id' => $actor->kingdomId, 'player_id' => $playerIds[$count - 1],
                'kingdom_role_id' => $roleId, 'reason' => self::PRIVATE_REASON, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('kingdom_role_assignments')->insert($extra);

        return ['roles' => $roleIds, 'players' => $playerIds, 'viewer' => $defaults->viewerRoleId, 'administratorAssignment' => $defaults->assignmentId];
    }
}
