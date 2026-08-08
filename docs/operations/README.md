# Operations documentation

[← Documentation home](../README.md)

This directory contains operational procedures, release controls, recovery guidance, migration/rollback notes, and historical phase-specific operating evidence.

## Production and release

- [Production launch runbook](production-launch-runbook.md) — pre-cutover evidence, launch sequence, validation, and stop conditions.
- [Release checklist](release-checklist.md) — required evidence from build through production closeout.
- [Branch protection](branch-protection.md) — recommended repository protection and required-check governance.
- [Phase 6 disaster-recovery exercise](phase-6-disaster-recovery-exercise.md) — recovery rehearsal requirements for the platform-scale phase.
- [Phase 6 database maintenance](phase-6-database-maintenance.md) — database housekeeping and operational maintenance guidance introduced with platform scale.

The authoritative real-production decision is maintained in [`../product/production-launch-approval.md`](../product/production-launch-approval.md). Operational documents can describe how to prove a control, but must not mark a production control approved on their own.

## Core runbooks

- [Local development](runbooks/local-development.md)
- [Deployment](runbooks/deployment.md)
- [Rollback](runbooks/rollback.md)
- [Backup and restore](runbooks/backup-restore.md)
- [Incident response](runbooks/incident-response.md)

Runbooks belong under `operations/runbooks/`; do not create a parallel top-level `docs/runbooks/` directory.

## Phase operating evidence

Phase-specific documents remain useful as historical evidence for the behavior introduced in that phase.

Operations guides:

- [Phase 2 operations](phase-2-operations.md)
- [Phase 3 operations](phase-3-operations.md)
- [Phase 4 operations](phase-4-operations.md)
- [Phase 5 operations](phase-5-operations.md)
- [Phase 6 operations](phase-6-operations.md)

Migration/rollback records:

- [Phase 1 migration and rollback](phase-1-migration-rollback.md)
- [Phase 2 migration and rollback](phase-2-migration-rollback.md)
- [Phase 4 migration and rollback](phase-4-migration-rollback.md)
- [Phase 5 migration and rollback](phase-5-migration-rollback.md)
- [Phase 6 migration and rollback](phase-6-migration-rollback.md)

There is no separate Phase 1 operations guide or Phase 3 migration/rollback document in the repository. The index lists the files that actually exist rather than implying continuous ranges.

Read “next phase”, “before Phase N”, and similar language in historical files as phase-gate context. The current program state is summarized in [`../README.md`](../README.md) and the accepted/current product records under [`../product/`](../product/README.md).

## Operational documentation rules

- A runbook must be executable by an operator who did not write the feature.
- Include prerequisites, safety/stop conditions, commands or actions, validation, rollback/recovery, and evidence to retain.
- Prefer immutable identifiers such as release SHA, image digest, backup identifier, or change/incident record ID.
- Never commit production credentials, secret values, private recovery material, or sensitive incident payloads.
- Distinguish a tested procedure from a completed real-world control. CI recovery demonstrations do not prove production recovery unless the production evidence record says so.
- When runtime behavior changes, update the corresponding runbook and release/rollback implications in the same PR.

## Launch evidence boundary

Repository automation can prove code quality, migrations, image construction, staging boot, backup/restore tooling, and image scanning. It cannot prove real HTTPS/ingress configuration, network egress enforcement, capacity, alert ownership, production dependencies, operator identities, or actual recovery of production-managed keys/media. Those remain external go/no-go evidence.
