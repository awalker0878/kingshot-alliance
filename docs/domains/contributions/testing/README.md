# Contributions testing and evidence

[← Contributions domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Contributions  
**Code owner:** `app/Domain/Contributions`  
**Primary validation boundary:** Durable Player history, current-authority versus historical-ownership separation, explainable Contributions records/reporting, privileged exports, and scheduled-report source state  
**P5 evidence decision:** Living suite map with hardened Phase 5 acceptance/accessibility/migration evidence reused

## 1. Critical claims and validation ownership

Contributions validation must prove:

- approval/correction/reversal history for Contributions-owned records;
- Player lifetime history across Alliance and Kingdom movement;
- Alliance historical Event reporting including former members;
- Kingdom historical Event reporting including transferred Players;
- current authority versus historical ownership separation;
- sibling Player isolation;
- Events ownership of Event facts/metrics;
- compatible metric aggregation only;
- calculation provenance/explainability;
- data-quality workflow;
- export compatibility; and
- deterministic scheduled-report source semantics.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `Performance`, `TenantIsolation`, and `Unit`. Performance applies to bounded history/reporting queries where executable thresholds exist; no generic throughput SLA is inferred.

## 3. Architecture and domain-boundary validation

Architecture evidence protects the ADR 0011 ownership axes:

- durable `player_id` for personal history;
- immutable Event `alliance_id` for Alliance Event history;
- immutable Event `kingdom_id` for Kingdom Event history;
- current authorization separated from historical ownership; and
- Events remaining canonical owner of Event participation/results/metrics.

Contributions owns read/report composition and its non-Event records. Notifications remains delivery coordinator; Integrations remains owner of external machine representations.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence must cover:

- exact active-Player personal history;
- sibling Player non-leakage;
- current Alliance authority for Alliance-wide history/reporting;
- current exact-Kingdom authority for Kingdom-wide history/reporting;
- former leader loss of organization-wide history access;
- new leader access to pre-tenure organization history;
- `contributions.manage` and recent password confirmation for privileged Contributions mutations/exports; and
- Platform Administrator game-domain bypass rejection.

[Contributions security](../security/README.md) defines private/evidence/data-class boundaries that tests must preserve.

## 5. Feature, interface and integration validation

Feature tests cover Player progress/history and organization reporting plus manager workflows. Integration evidence covers Events history composition, Notifications scheduling handoff, Audit/outbox behavior and export-run persistence.

Events history tests must prove a Player leaving/changing Alliance or Kingdom does not remove historical facts and that current organization membership is not used to filter old Event participants.

[Report exports](../interfaces/report-exports.md) defines current CSV/SpreadsheetML/version/checksum semantics; [Contributions interfaces](../interfaces/README.md) maps boundary families.

## 6. Idempotency, concurrency and asynchronous validation

Correction appends a replacement and reverses the original rather than overwriting Contributions history. Event history composition is read-only with respect to Events, so it cannot create duplicate Event facts.

Event result/metric concurrency is Events-owned and validated in the Events suite. Scheduled report requests use deterministic identities through Notifications/outbox.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 5 exit report](../../../product/phase-5-exit-report.md) remains historical evidence for the earlier Contributions implementation.

EVENT-CONTRIB-001 is greenfield: its canonical migrations/schema are changed directly to the final historical model. There is no legacy backfill, dual-write, or compatibility migration requirement. Fresh PostgreSQL migration from zero is mandatory acceptance evidence for EC-P1 onward.

Current PostgreSQL forward migration and backup/restore remain part of CI; domain recovery semantics are in [Contributions operations](../operations/README.md).

## 8. Performance, query and capacity evidence

Performance evidence will cover bounded Player history, Alliance history, Kingdom history, and report/export queries at realistic Event/result volumes.

History views must avoid N+1 lookups for historical/current affiliation and use bounded pagination. Compatible metric aggregation must use indexed Event Type/metric identity rather than loading arbitrary metric JSON into application memory.

## 9. Accessibility and frontend evidence

[Phase 5 accessibility review](../../../product/phase-5-accessibility.md) remains historical evidence for the earlier Vue surfaces. EVENT-CONTRIB-001 adds dedicated accessibility coverage for My Contributions/History and organization-history views when those phases are implemented.

`npm run check` protects frontend syntax/style/types/build but is not itself accessibility conformance.

## 10. Historical accepted evidence

Historical Phase 5 evidence remains retained as historical evidence and is not rewritten to claim EVENT-CONTRIB-001 semantics.

[Event history composition](../event-reconciliation.md), [Event contribution and historical intelligence](../../events/event-contribution-history.md), and current domain profiles are living contracts.

## 11. Evidence identity, retention and supersession

Historical Phase 5 counts/SHAs/run IDs stay unchanged. Living testing maps evolve with current code/tests.

Future EVENT-CONTRIB-001 acceptance records exact validated SHA/check identities under the [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

No public write API, anonymous export, manual mutation of Events-owned facts, membership-as-history identity, Platform Admin game-domain bypass, or opaque universal score is accepted.

Related documentation:

- [Contributions domain](../README.md)
- [Event history composition](../event-reconciliation.md)
- [Event contribution and historical intelligence](../../events/event-contribution-history.md)
- [Contributions security](../security/README.md)
- [Contributions operations](../operations/README.md)
- [Contributions interfaces](../interfaces/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
