# ADR-0048: Bounded, resumable King Perk reminder traversal

Status: Accepted

## Problem and alternatives

The prior reminder action capped successful queue receipts after materializing all permission-bearing Player IDs and all corresponding Player rows for each source. Replayed or ineligible recipients did not consume that limit, and fixed earliest appointment/skill prefixes could starve later sources. A LIMIT on the final send count does not bound discovery or attempted work. Replacing the full audience with an unchanging limited prefix would instead silently omit recipients. A process-local/cache-only cursor would lose continuation across worker restarts or cache loss. A generic broadcast framework or new bounded context is unnecessary for this capability-specific reminder lifecycle.

## Decision and ownership

Operations/KingPerks owns due-source discovery and durable traversal progress. GameWorld/Governance owns a bounded current permission-audience projection: distinct Player IDs ordered after an exclusive keyset boundary, filtered before LIMIT for current Kingdom, claimed/canonical Player and effective non-archived role grants. The old unbounded projection is removed. Audience candidates are not authorization; each recipient is rechecked through current owner contracts inside its write transaction.

`KingPerkReminderKind` owns the six existing lead-time and status predicates, shared by discovery and the locked source recheck. `DueKingPerkReminderQuery` returns at most fifty lightweight source/kind candidates, ordered unvisited first and then least recently visited. The action reads at most twenty-five recipients per candidate. `--limit` is a clamped 1–1000 attempted-work budget: denied/replayed recipients and empty-source visits consume work, not just newly created deliveries. The scheduler's existing limit and cadence are unchanged. Returned counters distinguish work, sources, recipient attempts, newly queued recipients, superseded pages and expired cursors removed; none implies provider delivery or completion of the global audience.

One owner-private `king_perk_reminder_cursors` row per source/kind stores the last attempted Player ID, a monotonic version, visit time and expiry. It is an operational checkpoint, not an authority over plans, assignments, membership, permissions, source timing or Communications delivery. A completed audience wraps so newly eligible recipients behind the boundary are eventually reconsidered while the source is still due. Existing per-source/kind/recipient idempotency keys prevent duplicate intent. Empty or replay-only sources still rotate behind other due sources. Each invocation also removes at most one hundred expired checkpoints; pruning never deletes a notification or source.

No foreign key couples this private cursor to polymorphic sources or its possibly deleted boundary Player. This deliberately avoids deletion cascades reversing the cursor-before-source lock order. Missing sources are not eligible; orphan progress expires after a week. Recreated progress has a fresh ULID identity, so a stalled page cannot pass an ABA check against a replacement row with the same version number. Corruption or manual deletion of progress can cause a bounded replay, not transfer domain authority or bypass idempotency.

## Transactions, failure and security

There is no sweep-wide transaction. Each attempted recipient locks its observed cursor and version, then the active Kingdom, current Player/authority, plan and source, followed by Communications' existing intent/route/outbox writes. The action refuses an enclosing transaction to avoid retaining multiple recipients' locks. Notification intent, its outbox receipt and cursor advancement commit or roll back together. A stale version or replaced cursor stops that stale page without rewinding progress. Exceptions remain failures; only missing domain identities and explicit ineligibility consume a no-send attempt. No provider IO occurs in these transactions.

Under these locks, recheck current Kingdom, claimed Player, manager authority when required, plan status, source ownership/status, assignment and the actual current time. A rescheduled, started or cancelled source must not use stale sweep-time eligibility. Downstream source authorization, destination binding, endpoint generation and provider-attempt fences remain independently required under ADR-0045/0046/0047. Queueing is not a promise that a later provider send remains authorized.

## Schema and operational consequences

This is an undeployed application. Correct the canonical King Perk table-creation migration directly, adding the private cursor and indexed status/time/source scans. There is no old/new implementation, alias, backfill or upgrade shim. No source data is copied into progress rows. The due query bounds returned candidates and application memory; it does not promise constant database CPU regardless of the number of simultaneously due sources. Use actual database plans and backlog evidence before introducing a separate materialized scheduler authority or more workers.

A finite live source/audience set advances across repeated calls, including after process restart. Continual arrival faster than the configured work budget still requires operational capacity management; no algorithm can promise deadlines under unlimited load. Keep the existing scheduler coordination, inspect the explicit counters and real source deadlines, and scale cadence/budgets only with database and provider evidence. See [background processing](../../operations/background-processing.md).

## Verification

The owning Integration tests cover multi-page audiences, 53 recipients beyond two pages, restart continuation, repeated idempotent sweeps, source fairness under a one-unit budget, empty audiences, permission revocation before and after discovery, cursor-version contention through another database connection, replacement-identity fencing, outbox rollback, rescheduling, deadlines, cycle regrant and bounded pruning. Governance tests cover duplicate grants before LIMIT, a deleted boundary, effective/archived/claimed/Kingdom scope and database-level limits. These exercise PostgreSQL, actual owner projections and real notification/outbox writes. They do not replace complete containing gates or prove every possible concurrency schedule. Required results and the exact checkpoint belong in HARD-103 in the [delivery ledger](../../product/codebase-hardening-delivery-ledger.md).
