# Product

Status: Current — 2026-09-04

This area explains **what Kingshot Alliance provides to users**. It does not define code ownership or operational procedure.

Alliance Assistant is delivered as the constrained, authorization-aware read/composition capability defined in [Alliance Assistant](alliance-assistant.md); its completed release evidence is recorded in the [capability delivery ledger](capability-delivery-ledger.md).

The cross-capability work is governed by the [Capability Extension Program](capability-extension-program.md). That contract distinguishes already-complete product capability from selected extensions and evidence-gated extensions, and defines ownership/provenance before application implementation begins. Intelligence Change Detection is now a current complete Phase 11 capability governed by [Intelligence Change Detection](intelligence-change-detection.md) with verification evidence in its [delivery ledger](intelligence-change-detection-delivery-ledger.md). Progression planning and calculator qualification are further governed by the [Progression Goal Planner and Calculator Evidence Program](progression-goal-planner-calculators.md), which is the implementation source of truth for `GP-*`, `CE-*` and `CI-*` work. Its live implementation reconciliation is recorded in the [Progression Goal Planner and Calculator delivery ledger](progression-goal-planner-calculators-delivery-ledger.md), with verification-discovered closeout requirements captured in the [Progression Goal Planner and Calculator verification amendment](progression-goal-planner-calculators-verification-amendment.md).

The Communications recipient-delivery expansion is defined by [Communications — Recipient Delivery & Notification Experience](communications-recipient-delivery-expansion.md), verified by the [acceptance matrix](communications-recipient-delivery-acceptance.md), and tracked to release closure in its [delivery ledger](communications-recipient-delivery-ledger.md). It preserves source-context ownership while adding one logical inbox message, recipient routing policy, multiple named destinations, quiet hours, Web Push, email and bounded digest delivery.

Phases 13–25 are governed by the [Kingshot Capability Expansion Program](kingshot-capability-expansion-program.md). The program is complete through Phase 25 for all evidence-supported capabilities; it establishes the mandatory named-Event identity/evidence gate, preserves existing capability ownership, and keeps KvK correctly disabled at Phase 17 until canonical identity and workflow evidence exist.

- [Product overview](product-overview.md)
- [Capability catalogue](capability-catalogue.md)
- [Capability completeness plan](capability-gap-analysis.md)
- [Capability delivery ledger](capability-delivery-ledger.md)
- [Capability Extension Program](capability-extension-program.md)
- [Communications — Recipient Delivery & Notification Experience](communications-recipient-delivery-expansion.md)
- [Communications Recipient Delivery acceptance](communications-recipient-delivery-acceptance.md)
- [Communications Recipient Delivery delivery ledger](communications-recipient-delivery-ledger.md)
- [Alliance Capability Expansion](alliance-capability-expansion.md)
- [Alliance Capability Expansion acceptance](alliance-capability-expansion-acceptance.md)
- [Alliance Capability Expansion delivery ledger](alliance-capability-expansion-delivery-ledger.md)
- [Kingshot Capability Expansion Program](kingshot-capability-expansion-program.md)
- [Intelligence Change Detection](intelligence-change-detection.md)
- [Intelligence Change Detection delivery ledger](intelligence-change-detection-delivery-ledger.md)
- [Progression Goal Planner and Calculator Evidence Program](progression-goal-planner-calculators.md)
- [Progression Goal Planner and Calculator delivery ledger](progression-goal-planner-calculators-delivery-ledger.md)
- [Progression Goal Planner and Calculator verification amendment](progression-goal-planner-calculators-verification-amendment.md)
- [Event Command — Readiness & Closeout](event-readiness-closeout.md)
- [Alliance Assistant](alliance-assistant.md)
- [Factual Governor Progression](factual-governor-progression.md)
- [Kingdom Transfer Planning](kingdom-transfer-planning.md)
- [Bear Hunt Debrief](bear-hunt-debrief.md)
- [Screenshot Intake](screenshot-intake.md)
- [Alliance Content game parity](alliance-content-game-parity.md)
- [Terminology](terminology.md)
- [Experience principles](experience/README.md)
- [Primary user journeys](experience/user-journeys.md)

Territory & Hive Planner current product truth is recorded in the [Capability catalogue](capability-catalogue.md), [Capability completeness plan](capability-gap-analysis.md), [Primary user journeys](experience/user-journeys.md), and the architecture/ADR documentation under `docs/architecture`.

Architecture is documented under [Architecture](../architecture/README.md); physical implementation under [Codebase](../codebase/README.md); deploy/recovery under [Operations](../operations/README.md).

## Product truth rule

Describe implemented user outcomes in present tense. Approved but unimplemented work is described only in an explicit implementation contract and must be labelled **Selected extension** or **Evidence-gated extension** until its delivery-ledger exit criteria are satisfied. Do not preserve completed phase/DCP documentation as a second product model, and do not infer future roadmap from unused code or historical plans. Git history is the archive for retired program documents.
