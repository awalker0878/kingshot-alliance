# Platform — DataGovernance

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/DataGovernance`

DataGovernance owns platform retention, legal hold, data export and account-deletion orchestration behavior.

## Boundary

DataGovernance coordinates lifecycle/governance obligations without taking business ownership of another context's aggregates. Context-owned deletion/export effects are executed through explicit owner contracts where required.

## Account deletion transitions

Request, cancellation and processing acquire the current Accounts lock before the deletion request lock. Platform calls the Accounts lifecycle owner inside the same database transaction, so request status, account metadata, audit and Communications notification intent commit or roll back together. Notification queuing writes durable intent only; external delivery remains outside these transactions.

Repeating a pending or blocked request preserves its original cooling-off deadline. Repeating a processed request produces no new lifecycle effects; cancellation of a completed account returns no change. A request after cancellation starts a new seven-day cooling-off period and produces a new security notification. Each real transition is atomic; retries that observe an already-applied state do not duplicate its audit or notification.

Finalization holds the Accounts lifecycle lock while obtaining the current Player set and executing owner cleanup/release/anonymization. GameWorld ownership assignment and reconciliation use the same account-before-Player order and reject finalized owners through the Accounts query contract. The ownership set therefore cannot gain another Player between enumeration and finalization.

Blocked requests retain their original `eligible_at` cooling-off deadline and receive a durable `next_attempt_at` one hour later. Selection filters and orders by the effective due time (`COALESCE(next_attempt_at, eligible_at)`) before applying the batch limit, then revalidates both timestamps under the request lock. A partial due-time index supports pending/blocked selection. Retried blockers move behind older eligible work instead of permanently occupying the first batch. Cancellation, a new request cycle and completed processing clear the retry timestamp. The scheduled worker remains hourly; removing a blocker permits the next due retry.

## Export and operational retention

Alliance exports are prepared through a bounded temporary stream inside the existing authorized repeatable-read transaction. PostgreSQL checks each redacted table size before row transfer and a forward cursor transfers bounded chunks; only a complete file receives success metadata, audit and checksum evidence. The HTTP adapter streams after commit. See [ADR-0057](../../adr/0057-bounded-alliance-export-preparation.md).

Operational retention processes at most 500 records per category per invocation, ordered by age and ID under skip-locked row locks. Eligibility is rechecked on mutation, preserving the webhook manual-retry boundary. See [ADR-0058](../../adr/0058-bounded-platform-maintenance-progress.md).
