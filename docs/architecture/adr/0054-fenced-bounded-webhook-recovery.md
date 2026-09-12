# ADR-0054: Fenced and bounded webhook recovery

Status: Accepted

## Problem and alternatives

Queue job retries reset when a replacement job is dispatched, so they cannot bound durable provider attempts. Old jobs or callbacks identified only by delivery ID can modify newer work. Full stale-delivery and global subscription collections grow without a memory bound. Retaining queue uniqueness locks alone cannot fence recovery or guarantee progress after a worker stops.

## Decision

Integrations owns delivery state and the durable fan-out checkpoint. Each queued reservation and provider attempt has a fresh persisted UUID. Queued jobs must present their exact reservation; provider completion must match its exact attempt. Jobs perform one owner invocation, with no independent failure callback or business retry authority.

The owner grants five automatic provider attempts. Counts survive replacement jobs and stale recovery. Exhaustion becomes terminal; an authorized manual retry grants five further attempts without resetting the cumulative count or delivery identity. Due-time checks reject early jobs. Every scheduler sweep recovers at most its clamped 1–500 limit, then reserves a bounded due page with `FOR UPDATE SKIP LOCKED`. Queued and delivering leases expire after five minutes. Lost broker dispatch is recoverable; the prior reservation cannot claim its replacement.

Public events create a durable source checkpoint and a subscription upper boundary. Processing visits at most 25 subscriptions per transaction, advances over inactive recipients, rechecks current subscription selectors/scope and records idempotent deliveries. The scheduler uses a bounded visit budget across least-recently-visited unfinished sources. Source identity reuse with different facts is rejected. Oversized source bodies are represented by a fingerprint and failure marker; accepted source payloads are cleared when fan-out completes. The compact completed checkpoint prevents replay from admitting later subscribers.

## Consequences and verification

Memory and synchronous fan-out are bounded independently of audience size. A page rollback cannot advance its cursor or enqueue committed delivery work. Fencing protects internal state; provider delivery remains at-least-once, including acknowledgement-unknown interruptions. Receivers must deduplicate stable delivery IDs.

The canonical fresh migration includes attempt budgets, due/stale indexes and owner-local fan-out progress. This application is undeployed; no upgrade shim or parallel implementation is retained. Owner tests cover fresh-job exhaustion, stale queued jobs/provider responses, due times, bounded recovery, resumable global fan-out and conflicting source replay. HARD-110 remains open pending PostgreSQL and containing execution.
