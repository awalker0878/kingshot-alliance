# Operations documentation

[← Documentation home](../README.md)

This directory contains operational procedures, release controls, recovery guidance, migration/rollback notes, and historical phase-specific operating evidence.

## Production and release

- [Production launch runbook](PRODUCTION_LAUNCH_RUNBOOK.md) — pre-cutover evidence, launch sequence, validation, and stop conditions.
- [Release checklist](RELEASE_CHECKLIST.md) — required evidence from build through production closeout.
- [Phase 6 disaster-recovery exercise](PHASE_6_DISASTER_RECOVERY_EXERCISE.md) — recovery rehearsal requirements for the platform-scale phase.

The authoritative real-production decision is maintained in [`../product/PRODUCTION_LAUNCH_APPROVAL.md`](../product/PRODUCTION_LAUNCH_APPROVAL.md). Operational documents can describe how to prove a control, but must not mark a production control approved on their own.

## Core runbooks

- [Local development](runbooks/local-development.md)
- [Deployment](runbooks/deployment.md)
- [Rollback](runbooks/rollback.md)
- [Backup and restore](runbooks/backup-restore.md)
- [Incident response](runbooks/incident-response.md)

Runbooks belong under `operations/runbooks/`; do not create a parallel top-level `docs/runbooks/` directory.

## Phase operating evidence

Phase-specific documents remain useful as historical evidence for the behavior introduced in that phase:

- `PHASE_1_OPERATIONS.md` through `PHASE_6_OPERATIONS.md`
- `PHASE_1_MIGRATION_ROLLBACK.md` through `PHASE_6_MIGRATION_ROLLBACK.md`

Read “next phase”, “before Phase N”, and similar language in those files as historical phase-gate context. The current program state is summarized in [`../README.md`](../README.md) and the accepted/current product records under [`../product/`](../product/README.md).

## Operational documentation rules

- A runbook must be executable by an operator who did not write the feature.
- Include prerequisites, safety/stop conditions, commands or actions, validation, rollback/recovery, and evidence to retain.
- Prefer immutable identifiers such as release SHA, image digest, backup identifier, or change/incident record ID.
- Never commit production credentials, secret values, private recovery material, or sensitive incident payloads.
- Distinguish a tested procedure from a completed real-world control. CI recovery demonstrations do not prove production recovery unless the production evidence record says so.
- When runtime behavior changes, update the corresponding runbook and release/rollback implications in the same PR.

## Launch evidence boundary

Repository automation can prove code quality, migrations, image construction, staging boot, backup/restore tooling, and image scanning. It cannot prove real HTTPS/ingress configuration, network egress enforcement, capacity, alert ownership, production dependencies, operator identities, or actual recovery of production-managed keys/media. Those remain external go/no-go evidence.
