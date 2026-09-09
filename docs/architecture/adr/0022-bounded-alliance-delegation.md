# ADR-0022: Enforce bounded Alliance delegation at every grant

Status: Accepted

## Problem and superseded implementation

Direct specialist-role assignment checked held permissions only when assigning to the actor. Bulk preview checked all recipients, but the single-target owner could still grant unheld permissions after a stale preview. Two delegated administrators could consequently confer authority indirectly that neither could grant to themselves. Bulk removal incorrectly required the permissions being removed, unlike its single-target owner. Rank administration also allowed a low-rank role administrator to grant R4 and its implicit cross-context officer authority.

System-role contracts require an additional distinction. Event Coordinator carries Operations authority without an Alliance permission list, so an empty list cannot prove grant authority. Gift Code coverage deliberately excludes R5 by rank alone, yet R5 must be able to commission another member as Gift Code Coordinator. A blanket held-permissions restriction would make the first legitimate coverage grant impossible.

## Decision and alternatives

Retain Alliance Access as the interpreter of specialist permissions and Membership as the owner of ranks. AllianceRoleDelegation owns one grant policy shared by preview and AssignMembershipRole. Custom grants require all current target-role permissions to be held by the current actor, regardless of recipient. R5 may explicitly commission a provisioned, immutable system role on another membership; this does not grant the leader that role's data access and cannot be used for self-assignment. Other system grants retain the held-permission ceiling. Event Coordinator additionally requires R5 or an existing assignment of that same system role; its empty Alliance permission list is not a grant of Operations authority.

The locked Action remains authoritative for direct HTTP calls and every bulk item. Preview is advisory, evaluates self/other policy once per actor/role pair, and reports denied targets individually without per-recipient permission queries. Removing a role is revocation, not delegation, and requires current role-management authority without requiring the removed permissions.

Rank grants require current role-management authority and cannot exceed the actor's current rank. The Membership rank enum owns this ordinal ceiling; preview and the locked mutation both apply it. Existing self-rank and R5 leadership-transfer protections remain. Checking only Alliance's explicit rank permission list would miss officer authority interpreted by Operations and other consumers; importing all those policies into Membership would create competing authority. An ordinal ceiling protects inherited authority without duplicating consumer meanings.

## Security, scalability and operations

No new persistence, compatibility path, permission version or platform bypass is introduced. All target rows remain Alliance-scoped. System-role commissioning is a specific leadership responsibility, not a general permission bypass for custom role definitions. Gift Code coverage keeps its explicit-approval and aggregate-only contract; R5 still has no implicit coverage access. Operations remains the interpreter of Event Coordinator capabilities. Bulk requests retain their 50-target bound and per-item results. Rejected writes create no membership, audit or outbox change. Role-definition serialization is a distinct concurrent-authority concern tracked by HARD-046.

## Verification

AllianceDelegationBoundaryV3Test covers direct/HTTP grants, self/other recipients, permitted subsets, cross-Alliance targets, removal parity, real late audit rollback, rank ceilings, changing authority during earlier bulk items, system-role commissioning, actual Operations permission outcomes and constant preview query count. Existing Gift Code, rank and leadership tests preserve explicit coverage and self/R5 behavior. Containing executable evidence is recorded in HARD-045/047.
