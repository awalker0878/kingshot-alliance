# Background processing

Status: Current

Hosted asynchronous processing uses Redis queues and Laravel Horizon. Durable business intent that must survive transaction completion should flow through the transactional outbox where applicable.

## Processing classes

All recurring tasks are registered once in `routes/console.php` and invoke owner commands. Bootstrap and service providers do not add parallel schedules. Use `php artisan schedule:list` to inspect cadence and `php artisan list` / `help <command>` for bounded manual execution. Every task uses shared-cache single-server coordination and overlap protection; owner idempotency remains required for retries and manual runs. See [ADR-0017](../architecture/adr/0017-single-scheduler-registry.md).

Background work includes notification/reminder delivery, webhook delivery/retry, outbox publication, scheduled content/maintenance, retention work and other retryable side effects.

Officer Brief and Intelligence change queue sweeps are owned by `Workflows/NotificationDelivery` and run every 15 minutes through `notifications:queue-officer-briefs` and `notifications:queue-intelligence-changes`. The sweeps are bounded and cursor-addressable, and scheduled `--cycle` runs advance a shared-cache operational cursor before wrapping after the final page. They reauthorize every recipient through owner contracts, consume read-only fact projections, store no brief/signal truth and rely on Communications idempotency before the independent `notifications:deliver` provider worker runs.

`content:queue-announcement-broadcasts` handles both one-off and recurring intent. It creates at most the requested number of runs per invocation, uses row locks plus deterministic run keys, and advances recurring rules in the same transaction as materialization. `notifications:deliver` independently reports external outcomes; operators must not infer provider success from a queued run.

## Delivery lease recovery

Immediate and digest provider workers share a 300-second Pending lease and recheck current due/status/retry eligibility under the row lock. Attempt numbers are monotonic; only the current Pending attempt can commit a result, its member routes, endpoint health and outbox receipts. Do not reset attempt counts manually or interpret a stale completion as permission to rewrite current state. Endpoint locks precede dispatch/delivery locks; transport calls take place after the claim commits, not inside a database transaction.

An exhausted actionable generation is marked Failed with no next attempt and a safe acknowledgement-unknown diagnostic. Its digest members and relevant terminal receipts are reconciled without another provider call. This removes it from later bounded candidate sweeps; no live endpoint failure is invented. Investigate the provider before any separately authorized recovery: acknowledgement loss means the external side effect may already have occurred. Scheduler locks and fencing cannot promise exactly-once network effects. See [ADR-0045](../architecture/adr/0045-fenced-notification-attempts.md).

## Rules

- queue work only after the owning transaction commits, or persist outbox intent in that transaction;
- assume at-least-once execution and make handlers idempotent;
- use bounded retries/backoff and retain safe diagnostic information;
- keep integration retry storms from starving core work through queue/supervisor separation;
- use overlap/single-server scheduler controls where duplicate concurrent execution is unsafe;
- never treat successful enqueueing as proof that an external delivery succeeded.

Redis loss affects more than cache: it affects sessions, queues, Horizon and scheduler coordination. Treat Redis as a production dependency.

Outbox publication claims a message only while its attempt count is below `operations.outbox.maximum_attempts`. Exhausted messages remain durable and visible in the Citadel. A password-confirmed Platform Administrator may release a failed unpublished message for one fresh bounded cycle; the original idempotency key is retained and the release is audited. Published messages are never eligible for this control.

Accounts registration, verification resend and pending-address verification persist private account.email.verification_requested intents with an explicit target. The normal outbox publisher delivers branded verification mail after rechecking the current account and requested address fingerprint. Failed SMTP attempts follow the shared bounded backoff; exhausted records use the existing Citadel recovery action. A finalized, missing or changed target consumes stale verification intent without sending; an already-verified account address also suppresses account verification. Signatures expire from delivery time. Mail acceptance followed by acknowledgement loss can cause a duplicate on retry.

Email promotion additionally records a private account.email.changed_notice_requested command with its historical recipient encrypted. The same outbox worker retries the existing branded old-address notice without changing its original recipient or promoted address. These commands never enter public webhooks. Finalization deletes their account-scoped encrypted records; encrypted transport history remains while the account is active.

Password reset delivery uses the private account.password.reset_delivery_requested outbox event. It references a delivery ID on the maintained reset-token row; recovery material is encrypted there and never copied into outbox history. Normal SMTP failures retry through outbox backoff with a sanitized error. Successful delivery clears the encrypted value, while credential/email/finalization transitions consume the whole token row. The central hourly auth:clear-resets schedule removes expired token rows even when their outbox retry budget is exhausted; monitor the normal scheduler and outbox health checks.
