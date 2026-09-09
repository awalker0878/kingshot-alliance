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
