<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Queries;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Queries\KingdomAdministratorAssignments;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Queries\KingdomOperationsRolePolicy;

final readonly class KingdomGovernanceHealthQuery
{
    public function __construct(private KingdomOperationsRolePolicy $operationsPolicy, private KingdomAdministratorAssignments $administrators) {}

    /** @return array{status:string,issues:list<array{severity:string,code:string,message:string,repairable:bool}>} */
    public function forKingdom(string $kingdomId): array
    {
        $issues = [];
        $roles = KingdomRole::query()->where('kingdom_id', $kingdomId)->whereIn('key', array_column(DefaultKingdomRole::cases(), 'value'))->limit(3)->get(['id', 'key', 'name', 'archived_at'])->keyBy('key');
        foreach (DefaultKingdomRole::cases() as $template) {
            $role = $roles->get($template->value);
            if (! $role instanceof KingdomRole || $role->archived_at !== null) {
                $issues[] = ['severity' => 'critical', 'code' => 'system_role_missing', 'message' => "System role {$template->name()} is missing or archived.", 'repairable' => true];
            }
        }
        $administrator = $roles->get(DefaultKingdomRole::Administrator->value);
        if ($administrator instanceof KingdomRole) {
            if (! $this->administrators->effective($kingdomId)->exists()) {
                $issues[] = ['severity' => 'critical', 'code' => 'no_effective_administrator', 'message' => 'The Kingdom has no effective administrator.', 'repairable' => false];
            }
            if ($this->policyDrift($administrator, KingdomPermission::ownerKey(), [KingdomPermission::RoleManage->key()])) {
                $issues[] = ['severity' => 'warning', 'code' => 'governance_policy_drift', 'message' => 'The Kingdom Admin Governance permission policy differs from the declared system policy.', 'repairable' => true];
            }
        }
        foreach (DefaultKingdomRole::cases() as $template) {
            $expected = array_map(static fn (OperationsPermission $permission): string => $permission->key(), $this->operationsPolicy->permissions($template));
            $roleKey = $template->value;
            $role = $roles->get($roleKey);
            if (! $role instanceof KingdomRole) {
                continue;
            }
            if ($this->policyDrift($role, OperationsPermission::ownerKey(), $expected)) {
                $issues[] = ['severity' => 'warning', 'code' => 'operations_policy_drift', 'message' => "Operations permission policy drift detected for {$template->name()}.", 'repairable' => true];
            }
        }
        if (KingdomRole::query()->where('kingdom_id', $kingdomId)->whereHas('permissions', static fn ($query) => $query->whereNull('permissions.owner_key'))->exists()) {
            $issues[] = ['severity' => 'warning', 'code' => 'unowned_permission', 'message' => 'At least one Kingdom role references a permission without an owning context.', 'repairable' => false];
        }
        if (KingdomRoleAssignment::query()->where('kingdom_id', $kingdomId)->whereNull('revoked_at')->whereHas('role', static fn ($query) => $query->whereNotNull('archived_at'))->exists()) {
            $issues[] = ['severity' => 'warning', 'code' => 'archived_role_assignment', 'message' => 'An archived custom role still has an unrevoked assignment.', 'repairable' => false];
        }
        $soon = now()->addDay();
        if ($administrator instanceof KingdomRole && $this->administrators->effective($kingdomId)->whereNotNull('expires_at')->where('expires_at', '<=', $soon)->exists()) {
            $permanentAdmin = $this->administrators->effective($kingdomId)->whereNull('expires_at')->exists();
            if (! $permanentAdmin) {
                $issues[] = ['severity' => 'critical', 'code' => 'administrator_expiry_risk', 'message' => 'All effective Kingdom administrator authority is time-bounded and at least one assignment expires within 24 hours.', 'repairable' => false];
            }
        }
        $status = collect($issues)->contains(static fn (array $issue): bool => $issue['severity'] === 'critical') ? 'critical' : ($issues === [] ? 'healthy' : 'degraded');

        return ['status' => $status, 'issues' => $issues];
    }

    /** @param list<string> $expected */
    private function policyDrift(KingdomRole $role, string $owner, array $expected): bool
    {
        $permissions = $role->permissions()->where('permissions.owner_key', $owner);

        return (clone $permissions)->whereIn('permissions.key', $expected)->count() !== count($expected)
            || (clone $permissions)->whereNotIn('permissions.key', $expected)->exists();
    }
}
