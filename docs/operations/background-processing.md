# Background processing

Status: Current

Hosted asynchronous processing uses Redis queues and Laravel Horizon. Durable business intent that must survive transaction completion should flow through the transactional outbox where applicable.

## Processing classes

All recurring tasks are registered once in `routes/console.php` and invoke owner commands. Bootstrap and service providers do not add parallel schedules. Use `php artisan schedule:list` to inspect cadence and `php artisan list` / `help <command>` for bounded manual execution. Every task uses shared-cache single-server coordination and overlap protection; owner idempotency remains required for retries and manual runs. See [ADR-0017](../architecture/adr/0017-single-scheduler-registry.md).

Background work includes notification/reminder delivery, webhook delivery/retry, outbox publication, scheduled content/maintenance, retention work and other retryable side effects.

Officer Brief and Intelligence change queue sweeps are owned by `Workflows/NotificationDelivery` and run every 15 minutes through `notifications:queue-officer-briefs` and `notifications:queue-intelligence-changes`. The sweeps are bounded and cursor-addressable, and scheduled `--cycle` runs advance a shared-cache operational cursor before wrapping after the final page. They reauthorize every recipient through owner contracts, consume read-only fact projections, store no brief/signal truth and rely on Communications idempotency before the independent `notifications:deliver` provider worker runs.

`content:queue-announcement-broadcasts --limit=25 --recipients=100` bounds source/run visits separately from recipient work. Materialization records occurrence identity and a finite membership-key upper bound. Each recipient's current authorization, Communications intent and cursor commit in one short transaction. Pending work resumes with persisted visit priority; changed revisions/generations or inactive scopes stop unfinished audiences. Caps are 100 source/run visits, 1,000 global work units and 25 candidates per run visit. Empty/cancelled/stale visits and suppressed recipients consume bounded work; zero completed runs need not mean zero progress.

`content.broadcast_sweep` logs source/run visits, recipient attempts, consumed work/budget and completions. Per-run counters distinguish skipped authority, suppression and actual replays. Inspect pending progress and failure logs before rerunning; never rewind a cursor or bypass source authorization. Indexed limits bound materialization, not the physical cost of arbitrary backlog filtering. [ADR-0049](../architecture/adr/0049-bounded-announcement-occurrences.md) defines restart and lock-order rules. `notifications:deliver` alone records external outcomes; completed recipient preparation is not provider success.

## Delivery lease recovery

Immediate and digest provider workers share a 300-second Pending lease and recheck current due/status/retry eligibility under the row lock. Attempt numbers are monotonic; only the current Pending attempt can commit a result, its member routes, endpoint health and outbox receipts. Do not reset attempt counts manually or interpret a stale completion as permission to rewrite current state. Endpoint locks precede dispatch/delivery locks; transport calls take place after the claim commits, not inside a database transaction.

Before claiming external publication, immediate and digest workers call the current source-owner authorization contract using the message's original account/Governor, not the routing endpoint's optional Governor. Revoked or missing source access cancels the affected route without provider IO; digest processing checks and removes members individually. Retries recheck source access. Inspect the original type/subject and current owner authority, not only preferences, when diagnosing a cancelled route. Do not force a cancelled message back into sending or add an always-allow fallback. Recreate legitimate intent through its owner after fixing current authority; infrastructure exceptions remain visible failures and do not authorize disclosure. See [ADR-0046](../architecture/adr/0046-current-notification-source-authorization.md). Provider handoff is outside the database transaction, so a later revocation cannot recall already-started IO.

Digest membership is validated against the current dispatch recipient, channel and concrete destination at claim and finalization. An invalid join is detached without mutating the foreign or rebound route; an invalid-only dispatch is cancelled without a provider call. This differs from source authorization denial, which can cancel the correctly scoped route. Diagnose grouping/member consistency separately from source eligibility, and never repair a malformed digest by forcing a foreign route into that dispatch. Original provider outcomes remain truthful even when an out-of-scope member cannot be finalized.

An exhausted actionable generation is marked Failed with no next attempt and a safe acknowledgement-unknown diagnostic. Its digest members and relevant terminal receipts are reconciled without another provider call. This removes it from later bounded candidate sweeps; no live endpoint failure is invented. Investigate the provider before any separately authorized recovery: acknowledgement loss means the external side effect may already have occurred. Scheduler locks and fencing cannot promise exactly-once network effects. See [ADR-0045](../architecture/adr/0045-fenced-notification-attempts.md).

## Rules

- queue work only after the owning transaction commits, or persist outbox intent in that transaction;
- assume at-least-once execution and make handlers idempotent;
- use bounded retries/backoff and retain safe diagnostic information;
- keep integration retry storms from starving core work through queue/supervisor separation;
- use overlap/single-server scheduler controls where duplicate concurrent execution is unsafe;
- never treat successful enqueueing as proof that an external delivery succeeded.

Redis loss affects more than cache: it affects sessions, queues, Horizon and scheduler coordination. Treat Redis as a production dependency.

Endpoint settings have a separate monotonic verification generation. A settings update or pause/resume advances it and resets verification under the owner lock. Immediate and digest handoff captures the generation actually used for IO. An old result can truthfully finalize its own attempt but cannot rewrite health for replacement settings. Diagnose an unverified replacement by testing its current configuration, not by replaying an old response or restoring a prior generation. No credentials or credential hashes enter diagnostics. [ADR-0047](../architecture/adr/0047-endpoint-verification-generations.md) defines this owner-local fence; the field is part of the canonical fresh schema, not an upgrade/backfill mode.

Outbox publication claims a message only while its attempt count is below `operations.outbox.maximum_attempts`. Exhausted messages remain durable and visible in the Citadel. A password-confirmed Platform Administrator may release a failed unpublished message for one fresh bounded cycle; the original idempotency key is retained and the release is audited. Published messages are never eligible for this control.

Accounts registration, verification resend and pending-address verification persist private account.email.verification_requested intents with an explicit target. The normal outbox publisher delivers branded verification mail after rechecking the current account and requested address fingerprint. Failed SMTP attempts follow the shared bounded backoff; exhausted records use the existing Citadel recovery action. A finalized, missing or changed target consumes stale verification intent without sending; an already-verified account address also suppresses account verification. Signatures expire from delivery time. Mail acceptance followed by acknowledgement loss can cause a duplicate on retry.

Email promotion additionally records a private account.email.changed_notice_requested command with its historical recipient encrypted. The same outbox worker retries the existing branded old-address notice without changing its original recipient or promoted address. These commands never enter public webhooks. Finalization deletes their account-scoped encrypted records; encrypted transport history remains while the account is active.

Password reset delivery uses the private account.password.reset_delivery_requested outbox event. It references a delivery ID on the maintained reset-token row; recovery material is encrypted there and never copied into outbox history. Normal SMTP failures retry through outbox backoff with a sanitized error. Successful delivery clears the encrypted value, while credential/email/finalization transitions consume the whole token row. The central hourly auth:clear-resets schedule removes expired token rows even when their outbox retry budget is exhausted; monitor the normal scheduler and outbox health checks.

## King Perk reminder work budgets

`php artisan king-perks:queue-reminders --limit=100` runs every minute through the existing single scheduler registration. Limit now means attempted work, clamped to 1–1000, rather than only successful sends. Each invocation selects at most fifty due source/kind candidates and at most twenty-five manager IDs per candidate; even an empty audience consumes a work unit. At most one hundred expired private checkpoints are pruned separately. No HTTP/provider request occurs in the queueing transaction.

The command reports `work`, `sources`, `recipients`, `queued`, `superseded_pages` and `expired_cursors_removed`. Queued counts newly enqueued recipients, not successful external sends or channel counts. Replays and currently denied recipients may consume work with queued=0. Superseded pages indicate another invocation advanced or replaced a cursor; they never overwrite the winning boundary. Do not infer that a zero queue count means all sources were processed.

Least-recently-visited source/kind ordering rotates due work; durable keyset boundaries survive new processes. Audience completion wraps for later permission grants while the source remains due. No progress state is a permission cache. Current source and recipient authority are checked again before queueing, and downstream publication retains its independent authorization/attempt fences. A failing transaction rolls back both intent and recipient progress; retry the same command instead of manually skipping a boundary. Monitor repeated failures and source deadlines: stable populations progress, but sustained arrival above the budget needs capacity planning.

Progress older than a week is reclaimed in bounded batches. Deleting progress manually causes an idempotent rescan, not an authorized resend, but is not normal recovery. Preserve source/delivery histories and never edit their states to force a send. [ADR-0048](../architecture/adr/0048-bounded-king-perk-reminder-traversal.md) owns the lock, restart and orphan rules.

## Announcement outcome diagnostics

Manager read/status totals aggregate retained Communications records rather than a truncated sample. Content preparation progress remains separate. A retry selection contains at most 50 concrete failures below their attempt limits per run; the displayed candidate total covers all matching failures. The retry Action rechecks current authorization and state. Do not reset attempts to make a displayed candidate succeed or interpret preparation completion as provider acknowledgement.

The source-scope index belongs to the canonical fresh notification schema. Returned data and query count are bounded; physical aggregation/ranking work still scales with matching retained volume. Monitor query plans, latency and retention growth. Inconsistent source metadata is not included, and unknown statuses surface an explicit projection failure rather than plausible partial totals. See [ADR-0050](../architecture/adr/0050-scoped-announcement-outcome-projections.md) and [announcement reference](../reference/announcements.md).

## Officer Brief assessment coverage

A large Transfer plan produces bounded current assessment evidence, not an exhaustive scan on each brief. Daily notification text explicitly states the unassessed count and bounded metadata records coverage. Do not interpret `assessment_incomplete` as a failed worker, completed assessment or clearance; it is a deliberate observation boundary. Current individual checks belong to readiness pages. The existing current-source authorization, attempt/digest/endpoint fences still govern delivery. See [ADR-0051](../architecture/adr/0051-bounded-transfer-verification-overviews.md).
