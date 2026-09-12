# ADR-0066: Current bounded Kingdom role delegation

Status: Accepted

## Context

Kingdom RoleManage permitted assigning roles whose permissions the actor did not hold. Definition edits already attempted a subset check, but ordinary Governance writes shared the Kingdom lifecycle lock and read effective permissions without protecting their continued validity. Concurrent role edits could invalidate admission before commit. Input limits applied at HTTP adapters and did not protect direct owner calls; long valid names could produce keys wider than the canonical column.

## Decision

GameWorld Governance serializes its role mutations on the exclusive current Kingdom scope before locking the actor and target. Exact permission reconciliation uses the same scope barrier, while each permission owner continues to provide its own meanings. Assign, create and update delegate only recognized permissions in the current actor's protected authority. Assignment replay re-enters these checks. Current actors and targets must be direct canonical Governors of the active Kingdom. The obsolete shared Kingdom write-state entry point is removed.

KingdomAuthorityFactsQuery acquires the shared Kingdom scope before the current actor for protected reads. Effective assignments are SQL subqueries into a scalar permission-key projection, so duplicate assignment history never expands model hydration. The projection accepts at most 500 deployed permission keys and fails closed on overflow. No role or assignment collection is locked or materialized by this projection. Consumers must enter with the governing scope before Player or mutable operation state; HARD-127 records the remaining Operations caller migration and its independent concurrency proofs.

Role definitions accept a list of at most 50 permission keys, names of 100 Unicode characters and descriptions of 255. Creation uses a deterministic shortened slug plus name digest when the ordinary slug would exceed 64 bytes. Assignment/removal reasons accept at most 500 Unicode characters. Assignment dates use the same date contract as HTTP and a supported calendar range. Exact owner reconciliation accepts at most 50 roles with at most 50 permission keys each. Invalid inputs fail before side effects. These are owner invariants, without new aliases, policy duplication or Workflow authorization.

## Verification

KingdomRoleDelegationTest checks limited-manager escalation, authorized delegation, replay, malformed direct definitions, Unicode bounds, distinct long-name keys, canonical and archived authority, assignment dates/reasons and 1,001 duplicate assignment facts. KingdomRoleAuthorityConcurrencyTest exercises permission changes by both normal role editing and exact owner reconciliation, in both competing-transaction orders. The existing complete Governance, Operations, lifecycle and containing gates remain required; HARD-124 records executed results.
