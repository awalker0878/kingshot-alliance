# ADR-0045: Fence notification results to the current delivery attempt

Status: Accepted

## Problem

Immediate and digest workers selected due row IDs before claiming each row. The digest locked its dispatch but did not repeat the queued/retry/expired-lease predicate after selection. Both workers finalized any Pending row without matching its attempt number. A delayed provider response could therefore overwrite a later worker's claim, its member routes and its broadcast outbox receipts. Endpoint health was written outside that fenced completion and could likewise reflect the obsolete attempt. Exhausted Pending rows could remain at the front of every bounded sweep indefinitely.

The regression uses two real PostgreSQL connections and suspends workers at the HTTP adapter boundary after their claim transactions commit. The old implementation fails ten of the initial sixteen cases: stale selections, old successes/failures, the exact lease boundary and exhausted claims. Existing transport fixtures are used; no alternative delivery implementation is substituted.

## Decision

Communications/Delivery owns one `NotificationAttemptEligibility` predicate, applied both to bounded candidate selection and to the row-locked current read. A due Queued row is ready; Failed requires a non-null retry time at or before the current instant; Pending requires an update time at least 300 seconds old. Both comparisons are inclusive. Each claim obtains a fresh clock value, and dispatch ordering has a deterministic ID tie-breaker. The existing per-route/dispatch attempt budget is rechecked under the same row lock.

Every real claim increments the existing monotonic `attempt_count` and returns that value in the immutable `DeliveryAttempt`. Completion locks only the matching Pending row with the same attempt count. A stale response is ignored before changing status, retry times, member routes, outbox receipts or endpoint health. Manual retry must never reset the attempt count. No new lease-token column, compatibility state or competing queue authority is introduced.

Transport adapters return an outcome without mutating endpoint state. `NotificationEndpointHealth` records it only inside the accepted completion transaction. Endpoint locking precedes dispatch/delivery locking, consistent with endpoint deletion's foreign-key effects. A paused or deleted endpoint does not become healthy because an earlier request returned. A failed outbox write rolls back delivery/member/health changes together. Network IO remains outside database transactions; a lost acknowledgement or a crash after provider acceptance can still cause an at-least-once resend.

Digest completion checks that each captured route is still a member of that dispatch and still Queued before mutating it. The current twenty-member bound, recipient routing policy, source message identities, digest window and original availability-time semantics remain unchanged. Source-specific authorization is a separate boundary; this decision does not claim to resolve HARD-099.

An actionable row whose budget is exhausted is terminalized without another provider request. Its status is Failed, retry time is cleared, and safe diagnostics explicitly say that an acknowledgement was not recorded and the provider outcome may be unknown. Digest members and applicable terminal broadcast receipts are updated atomically, with no invented provider-health observation. The exhaustion receipt has its own deterministic suffix so it cannot collide with a prior retryable failure at the same attempt count. Subsequent sweeps no longer select the exhausted row. Reconciliation consumes a bounded candidate slot; it does not falsely increase the worker's count of attempted provider sends.

## Alternatives and consequences

A row lock with only a Pending check was rejected because it does not identify the authorized generation. Scheduler overlap controls or queue uniqueness alone do not protect delayed completions, direct invocations or manual retries. Keeping a database transaction open across provider calls was rejected because it holds locks during unbounded external latency and still cannot atomically commit a third-party side effect. Adding UUID leases and a second generic worker framework would duplicate the existing monotonic attempt identity without a current benefit.

This keeps the modular monolith's existing owner and two bounded worker entry points. There is one readiness rule and one endpoint-health transition implementation, but distinct immediate and digest member semantics remain explicit. No production retry budget, provider timeout, security policy, worker count or database durability setting is weakened. Provider-side idempotency, when actually supported, remains a transport-specific concern; this ADR does not promise exactly-once external delivery.

PostgreSQL Read Committed re-evaluates a row's search condition after a conflicting writer commits; applying the readiness/fencing predicates to the locked query uses that behavior. See [PostgreSQL transaction isolation](https://www.postgresql.org/docs/18/transaction-iso.html) and [Laravel pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking). The tests still exercise independent database locks rather than assuming the documentation is runtime proof.

## Verification and remaining work

`NotificationAttemptFencingTest` covers both worker kinds: active lease/retry exclusion after preselection, stale success and failure, lease/retry boundaries, exhausted queues and receipt identity, actual lock contention, completion rollback, endpoint pause and detached digest members. Provider callbacks assert no open transaction. Containing Communications, NotificationDelivery and repository architecture tests and full static analysis are required; exact results and containing commit belong to HARD-098 in the canonical delivery ledger.

Credential replacement during an in-flight request is a distinct endpoint-generation diagnostic issue recorded as HARD-100. It must not be hidden by claiming attempt fencing certifies a different endpoint configuration. The overall hardening program remains open.
