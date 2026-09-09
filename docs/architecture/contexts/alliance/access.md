# Alliance — Access

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Access`

Access owns Alliance permission vocabulary, specialist role semantics and Alliance authorization interpretation.

## Authority inputs

The actor is the active Player. Access evaluates the concrete Alliance membership/rank/specialist-role facts needed by the requested Alliance capability.

## Invariants

- Alliance permissions are Player-scoped and Alliance-scoped;
- Platform Administrator is not a game-domain bypass;
- authorization services interpret permissions but do not acquire database locks;
- protected write Actions revalidate mutable authority inside the owner-controlled transaction;
- Operations and Intelligence define their own permission meanings even when they consume Alliance facts.

AllianceRoleDelegation owns the grant policy shared by advisory preview and locked assignment. Custom grants, including another member and each bulk item, require current held target-role permissions. R5 may explicitly commission a provisioned system role on another member without acquiring its data access; this is not a self-assignment or custom-role bypass. Event Coordinator additionally requires R5 or a current assignment of that same role, because an empty Alliance permission list does not imply Operations grant authority. Removing a role requires role-management authority but not the permissions being revoked. AssignMembershipRole and RemoveMembershipRole remain authoritative. See [ADR-0022](../../adr/0022-bounded-alliance-delegation.md).

Updating or archiving an existing role acquires AllianceWriteState's exclusive Alliance scope before the administrator membership and role. This coordinates definition changes with all protected writers holding the ordinary shared Alliance scope, rather than only the administrator's membership. Current permission interpretation follows that lock acquisition; another Alliance retains independent execution. Audit/outbox and assignments remain atomic. See [ADR-0023](../../adr/0023-alliance-role-revocation-serialization.md).

AllianceRoleCatalogQuery owns bounded management and assignment-option reads. It returns 25 rows with scope/filter-bound encrypted cursors ordered by immutable key, literal name-prefix search and active/archive separation. Management permission/count facts use two queries. Current RoleManage authority protects both HTTP reads; options always exclude archived roles and use a distinct 60/minute account budget. Dashboard/bulk role choices load on demand instead of duplicating unbounded catalogs. Each editable role owns current Inertia form state and visible validation feedback. Explicit permission lists and owner name/key storage bounds protect mutations. [ADR-0025](../../adr/0025-bounded-role-catalogs-and-current-editors.md) records contracts, indexes and alternatives.
