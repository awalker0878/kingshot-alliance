# Contributions interfaces

[← Contributions domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Contributions  
**Code owner:** `app/Domain/Contributions`  
**Primary boundary:** Player/Alliance/Kingdom contribution-history reporting, Contributions-owned record management, privileged report exports, and read-only external representation  
**P4 inventory decision:** Focused contract added — `report-exports.md`; existing `../event-reconciliation.md` path reused for the current Event-history composition contract

## 1. Boundary purpose and ownership

Contributions owns non-Event contribution facts/reporting and the unified contribution-history read model, including Player lifetime history, Alliance/Kingdom historical reporting, member self-report, manager record management, explainable calculations/corrections, data quality, report schedules/runs, and privileged exports.

Events remains authoritative for Event participation, attendance, results, metrics, and historical context. Contributions consumes those facts through supported reads and does not materialize a second canonical Event ledger. Notifications owns scheduled delivery coordination; Integrations owns the external machine API representation.

## 2. Surface inventory

`routes/contributions.php` currently exposes authenticated contribution member/manager surfaces including contribution index/management, self-report submission, category/manual-record creation, approve/correct/reverse workflows, data-quality refresh/resolve, report scheduling, and manager-only CSV/`.xls` exports.

EVENT-CONTRIB-001 extends first-party read surfaces with:

- active-Player lifetime contribution/Event history;
- authorized Alliance historical Event/contribution reporting; and
- authorized Kingdom historical Event/contribution reporting.

`GET /api/v1/contributions` remains a separate Integrations-owned read-only projection until that contract is explicitly versioned for cross-scope history.

## 3. Callers, authorization and tenancy

Personal history resolves the exact currently active Player and reads only that durable `player_id`'s history.

Alliance-wide reporting/mutations require current active-Player authority for the exact Alliance, including `contributions.manage` for privileged management. Kingdom-wide historical Event reporting requires current exact-Kingdom Player authority.

Current membership/current Kingdom placement controls current authority or eligibility only; it must never remove historical Event facts from an already authorized organization-history query. Platform Administrator status is not a game-domain bypass.

## 4. Input and validation contracts

Contribution inputs validate category/Player/value/evidence/period and owning action invariants. Corrections require a reason and preserve linked history; reversals preserve source evidence rather than overwriting records.

History filters may include date, Event scope/type, historical Alliance/Kingdom context, outcome, metric/category, and source domain. Historical target IDs are resolved against Event-owned immutable targets, never inferred from current membership.

Report schedules validate active recipient Player authority/eligibility, supported cadence (`daily`, `weekly`, `monthly`), timezone, and next due time. Export requests cannot select an unauthorized target.

Event-history composition follows [Event history composition](../event-reconciliation.md).

## 5. Output and disclosure contracts

Personal output contains only the exact active Player's history. Alliance/Kingdom organization output is broader private operational data and requires current scope authority.

Historical rows may expose occurrence-time represented Alliance/Kingdom/name context and current affiliation side by side where useful, but current affiliation is informational only.

The privileged file contract is defined in [Report exports](report-exports.md). The external `/api/v1/contributions` representation remains deliberately narrower and Integrations-owned.

## 6. Internal actions, queries and services

Supported Contributions-owned internal contracts include contribution recording/approval/correction/reversal actions, reporting/data-quality queries/services, `ContributionReportExporter`, report-schedule creation, and unified history/report composition.

Events supplies authoritative Event history facts through supported query/read contracts; Contributions does not call Events mutation persistence to fabricate report rows.

Notifications consumes Contributions-owned report schedules/due state through supported coordination actions; it does not become owner of contribution/report semantics.

## 7. Events, outbox and cross-domain consumers

Contribution/report mutations may create Audit/Platform-outbox evidence. Event mutations produce Events-owned evidence. Read composition does not duplicate Event writes/outbox facts.

Scheduled report delivery requests are handed to Notifications/shared outbox with deterministic delivery identity. Integrations may expose explicitly approved producer/read contracts but internal outbox publication alone does not define an external Contributions schema.

## 8. Commands, jobs and scheduled work

`contributions:queue-reports {--limit=50}` is a Notifications-owned coordination command that consumes Contributions report-schedule state; the scheduler executes with one-server/overlap protection.

Large history exports may be queued if a future volume threshold requires asynchronous delivery, but no Event-to-Contributions materialization/reconciliation job is part of the final EVENT-CONTRIB-001 architecture.

## 9. Files, imports, exports and external dependencies

The material file contract is [Report exports](report-exports.md): versioned CSV plus SpreadsheetML XML served as `.xls`. `ContributionReportExporter::REPORT_VERSION` is `event-history.v2`, and current report rows include Event scope/type/occurrence, historical Alliance/Kingdom context, Player result score/rank/outcome, normalized metric identity/dimension/value/unit, and provenance alongside Contributions-owned records.

There is no accepted Contributions bulk import format. Externally represented data is served via Integrations API JSON, not by reusing export bytes/schema.

## 10. Failure, idempotency, versioning and compatibility

Unauthorized target IDs and insufficient current authority fail closed. Correction/reversal retries preserve historical semantics rather than destructively replacing rows. Event-history composition is read-only with respect to Events and cannot create duplicate Event facts.

Because the database is greenfield for EVENT-CONTRIB-001, incompatible internal schema assumptions are replaced directly rather than supported through dual-write or compatibility shims. External/export contracts remain explicitly versioned where consumers exist.

## 11. Explicit non-capabilities

Contributions does not:

- edit or duplicate source Event facts merely for reporting;
- use current membership as historical identity;
- expose a public write API;
- grant Platform Admin a game-domain history bypass;
- treat the manager export schema as the external API schema;
- provide anonymous report downloads;
- own generic notification/mail transport; or
- create an unexplained universal score from incompatible Event metrics.

## 12. Focused contracts, evidence and related documentation

Focused contracts:

- [Report exports](report-exports.md) — compatibility-sensitive CSV/SpreadsheetML contract.
- [Event history composition](../event-reconciliation.md) — Contributions-side Events history/read composition contract.
- [Event contribution and historical intelligence](../../events/event-contribution-history.md) — durable historical ownership and metric semantics.

Related documentation:

- [Contributions domain](../README.md)
- [Contributions security](../security/README.md)
- [Contributions operations](../operations/README.md)
- [Integrations API](../../integrations/api.md)
- [Notifications](../../notifications/README.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
