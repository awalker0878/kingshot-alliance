# Background processing

Status: Current

Hosted asynchronous processing uses Redis queues and Laravel Horizon. Durable business intent that must survive transaction completion should flow through the transactional outbox where applicable.

## Processing classes

Background work includes notification/reminder delivery, webhook delivery/retry, outbox publication, scheduled content/maintenance, retention work and other retryable side effects.

## Rules

- queue work only after the owning transaction commits, or persist outbox intent in that transaction;
- assume at-least-once execution and make handlers idempotent;
- use bounded retries/backoff and retain safe diagnostic information;
- keep integration retry storms from starving core work through queue/supervisor separation;
- use overlap/single-server scheduler controls where duplicate concurrent execution is unsafe;
- never treat successful enqueueing as proof that an external delivery succeeded.

Redis loss affects more than cache: it affects sessions, queues, Horizon and scheduler coordination. Treat Redis as a production dependency.