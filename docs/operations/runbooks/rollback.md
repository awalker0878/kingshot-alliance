# Rollback runbook

Status: Current

Rollback means returning application execution to a previously validated release. It is not automatically a database restore.

## Before rollback

- identify the exact current and target release image digests/SHAs;
- determine whether schema changes are backward compatible with the target application;
- preserve evidence/logs needed to understand the failed release;
- stop further rollout/automation that could fight the rollback.

## Procedure

1. select the previously validated immutable image;
2. verify schema compatibility;
3. redeploy web/worker execution to that image;
4. verify liveness/readiness;
5. verify authentication, representative writes and queue/outbox processing;
6. record incident/release evidence.

If data must be destructively restored, stop treating the operation as a simple rollback and follow [Backup and restore](backup-restore.md).