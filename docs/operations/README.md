# Operations documentation

[← Documentation home](../README.md)

This directory owns the **shared repository/runtime operating model**: configuration, background processing, observability, deployment/release controls, recovery runbooks, and historical phase-wide operating evidence.

Operating guides that primarily diagnose one code-domain capability live with that owning domain under `docs/domains/<domain>/operations/` and consume these shared runbooks rather than defining a separate platform.

DCP-P3 operating-document depth is governed by the [operations documentation standard](../product/operations-documentation-standard.md) and the frozen [operations coverage matrix](../product/operations-coverage-matrix.md).

## Start here — shared living operations

- [Background processing](background-processing.md) — scheduler commands, Horizon queues, outbox processing, idempotency, failure signals, and safe catch-up/recovery.
- [Runtime configuration reference](configuration-reference.md) — application, PostgreSQL, Redis/session/queue, storage, mail, security/proxy, Horizon, launch-threshold, and deployment-host settings.
- [Observability](observability.md) — liveness/readiness, request/trace correlation, JSON logs, audit correlation, Horizon, outbox/webhook signals, release identity, alert categories, and evidence boundaries.

These shared living contracts are updated when runtime configuration, scheduler cadence, queue ownership, health checks, observability, deployment, or recovery semantics change.

## Domain operations profiles

Every canonical code domain has one current living operations profile:

- [Alliances](../domains/alliances/operations/README.md)
- [Audit](../domains/audit/operations/README.md)
- [Authorization](../domains/authorization/operations/README.md)
- [Content](../domains/content/operations/README.md)
- [Contributions](../domains/contributions/operations/README.md)
- [Events](../domains/events/operations/README.md)
- [Identity](../domains/identity/operations/README.md)
- [Integrations](../domains/integrations/operations/README.md)
- [Kingdoms](../domains/kingdoms/operations/README.md)
- [Memberships](../domains/memberships/operations/README.md)
- [Notifications](../domains/notifications/operations/README.md)
- [Platform](../domains/platform/operations/README.md)
- [Rallies](../domains/rallies/operations/README.md)
- [Recruitment](../domains/recruitment/operations/README.md)

Focused living runbooks required by the P3 inventory are indexed from those profiles. Kingdoms retains its accepted K1–K3 operations guides under its domain profile.

## Core runbooks

- [Local development](runbooks/local-development.md)
- [Deployment](runbooks/deployment.md)
- [Azure Container Apps](runbooks/azure-container-apps.md) — supported multi-container web replica, Horizon boundary, scheduled/release jobs, private managed dependencies, and validation.
- [Rollback](runbooks/rollback.md)
- [Backup and restore](runbooks/backup-restore.md)
- [Incident response](runbooks/incident-response.md)

Runbooks stay under `operations/runbooks/`; do not create a parallel top-level `docs/runbooks/` directory.

A practical operator path is:

1. establish valid runtime configuration;
2. deploy using the deployment runbook and the environment-specific runbook where applicable;
3. validate shared health/telemetry using observability;
4. verify scheduler/outbox/queues using background processing;
5. diagnose the owning domain through `docs/domains/<domain>/operations/`; and
6. use rollback, backup/restore, or incident response when a stop/recovery condition is reached.

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

A domain profile documents persisted state, runtime dependencies, normal flow, diagnostics, failure/recovery/replay, backup/rollback semantics, capacity assumptions, degradation, safe operator actions and evidence. A focused runbook is required only when a capability has an independently complex operational lifecycle under the P3 standard.

Top-level shared operations owns deployment/configuration/health/backup/rollback/incident mechanics. Domain-specific guides must link back here instead of duplicating those procedures.

## Operational documentation rules

- A living operations guide/runbook must be executable by an operator who did not write the feature.
- Include prerequisites, safety/stop conditions, actions/commands, validation, rollback/recovery, and evidence to retain where applicable.
- Prefer immutable identifiers such as release SHA, image digest, backup ID, or change/incident record ID.
- Never commit production credentials, secret values, private recovery material, or sensitive incident payloads.
- Distinguish a tested procedure from a completed real-world control. CI recovery demonstrations do not prove production recovery unless production evidence says so.
- A restart is not proof of recovery; validate the durable state that was expected to advance.
- When runtime behavior changes, update the owning domain guide plus any affected shared runbook/configuration contract in the same change.
- Historical phase records remain evidence; do not keep extending them to describe current domain behavior when a living domain guide owns it.

## Launch evidence boundary

Repository automation can prove code quality, migrations, image construction, staging boot, backup/restore tooling, image scanning, health endpoints, configuration validation, scheduler definitions, queue configuration, domain operations-document structure and local links. It cannot prove real HTTPS/ingress, network egress enforcement, production capacity, production log retention, alert ownership, production dependencies, operator identities, on-call coverage, actual provider backup durability, or recovery of production-managed keys/media. Those remain external go/no-go evidence.
