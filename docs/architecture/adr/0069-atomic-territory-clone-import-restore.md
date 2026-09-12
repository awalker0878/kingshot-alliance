# ADR-0069: Atomic Territory clone, import and restore

Status: Accepted

## Context

Clone released source admission and committed an empty destination before saving the copied layout. Import and restore released initial plan admission, committed SaveTerritoryPlan, then recorded their operation-specific audit outside that transaction. A late validation, identity or audit failure could therefore leave partial persistence, while source authorization or lifecycle could change during the composition.

## Decision

Operations TerritoryPlanning owns one outer transaction for each complete command. Clone holds current source scope, actor and plan through snapshot construction, destination creation and the bounded save. Import performs its pure document preview first, then holds current scope, expected revision and map identity through normalized save and imported audit. Restore first holds current scope and expected revision, acquires the published revision under that plan, and retains those locks through bounded save and restored audit. Required audit events use the protected actor and refreshed protected plan. The released read-and-audit identity queries are removed.

Existing owner Actions compose through nested savepoints, which cannot commit independently of the outer transaction. Late exceptions roll back all newly created rows, layout changes, revision changes, audit and outbox. Retrying the original expected revision after rollback is supported; a completed competing save requires a refreshed expected revision. Published snapshots remain immutable. No cross-context mutation, Workflow exception, migration or compatibility path is added.

## Verification

TerritoryCompositionAtomicityTest adds three final-audit failure cases with an actual competing archive command, exact persisted-state rollback and successful retry for clone/import/restore. Two cases exercise import and restore after a competing successful save, proving stale rejection and refreshed retry. Existing lifecycle, immutable history, import validation and scope-ordering suites remain required. HARD-128 records executed evidence.
