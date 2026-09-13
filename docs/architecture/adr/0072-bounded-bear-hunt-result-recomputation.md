# ADR-0072: Bounded Bear Hunt result recomputation and report receipts

Status: Accepted

## Context

Operations Results recomputed every retained Governor after each accepted report, replay or removal. The 100-entry report bound did not limit distinct occurrence baselines. Recalculation loaded all baselines and results, made separate sum/count/result queries for each Governor, repeatedly searched the baseline collection and copied the complete occurrence into the receipt persisted by Intelligence Evidence. Integer conversion could also saturate a PostgreSQL numeric sum before PHP added the manual baseline.

## Decision and ownership

Operations keeps the accepted-report ledger, captured manual baselines and authoritative Event Player results. The existing Alliance/Event scope transaction, verified Bear Hunt profile guard and current actor authorization remain mandatory. Intelligence continues its explicit scalar owner handoff under ADR-0010 and stores the destination receipt; it does not gain result persistence or ranking rules.

A synchronous occurrence supports at most 1,000 distinct reviewed Governors. This is an application transaction work budget, not a claim about Kingshot Alliance capacity. A limit-plus-one locked baseline query rejects overflow and rolls back the entire report, frozen context, result, audit and outbox change. Removed reports retain their baselines and consume this distinct-Governor budget; repeated reports for admitted Governors remain supported. There is no silent truncation, eviction or historical backfill.

One indexed grouped query computes accepted damage and entry counts. At most 1,000 baseline/result rows and aggregate groups are materialized. Keyed collections replace repeated searches. Competition ranking retains equal-score ties and gaps, includes accepted zero-damage rows and preserves the existing manual-baseline participation rule. Upserts of at most 100 rows update only result score, rank, recording actor/time and update timestamp; existing result identity, creation time, outcome, notes and metric relationships survive. Removal restores the original nullable manual score/rank exactly.

PostgreSQL sums are validated as representable nonnegative PHP integers before addition. An aggregate or baseline-plus-damage overflow rejects the transaction with an actionable validation error instead of storing a saturated score or leaking a type/database error.

Receipts contain current captured results for the stored report's 1–100 Governors, ordered by rank and Governor identity. They do not contain unrelated occurrence Governors. Replays derive the member set from persisted report entries rather than changed retry input. Removed-report replay preserves removal and reports current restored results. A limit-plus-one receipt query rejects a corrupt report outside the stored entry contract. Existing authorized Results projections remain the way to request occurrence results.

## Alternatives and operational implications

Only replacing N+1 queries leaves application memory, the rank sort, result updates and receipt size open-ended. Paging all rows inside the same transaction bounds a page but still leaves that transaction's application work unbounded. Asynchronous ranking would add partial-result states and recovery ownership that this synchronous capability does not otherwise need. The explicit admission budget keeps the supported atomic operation predictable without adding a second projection authority.

Retained accepted history is aggregated by PostgreSQL through occurrence/status and report/player indexes. Database aggregation work still grows with accepted history; this decision bounds application materialization and mutation cardinality, not total database history storage or a fixed elapsed time. Raising the distinct-Governor budget requires measured query/transaction evidence and review of the synchronous contract. An over-budget or invalid stored ledger fails closed; operators correct input through the owner rather than deleting baseline rows directly.

## Verification

Owner-boundary PostgreSQL tests cover the 1,000-Governor limit, full ranking beyond the receipt, query and payload bounds, report replay, removed-report replay, ties, zero scores, existing result identity and manual values, sum/addition overflow and complete rollback. Existing current-authority and contention regressions remain active. PHPStan, architecture and containing browser/PHP/schema gates are required; authored tests alone are not runtime evidence.
