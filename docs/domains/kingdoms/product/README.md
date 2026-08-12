# Kingdoms product and acceptance evidence

[← Kingdoms domain](../README.md)

This directory contains Kingdoms-specific product scope, implementation-plan, slice validation, accessibility and acceptance evidence. `KINGDOMS-001` through `KINGDOMS-005` are accepted.

Use the [Kingdoms domain](../README.md) and living capability contracts for current behavior. Product records capture governed increment scope and validation history; repository/product acceptance remains distinct from broader production policy or future scope changes.

## KINGDOMS-001 — roster intelligence

- [Scope](kingdoms-roster-intelligence-increment.md)
- [Implementation plan](kingdoms-roster-intelligence-implementation-plan.md)
- [Accessibility](kingdoms-roster-intelligence-accessibility.md)
- [Exit report](kingdoms-roster-intelligence-exit-report.md)

## KINGDOMS-002 — transfer planning

- [Scope](kingdoms-transfer-planning-increment.md)
- [Implementation plan](kingdoms-transfer-planning-implementation-plan.md)
- [Slice A decisions](kingdoms-transfer-planning-slice-a-decisions.md)
- [Slice A validation](kingdoms-transfer-planning-slice-a-validation.md)
- [Slice B validation](kingdoms-transfer-planning-slice-b-validation.md)
- [Slice C1 validation](kingdoms-transfer-planning-slice-c1-validation.md)
- [Slice C2 validation](kingdoms-transfer-planning-slice-c2-validation.md)
- [Slice D validation](kingdoms-transfer-planning-slice-d-validation.md)
- [Accessibility](kingdoms-transfer-planning-accessibility.md)
- [Exit report](kingdoms-transfer-planning-exit-report.md)

## KINGDOMS-003 — Alliance intelligence and diplomacy

- [Scope](kingdoms-alliance-intelligence-increment.md)
- [Implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)
- [K3-P0 decisions](kingdoms-alliance-intelligence-p0-decisions.md)
- [Slice A validation](kingdoms-alliance-intelligence-slice-a-validation.md)
- [Slice B validation](kingdoms-alliance-intelligence-slice-b-validation.md)
- [Slice C1 validation](kingdoms-alliance-intelligence-slice-c1-validation.md)
- [Slice C2 validation](kingdoms-alliance-intelligence-slice-c2-validation.md)
- [Slice D validation](kingdoms-alliance-intelligence-slice-d-validation.md)
- [Accessibility](kingdoms-alliance-intelligence-accessibility.md)
- [Exit report](kingdoms-alliance-intelligence-exit-report.md)

## KINGDOMS-004 — automated game-data ingestion — accepted

Whole-increment runtime candidate `3e0976e8bdd32207bd6314011c26b94fa0f3c118` is protected-green across Dependency Review `31556412455`, CodeQL `31556412413`, and CI `31556412468`. Production adapter configuration remains empty and no concrete source/cutover is approved.

- [Scope](kingdoms-automated-ingestion-increment.md)
- [Implementation plan](kingdoms-automated-ingestion-implementation-plan.md)
- [K4-P0 decisions](kingdoms-automated-ingestion-p0-decisions.md)
- [K4-P0 exit report](kingdoms-automated-ingestion-p0-exit-report.md)
- [Slice A validation](kingdoms-automated-ingestion-slice-a-validation.md)
- [Slice B validation](kingdoms-automated-ingestion-slice-b-validation.md)
- [Slice C validation](kingdoms-automated-ingestion-slice-c-validation.md)
- [Slice D validation](kingdoms-automated-ingestion-slice-d-validation.md)
- [Slice E validation](kingdoms-automated-ingestion-slice-e-validation.md)
- [Whole-increment exit report](kingdoms-automated-ingestion-exit-report.md)
- [Living automated-ingestion contract](../automated-ingestion.md)

## KINGDOMS-005 — opt-in shared Kingdom intelligence — accepted

`K5-P0`–`K5-P6` are Complete. Whole-increment runtime candidate `6f84b51ab27941f0fec2abce71f1f2f6325560e4` passed Dependency Review `31573301975`, CodeQL `31573301988`, and CI `31573301977`: Pint 560 files, PHPStan/Larastan 394/394 zero errors, 452 tests / 10,322 assertions, frontend lint/format/type/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

Accepted runtime includes directional consent/agreement state, explicit per-target grants/removal, persistent fail-closed Kingdom-drift invalidation, bounded safe current facts, bounded accepted history, member-safe current/history presentation and a manager-only consent/grant workspace. History uses opaque encrypted target-bound cursors, 50-row maximum pages, and a hard 250-observation traversal cap; the UI exposes no arbitrary `asOf` history-window control. Recipient reads create no canonical copy and do not permit reshare.

P4 hardens one-time invitation-secret lifecycle by erasing the persisted hash on accept, decline and revoke. P5 adds bounded retention of eligible old operational consent/grant rows while preserving active shares/grants, canonical source tracking/observations and Audit/outbox evidence, plus realistic-volume capacity proof. P6 composes the complete cross-tenant seam and re-proves unrelated-tenant denial, correction/invalidation propagation, no copy/reshare/mutation and immediate authorization loss.

- [Scope](kingdoms-shared-intelligence-increment.md)
- [Implementation plan](kingdoms-shared-intelligence-implementation-plan.md)
- [K5-P0 decisions](kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 security/privacy review](../security/kingdoms-shared-intelligence-p0-security-review.md)
- [K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)
- [Slice A validation](kingdoms-shared-intelligence-slice-a-validation.md)
- [Slice A security review](../security/kingdoms-shared-intelligence-foundation-security-review.md)
- [Slice B validation](kingdoms-shared-intelligence-slice-b-validation.md)
- [Slice B security review](../security/kingdoms-shared-intelligence-current-facts-security-review.md)
- [Slice C validation](kingdoms-shared-intelligence-slice-c-validation.md)
- [Slice C security review](../security/kingdoms-shared-intelligence-history-security-review.md)
- [Slice D validation](kingdoms-shared-intelligence-slice-d-validation.md)
- [Slice D presentation security review](../security/kingdoms-shared-intelligence-presentation-security-review.md)
- [Slice E validation](kingdoms-shared-intelligence-slice-e-validation.md)
- [Slice E retention/capacity security review](../security/kingdoms-shared-intelligence-retention-security-review.md)
- [Slice E retention operations runbook](../operations/kingdoms-shared-intelligence-retention.md)
- [Whole-increment exit report](kingdoms-shared-intelligence-exit-report.md)
- [Living shared-intelligence contract](../shared-intelligence.md)

The accepted contract remains directional, two-party opt-in, same-Kingdom, explicit-per-target, source-owned and read-only. Roster/player sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, transitive reshare, public directories/APIs/webhooks, source acquisition, scoring/ranking and automatic decisions remain excluded.

## Related evidence

- [Kingdoms security evidence](../security/README.md)
- [Kingdoms operations](../operations/README.md)
- [Kingdoms interfaces](../interfaces/README.md)
- [Kingdoms testing/evidence](../testing/README.md)
- [Program product documentation](../../../product/README.md)
