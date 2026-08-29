# Background processing

Status: Current

Hosted asynchronous processing uses Redis queues and Laravel Horizon. Durable business intent that must survive transaction completion should flow through the transactional outbox where applicable.

## Processing classes

Background work includes notification/reminder delivery, webhook delivery/retry, outbox publication, scheduled content/maintenance, retention work and other retryable side effects.

Officer Brief and Intelligence change queue sweeps run every 15 minutes through `notifications:queue-officer-briefs` and `notifications:queue-intelligence-changes`. The sweeps are bounded and cursor-addressable, and scheduled `--cycle` runs advance a shared-cache operational cursor before wrapping after the final page. They reauthorize every recipient, store no brief/signal truth and rely on Communications idempotency before the independent `notifications:deliver` provider worker runs.

`content:queue-announcement-broadcasts` handles both one-off and recurring intent. It creates at most the requested number of runs per invocation, uses row locks plus deterministic run keys, and advances recurring rules in the same transaction as materialization. `notifications:deliver` independently reports external outcomes; operators must not infer provider success from a queued run.

## Rules

- queue work only after the owning transaction commits, or persist outbox intent in that transaction;
- assume at-least-once execution and make handlers idempotent;
- use bounded retries/backoff and retain safe diagnostic information;
- keep integration retry storms from starving core work through queue/supervisor separation;
- use overlap/single-server scheduler controls where duplicate concurrent execution is unsafe;
- never treat successful enqueueing as proof that an external delivery succeeded.

Redis loss affects more than cache: it affects sessions, queues, Horizon and scheduler coordination. Treat Redis as a production dependency.

Outbox publication claims a message only while its attempt count is below `operations.outbox.maximum_attempts`. Exhausted messages remain durable and visible in the Citadel. A password-confirmed Platform Administrator may release a failed unpublished message for one fresh bounded cycle; the original idempotency key is retained and the release is audited. Published messages are never eligible for this control.
