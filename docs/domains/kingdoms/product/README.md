# Kingdoms product and acceptance evidence

[← Kingdoms domain](../README.md)

This directory contains Kingdoms-specific product scope, implementation-plan, slice validation, accessibility and acceptance evidence. `KINGDOMS-001`, `KINGDOMS-002`, `KINGDOMS-003`, and `KINGDOMS-004` are accepted. `KINGDOMS-005` is in progress: `K5-P0` is Complete and `K5-P1` / Slice A is selected pending exact transition-head validation; no shared-observation runtime capability exists yet.

Use the [Kingdoms domain](../README.md) and living capability contracts for current behavior. Product records capture governed increment scope and validation history; a selected future slice does not imply runtime capability or production approval.

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

Current governed state: P0/P1/P2/P3/P4/P5/P6 Complete. Whole-increment runtime candidate `3e0976e8bdd32207bd6314011c26b94fa0f3c118` is protected-green across Dependency Review `31556412455`, CodeQL `31556412413`, and CI `31556412468`, with 429 tests / 9,799 assertions. Production adapter configuration remains empty and no concrete source/cutover is approved.

- [Scope](kingdoms-automated-ingestion-increment.md)
- [Implementation plan](kingdoms-automated-ingestion-implementation-plan.md)
- [K4-P0 decisions](kingdoms-automated-ingestion-p0-decisions.md)
- [K4-P0 security/privacy review](../security/kingdoms-automated-ingestion-p0-security-review.md)
- [K4-P0 exit report](kingdoms-automated-ingestion-p0-exit-report.md)
- [Slice A validation](kingdoms-automated-ingestion-slice-a-validation.md)
- [Slice A security review](../security/kingdoms-automated-ingestion-foundation-security-review.md)
- [Slice B validation](kingdoms-automated-ingestion-slice-b-validation.md)
- [Slice B security review](../security/kingdoms-automated-ingestion-player-promotion-security-review.md)
- [Slice C validation](kingdoms-automated-ingestion-slice-c-validation.md)
- [Slice C security review](../security/kingdoms-automated-ingestion-alliance-promotion-security-review.md)
- [Slice D validation](kingdoms-automated-ingestion-slice-d-validation.md)
- [Slice D security review](../security/kingdoms-automated-ingestion-scheduler-security-review.md)
- [Slice E validation](kingdoms-automated-ingestion-slice-e-validation.md)
- [Slice E security/privacy review](../security/kingdoms-automated-ingestion-operations-security-review.md)
- [Whole-increment exit report](kingdoms-automated-ingestion-exit-report.md)
- [Automated ingestion operations](../operations/kingdoms-automated-ingestion.md)
- [Living automated-ingestion contract](../automated-ingestion.md)

## KINGDOMS-005 — opt-in shared Kingdom intelligence — in progress

`K5-P0` is Complete at validated candidate `d9e05fd06bd08050e5489598406cfb556d5bc0ac`: Dependency Review `31557697685`, CodeQL `31557697793`, and CI `31557697725` all succeeded, including 529 Pint files, PHPStan/Larastan 374/374 zero errors, 429 tests / 9,809 assertions, frontend/build, image, staging, backup/restore and scan.

`K5-P1` / Slice A is selected next but cannot begin until the exact containing P0 Complete / P1 Current status head is protected-green. Slice A is consent-foundation only: directional agreement/invitation persistence and accept/decline/revoke behavior. It will not expose observation data.

- [Scope](kingdoms-shared-intelligence-increment.md)
- [Implementation plan](kingdoms-shared-intelligence-implementation-plan.md)
- [K5-P0 decisions](kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 security/privacy review](../security/kingdoms-shared-intelligence-p0-security-review.md)
- [K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)

The locked contract remains directional, two-party opt-in, same-Kingdom, explicit-per-target and read-only. Roster/player sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, transitive reshare, public directories/APIs/webhooks, source acquisition, scoring/ranking and automatic decisions remain excluded.

## Related evidence

- [Kingdoms security evidence](../security/README.md)
- [Kingdoms operations](../operations/README.md)
- [Kingdoms interfaces](../interfaces/README.md)
- [Kingdoms testing/evidence](../testing/README.md)
- [Program product documentation](../../../product/README.md)
