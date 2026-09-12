# ADR-0065: Atomic Kingdom policy provisioning

Status: Accepted

## Context

Kingdom administrator bootstrap and actor-authorized policy reconciliation committed GameWorld provisioning before Operations reconciled its permission meanings. A late failure could leave a partial bootstrap or policy repair. The actor's Kingdom scope was released before the final owner call. These are the same composition requirements established for recovery in ADR-0063, on separate CLI and Governor entry points.

## Decision

BootstrapKingdomAdministrator and ReconcileKingdomGovernancePolicy are two additional named atomic Workflow exceptions. Each wraps its existing GameWorld and Operations owner calls in one database transaction. The CLI bootstrap remains trusted operator initialization with GameWorld's current active Kingdom/target and once-only bootstrap rules. Reconciliation retains current GameWorld RoleManage and exclusive Kingdom scope through the final Operations owner call. Workflows acquire no models, row locks, SQL or independent permission interpretation. Governance owns role/assignment invariants and audit/outbox; Operations owns the meaning of its permissions and reconciles them through GameWorld's explicit owner Action.

A later owner failure rolls back every permission, role, assignment, audit and outbox change. Retrying re-enters the normal owner invariants. No new authority is introduced for other Workflows. The architecture verifier names only these two additional Actions, alongside the existing reviewed exceptions. Moving Operations meanings into GameWorld, using callbacks into owner transactions, or accepting separately committed partial policy were rejected because they either duplicate ownership or leave an avoidable partial result. The composition remains bounded to the three default roles and fixed provisioned permission sets, with no network I/O.

## Verification

KingdomPolicyAtomicityTest exercises late Operations failure for both commands against committed PostgreSQL state and verifies all owned tables return to their previous counts/content before a successful retry. Reconciliation revocation during the owner composition is exercised with a second connection, and a revoked actor cannot retry. Existing CLI/bootstrap, recovery, default permission reconciliation and Governance behavior remain required. HARD-123 records actual executed evidence.
