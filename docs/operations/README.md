# Operations documentation

[← Documentation home](../README.md)

This directory contains the living operating model, production/release controls, recovery runbooks, and historical phase-specific evidence.

## Start here — living operations

Operators should use these documents for the current Phase 0–6-complete runtime and implemented post-program increments rather than reconstructing today's behavior from historical phase records:

- [Background processing](background-processing.md) — scheduler commands, Horizon queues, outbox processing, idempotency, failure signals, and safe catch-up/recovery.
- [Runtime configuration reference](configuration-reference.md) — application, PostgreSQL, Redis/session/queue, storage, mail, security/proxy, Horizon, launch-threshold, and deployment-host settings.
- [Observability](observability.md) — liveness/readiness, request/trace correlation, JSON logs, audit correlation, Horizon, outbox/webhook signals, release identity, alert categories, and evidence boundaries.
- [Kingdoms roster intelligence operations](kingdoms-roster-intelligence.md) — `KINGDOMS-001` persisted state, CSV diagnostics, snapshot/history behavior, intelligence query/index boundary, migration order and internal-only outbox contract.

These are living operational contracts. Update them in the same PR when runtime behavior, configuration, scheduler cadence, queue ownership, health checks, or observability semantics change.

## Core runbooks

- [Local development](runbooks/local-development.md)
- [Deployment](runbooks/deployment.md)
- [Rollback](runbooks/rollback.md)
- [Backup and restore](runbooks/backup-restore.md)
- [Incident response](runbooks/incident-response.md)

Runbooks belong under `operations/runbooks/`; do not create a parallel top-level `docs/runbooks/` directory.

A practical operator path is:

1. use the [configuration reference](configuration-reference.md) to establish a valid runtime;
2. deploy using the [deployment runbook](runbooks/deployment.md);
3. verify application/dependency signals using [observability](observability.md);
4. verify scheduler, outbox, and queues using [background processing](background-processing.md); and
5. use the rollback, backup/restore, or incident-response runbook when a stop/recovery condition is reached.

For `KINGDOMS-001` roster/import/history/intelligence incidents, use the [Kingdoms operations guide](kingdoms-roster-intelligence.md) together with those shared runbooks rather than inventing a separate deployment path.

## Production and release

- [Production launch runbook](production-launch-runbook.md) — pre-cutover evidence, launch sequence, validation, and stop conditions.
- [Release checklist](release-checklist.md) — required evidence from build through production closeout.
- [Branch protection](branch-protection.md) — recommended repository protection and required-check governance.

The authoritative real-production decision is maintained in [`../product/production-launch-approval.md`](../product/production-launch-approval.md). Operational documents can describe how to prove a control, but must not mark a production control approved on their own.

## Historical phase operating evidence

Phase-specific documents remain useful as evidence of what was introduced/tested during the implementation program. They are not the primary source for the current combined operating model.

Operations guides:

- [Phase 2 operations](phase-2-operations.md)
- [Phase 3 operations](phase-3-operations.md)
- [Phase 4 operations](phase-4-operations.md)
- [Phase 5 operations](phase-5-operations.md)
- [Phase 6 operations](phase-6-operations.md)
- [Phase 6 disaster-recovery exercise](phase-6-disaster-recovery-exercise.md)
- [Phase 6 database maintenance](phase-6-database-maintenance.md)

Migration/rollback records:

- [Phase 1 migration and rollback](phase-1-migration-rollback.md)
- [Phase 2 migration and rollback](phase-2-migration-rollback.md)
- [Phase 4 migration and rollback](phase-4-migration-rollback.md)
- [Phase 5 migration and rollback](phase-5-migration-rollback.md)
- [Phase 6 migration and rollback](phase-6-migration-rollback.md)

There is no separate Phase 1 operations guide or Phase 3 migration/rollback document in the repository. The index lists the files that actually exist rather than implying continuous ranges.

Read “next phase”, “before Phase N”, and similar language in historical files as phase-gate context. The current program state is summarized in [`../README.md`](../README.md) and the accepted/current product records under [`../product/`](../product/README.md).

## Operational documentation rules

- A living operations guide or runbook must be executable by an operator who did not write the feature.
- Include prerequisites, safety/stop conditions, commands or actions, validation, rollback/recovery, and evidence to retain where applicable.
- Prefer immutable identifiers such as release SHA, image digest, backup identifier, or change/incident record ID.
- Never commit production credentials, secret values, private recovery material, or sensitive incident payloads.
- Distinguish a tested procedure from a completed real-world control. CI recovery demonstrations do not prove production recovery unless the production evidence record says so.
- When runtime behavior changes, update the corresponding living guide/runbook and release/rollback implications in the same PR.
- Phase-specific records should remain historical evidence; do not keep extending them to describe current cross-phase behavior when a living guide owns that contract.

## Launch evidence boundary

Repository automation can prove code quality, migrations, image construction, staging boot, backup/restore tooling, image scanning, health endpoints, configuration validation, scheduler definitions, and queue configuration. It cannot prove real HTTPS/ingress configuration, network egress enforcement, capacity, production log retention, alert ownership, production dependencies, operator identities, on-call coverage, or actual recovery of production-managed keys/media. Those remain external go/no-go evidence.
