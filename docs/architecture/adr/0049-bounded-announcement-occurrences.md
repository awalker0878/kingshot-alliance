# ADR-0049: Bounded announcement occurrences and recipient progress

Status: Accepted

Supersedes [ADR-0005](0005-separate-recurring-broadcast-intent-and-delivery.md). Content intent, occurrence identity and Communications provider outcomes retain separate owners; synchronous all-member preparation is replaced.

## Problem and alternatives

The old command bounded runs, not recipients. Each run loaded every active membership and every Player, then queued the entire audience inside one outer transaction. A large Alliance controlled memory and lock duration; a process restart could not resume smaller committed units. One-off keys also omitted the revision despite the product promising a new broadcast for each newly published revision.

Chunking inside the same outer transaction would not bound invocation work or lock duration. Creating one detached job per recipient would move the unbounded enqueue step elsewhere. Persisting a preauthorized recipient list would become stale permission authority. The chosen design instead stores finite traversal progress, reauthorizes current owner facts and commits one recipient at a time.

## Ownership and lifecycle

Alliance/Content owns publication, recurring intent, occurrence identity and recipient preparation. Alliance/Membership supplies candidate keysets, never permission grants. Alliance/Access evaluates current membership authority; GameWorld owner queries stabilize current Kingdom and Governor facts. Communications owns preferences, messages, destinations, provider attempts, retries and outcomes.

An occurrence's immutable identity is Alliance, Content item/revision, optional schedule ID/generation and scheduled time. Its mutable preparation status is Pending, then Queued, Empty or Cancelled. Queued means recipient preparation completed, not provider success. Counters/cursor are operational progress, not another delivery authority. The fresh canonical schema is corrected directly; the old eager implementation is removed without a compatibility overload, backfill, dual-write or alternate runtime mode.

## Bounded materialization

`MaterializeAnnouncementBroadcastRuns` selects at most the source limit across one-off and recurring candidates. Merged due ordering removes fixed one-off priority. A schedule's last materialization time prevents old recurrence backlog from monopolizing a small source budget; the priority does not change its actual occurrence time or idempotency key.

Creation locks Alliance, active Kingdom, Content and optional schedule. It records a Pending run and the highest current active-membership key without hydrating the audience. One-off identity includes revision; `broadcasted_at` records durable occurrence creation, while run `queued_at` records actual preparation completion. Recurrence advances next/last occurrence atomically with creating its run. Invalid or expired recurring sources are retired rather than occupying the same due prefix forever. Inactive scope produces no recipient intent.

Recurring settings saves and cancellation/deactivation advance an internal monotonic generation under existing locks. An A-to-B-to-A change cannot revive an old pending audience. Natural schedule exhaustion does not revoke its last valid occurrence: its scheduled time must remain within the end bound.

## Recipient units and concurrency

`QueueAnnouncementBroadcastRun` selects at most 25 membership IDs after its durable cursor and at or below the captured upper key. This is a finite boundary, not an immutable authorization snapshot. New higher keys wait for another occurrence; deleted cursor rows do not restart traversal. Current membership uniqueness prevents two memberships for the same Governor in an Alliance.

Each candidate is one transaction. Lock order is Alliance, Kingdom, membership, current Player, Content, optional schedule, then run. Recheck active scope, claimed current identity, same Kingdom, membership permission, publication/revision and schedule generation before queueing. The row-locked run must still be Pending at the selected cursor. A competing worker that advanced it makes the stale page a no-op, not a duplicate fan-out. Provider IO occurs in the separate Communications worker, never this transaction.

The existing Communications queue contract and cursor/counters commit together. Failed intent or final audit/outbox writes roll back that unit; prior committed recipients remain progress. Idempotency remains run/Governor for the logical message and message/channel/destination for delivery. No redundant recipient receipt store is added.

Skipped/revoked candidates consume work and advance. No enabled routes means suppressed, not replayed. Existing message/routes count as replay only when neither was created. Empty, cancelled and stale visits consume a unit too. Completion emits the existing queued-run audit/outbox once; cancellation records a safe reason and audit without deleting prior messages.

Pending runs use persisted least-recently-visited order with a microsecond tie breaker. Its short run-only visit transaction commits before acquiring owner locks, avoiding reversed lock ordering. This is not a lease held over recipient work. Defaults are 25 source/run visits and 100 recipient work units; caps are 100 visits, 1,000 units and 25 candidates per run visit. Worker/retry counts are unchanged.

## Source authorization and operations

`AnnouncementBroadcastSource` is the common current-occurrence policy for enqueue and external source authorization. A queued message is not permission to send obsolete revisions, cancelled generations, disabled announcements or archived Kingdoms. Communications retains original account/Governor, destination, attempt, member and endpoint-generation fences. Changes after a provider handoff cannot recall an external side effect; no distributed exactly-once guarantee is implied.

Run progress distinguishes eligible recipients, skipped candidates, suppressed routes, replays and state. The management page distinguishes recorded occurrence, pending preparation and completed recipients. `content.broadcast_sweep` logs source/run visits, recipient attempts, consumed work/budget and completions without names, bodies or credentials. Diagnose pending rows and failures, then resume the normal command; do not rewind cursors or bypass current authorization.

Indexes support source filtering, pending-run ordering and `(alliance_id, status, id)` membership pages. Limits bound hydrated records, memory and invocation work, not arbitrary backlog scan/sort cost. Backlog age and real query plans remain operational signals. Separate management-history truncation/pagination findings are recorded as HARD-106 rather than concealed by this change.

## Verification

HARD-104 in the canonical delivery ledger records current red/green evidence. Real PostgreSQL regressions cover multiple pages, application restart, global budgets, source fairness, a second connection, candidate revocation/deletion, archival, revisions, A-to-B-to-A schedules, natural exhaustion, suppressed routes, intent/outbox rollback and external-source denial. Existing announcement/source scenarios retain their assertions while fixtures use the canonical owner coordinator instead of the removed eager action signature.

A real fixture and both Playwright projects verify pending-to-complete presentation. Normal containing PHP/frontend/architecture/browser/schema/image/recovery/security gates remain required. Acceptance of the ADR does not mark HARD-104 or the entire program Complete before those results are recorded.
