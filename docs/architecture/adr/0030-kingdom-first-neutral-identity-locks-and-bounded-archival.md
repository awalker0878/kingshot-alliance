# ADR-0030: Lock Kingdom before neutral identities and bound archival reads

Status: Accepted

## Context

Kingdom archival and neutral Alliance resolution acquired the Kingdom first, while identity update, restoration and reconciliation acquired children first. Those inverse orders could deadlock. Archival also materialized every active tracked Alliance, although tracked external identities have no enforced upper bound.

## Decision

Neutral Alliance lifecycle and identity writers acquire their expected or discovered Kingdom before child locks. Identity update restricts the child lock to the expected Kingdom. Restoration and child archival discover the parent without locking the child, lock that parent, then reload the child within the discovered scope. Reconciliation discovers its canonical parent, locks that Kingdom, then locks both children in ID order within the same scope. Missing, changed, archived and canonical-alias facts are checked against the current locked records; historical and idempotent read semantics remain intact.

ArchiveKingdom retains one atomic transaction and its exclusive Kingdom lifecycle barrier. It reads active children through an ascending-ID lazy cursor with at most 200 models per batch. Every actual child transition retains its audit record, and an incremental count supplies the final Kingdom audit metadata. The complete cascade rolls back if any later batch or audit fails. Repeating an archived Kingdom produces no new transition.

## Alternatives

An unbounded eager collection makes application memory depend on historical Kingdom size. A separate transaction per batch would expose partially archived Kingdoms and break existing all-or-nothing semantics. Omitting child audit records or replacing them with only a summary would discard established lifecycle evidence. A global lock would serialize unrelated Kingdoms unnecessarily.

## Consequences and verification

All neutral identity writers agree on parent-before-child ordering. Independent Kingdoms proceed independently. Archival query result size and retained application models are bounded; database work, row locks and audit writes remain proportional to affected children because the operation remains atomic and individually audited.

Four scaling/rollback cases cover zero, one and 405 active children, multiple batches, exact counts, unrelated/historical identities, repeat archival and failure in the second page. Ten separate-connection cases cover both orders for update, restore, reconciliation and child archival against Kingdom archival, independent Kingdom progress and rejection without acquiring a foreign child lock. Existing canonical-alias and history suites remain required. Runtime results are recorded in the delivery ledger.
