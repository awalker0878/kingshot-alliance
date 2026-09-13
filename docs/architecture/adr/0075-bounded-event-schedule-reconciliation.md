# ADR-0075 — Bounded Event schedule reconciliation

Status: Accepted

## Context

CreateEvent and UpdateEvent generate at most 64 occurrences per schedule. Repeated edits retain cancelled occurrences and their attached participation, poll, roster and result history. UpdateEvent nevertheless loaded and locked every future occurrence, including unrelated retained cancellations, and found matches by repeatedly searching those collections. Its capacity check also materialized one registration count per historical occurrence. Cancellation rewrote already-cancelled and completed future rows.

## Decision

Keep Events as the mutation owner and preserve existing governing scope, current actor and resource locks. RecurrenceCalculator names its existing 64-row default; owner Actions continue using that default. Reconciliation loads only scheduled future rows or identities at one of the new schedule's at most 64 desired dates. A 129th sentinel row or more than 64 stored scheduled future rows rejects the entire command. Timestamp maps use UTC at the database's second precision. Unrelated cancelled history is never hydrated or rewritten.

Matching cancelled occurrences reactivate with their existing identity and attached state; new dates create occurrences and removed scheduled dates cancel. Completed occurrences retain their identity, status and end time even when a new schedule includes their date. CancelEvent changes only future scheduled occurrences. It leaves completed and previously cancelled history unchanged.

Capacity validation uses SQL MAX over grouped registration counts across the complete Event history, returning one scalar. UpdateEvent enforces the same 1–100,000 capacity range as creation. The scope, Event update, occurrence changes, audit and outbox remain one owner transaction.

## Consequences and verification

Application materialization is bounded by the existing generation contract, independently of retained cancellations. The database may still scan indexed history for membership and capacity aggregates; this does not impose a constant database-cost claim. Invalid stored schedules require owner investigation instead of partial mutation. No historical rows are deleted, no profile is enabled, and no new authority is introduced.

EventScheduleHistoryBoundsTest covers 1,000 unrelated cancellations, all 64 replacement dates and reactivation identities, attached Poll preservation, completed history, corrupt schedule rejection, late outbox rollback/retry, capacity limits and exact maximum across 1,000 historical registrations. Existing owner concurrency/profile cases remain applicable. Event management read pagination is independently tracked by HARD-134.
