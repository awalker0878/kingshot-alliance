# Recovery

Status: Current

Recovery planning covers failure of application release, PostgreSQL, Redis, durable private media and required secret material.

- Application release failure: use immutable release rollback where schema-compatible.
- PostgreSQL corruption/loss: validated database restore.
- Redis loss: restore service availability; assume cache/session/queue coordination impact and reconcile durable work from database/outbox state as applicable.
- Private media loss: restore the durable object-storage recovery set.
- Application key loss: encrypted application/session/model material may become unrecoverable; key custody is therefore part of production DR.

See [Disaster recovery](disaster-recovery.md) and [Backup/restore runbook](../runbooks/backup-restore.md).