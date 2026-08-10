# Operations documentation

[← Documentation home](../README.md)

This directory owns the **shared repository/runtime operating model**: configuration, background processing, observability, deployment/release controls, recovery runbooks, and historical phase-wide operating evidence.

Operating guides that primarily diagnose one code-domain capability live with that owning domain under `docs/domains/<domain>/operations/` and consume these shared runbooks rather than defining a separate platform.

## Start here — shared living operations

- [Background processing](background-processing.md) — scheduler commands, Horizon queues, outbox processing, idempotency, failure signals, and safe catch-up/recovery.
- [Runtime configuration reference](configuration-reference.md) — application, PostgreSQL, Redis/session/queue, storage, mail, security/proxy, Horizon, launch-threshold, and deployment-host settings.
- [Observability](observability.md) — liveness/readiness, request/trace correlation, JSON logs, audit correlation, Horizon, outbox/webhook signals, release identity, alert categories, and evidence boundaries.
- [Kingdoms operations](../domains/kingdoms/operations/README.md) — domain-specific roster intelligence, transfer planning, and Alliance-intelligence diagnostics for accepted K1–K3 behavior.

These shared living contracts are updated when runtime configuration, scheduler cadence, queue ownership, health checks, observability, deployment, or recovery semantics change.

## Core runbooks

- [Local development](runbooks/local-development.md)
- [Deployment](runbooks/deployment.md)
- [Rollback](runbooks/rollback.md)
- [Backup and restore](runbooks/backup-restore.md)
- [Incident response](runbooks/incident-response.md)

Runbooks stay under `operations/runbooks/`; do not create a parallel top-level `docs/runbooks/` directory.

A practical operator path is:

1. establish valid runtime configuration;
2. deploy using the deployment runbook;
3. validate shared health/telemetry using observability;
4. verify scheduler/outbox/queues using background processing; and
5. use rollback, backup/restore, or incident response when a stop/recovery condition is reached.

Domain-specific guides add domain diagnosis/state semantics to this path; they do not replace it.

## Production and release

- [Production launch runbook](production-launch-runbook.md) — pre-cutover evidence, launch sequence, validation, and stop conditions.
- [Release checklist](release-checklist.md) — required evidence from build through production closeout.
- [Branch protection](branch-protection.md) — repository protection and required-check governance.

The authoritative real-production decision is maintained in [production launch approval](../product/production-launch-approval.md). Operational docs can explain how to prove a control but cannot approve production on their own.

## Historical phase operating evidence

Phase-specific records remain here where they document the overall original program/runtime evolution rather than one current domain contract.

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

Read “next phase”, “before Phase N”, and similar wording in these files as historical phase-gate context.

## Domain-specific operations

Canonical pattern:

```text
docs/domains/<domain>/operations/
  README.md
  <capability>.md
```

Current example:

- [Kingdoms operations](../domains/kingdoms/operations/README.md)

A domain-specific guide should document persisted state, normal operating flow, domain-specific diagnostics, idempotency/recovery, performance/query constraints, privacy/safety, and evidence while linking back to shared deployment/observability/recovery material here.

## Operational documentation rules

- A living operations guide/runbook must be executable by an operator who did not write the feature.
- Include prerequisites, safety/stop conditions, actions/commands, validation, rollback/recovery, and evidence to retain where applicable.
- Prefer immutable identifiers such as release SHA, image digest, backup ID, or change/incident record ID.
- Never commit production credentials, secret values, private recovery material, or sensitive incident payloads.
- Distinguish a tested procedure from a completed real-world control. CI recovery demonstrations do not prove production recovery unless production evidence says so.
- When runtime behavior changes, update the owning domain guide plus any affected shared runbook/configuration contract in the same change.
- Historical phase records remain evidence; do not keep extending them to describe current domain behavior when a living domain guide owns it.

## Launch evidence boundary

Repository automation can prove code quality, migrations, image construction, staging boot, backup/restore tooling, image scanning, health endpoints, configuration validation, scheduler definitions, and queue configuration. It cannot prove real HTTPS/ingress, network egress enforcement, capacity, production log retention, alert ownership, production dependencies, operator identities, on-call coverage, or actual recovery of production-managed keys/media. Those remain external go/no-go evidence.
