<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Queries;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\Operations\Access\Enums\OperationsPermission;

final class KingdomGovernanceHealthQuery
{
    /** @return array{status:string,issues:list<array{severity:string,code:string,message:string,repairable:bool}>} */
    public function forKingdom(string $kingdomId): array
    {
        $issues = [];
        $roles = KingdomRole::query()->where('kingdom_id', $kingdomId)->with('permissions')->get()->keyBy('key');
        foreach (DefaultKingdomRole::cases() as $template) {
            $role = $roles->get($template->value);
            if (! $role instanceof KingdomRole || $role->archived_at !== null) {
                $issues[] = ['severity' => 'critical', 'code' => 'system_role_missing', 'message' => "System role {$template->name()} is missing or archived.", 'repairable' => true];
            }
        }
        $administrator = $roles->get(DefaultKingdomRole::Administrator->value);
        if ($administrator instanceof KingdomRole) {
            if (! KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('kingdom_role_id', $administrator->id)->exists()) {
                $issues[] = ['severity' => 'critical', 'code' => 'no_effective_administrator', 'message' => 'The Kingdom has no effective administrator.', 'repairable' => false];
            }
            $governanceKeys = $administrator->permissions->where('owner_key', KingdomPermission::ownerKey())->pluck('key')->map('strval')->sort()->values()->all();
            if ($governanceKeys !== [KingdomPermission::RoleManage->key()]) {
                $issues[] = ['severity' => 'warning', 'code' => 'governance_policy_drift', 'message' => 'The Kingdom Admin Governance permission policy differs from the declared system policy.', 'repairable' => true];
            }
        }
        $expectedOperations = [
            DefaultKingdomRole::Administrator->value => [OperationsPermission::EventKingdomCreate->key(), OperationsPermission::EventKingdomManage->key(), OperationsPermission::EventKingdomView->key(), OperationsPermission::TerritoryKingdomManage->key(), OperationsPermission::TerritoryKingdomView->key()],
            DefaultKingdomRole::EventCoordinator->value => [OperationsPermission::EventKingdomCreate->key(), OperationsPermission::EventKingdomManage->key(), OperationsPermission::EventKingdomView->key(), OperationsPermission::TerritoryKingdomManage->key(), OperationsPermission::TerritoryKingdomView->key()],
            DefaultKingdomRole::Viewer->value => [OperationsPermission::EventKingdomView->key(), OperationsPermission::TerritoryKingdomView->key()],
        ];
        foreach ($expectedOperations as $roleKey => $expected) {
            $role = $roles->get($roleKey);
            if (! $role instanceof KingdomRole) {
                continue;
            }
            $actual = $role->permissions->where('owner_key', OperationsPermission::ownerKey())->pluck('key')->map('strval')->sort()->values()->all();
            sort($expected);
            if ($actual !== $expected) {
                $issues[] = ['severity' => 'warning', 'code' => 'operations_policy_drift', 'message' => "Operations permission policy drift detected for {$role->name}.", 'repairable' => true];
            }
        }
        if (KingdomRole::query()->where('kingdom_id', $kingdomId)->whereHas('permissions', static fn ($query) => $query->whereNull('permissions.owner_key'))->exists()) {
            $issues[] = ['severity' => 'warning', 'code' => 'unowned_permission', 'message' => 'At least one Kingdom role references a permission without an owning context.', 'repairable' => false];
        }
        if (KingdomRoleAssignment::query()->where('kingdom_id', $kingdomId)->whereNull('revoked_at')->whereHas('role', static fn ($query) => $query->whereNotNull('archived_at'))->exists()) {
            $issues[] = ['severity' => 'warning', 'code' => 'archived_role_assignment', 'message' => 'An archived custom role still has an unrevoked assignment.', 'repairable' => false];
        }
        $soon = now()->addDay();
        if ($administrator instanceof KingdomRole && KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('kingdom_role_id', $administrator->id)->whereNotNull('expires_at')->where('expires_at', '<=', $soon)->exists()) {
            $permanentAdmin = KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('kingdom_role_id', $administrator->id)->whereNull('expires_at')->exists();
            if (! $permanentAdmin) {
                $issues[] = ['severity' => 'critical', 'code' => 'administrator_expiry_risk', 'message' => 'All effective Kingdom administrator authority is time-bounded and at least one assignment expires within 24 hours.', 'repairable' => false];
            }
        }
        $status = collect($issues)->contains(static fn (array $issue): bool => $issue['severity'] === 'critical') ? 'critical' : ($issues === [] ? 'healthy' : 'degraded');
        return ['status' => $status, 'issues' => $issues];
    }
}
