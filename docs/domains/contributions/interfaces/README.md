# Contributions interfaces

[← Contributions domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Contributions  
**Code owner:** `app/Domain/Contributions`  
**Primary boundary:** Alliance contribution/reporting workflows, Events-derived reconciliation, privileged report exports, and read-only external representation  
**P4 inventory decision:** Focused contract added — `report-exports.md`; existing `../event-reconciliation.md` reused

## 1. Boundary purpose and ownership

Contributions owns the Alliance contribution facts/reporting contract, including member self-report, manager record management, explainable calculations/corrections, data quality, report schedules/runs, and privileged exports. Events-derived attendance reconciliation is a supported cross-domain input; Notifications owns scheduled delivery coordination; Integrations owns the external machine API representation.

## 2. Surface inventory

`routes/contributions.php` exposes authenticated active-Alliance member/manager surfaces:

- contribution index and management workspace;
- self-report submission, throttled at 20 requests/minute;
- category/manual-record creation;
- approve/correct/reverse workflows;
- Events reconciliation and data-quality refresh/resolve;
- report-schedule creation; and
- manager-only CSV and `.xls` report exports, each throttled at 10 requests/minute.

`GET /api/v1/contributions` is a separate Integrations-owned read-only machine projection of approved records.

## 3. Callers, authorization and tenancy

Member/self-report and reporting reads require authenticated, verified active-Alliance context. Management, correction/reversal, reconciliation, data-quality, scheduling, and exports require `contributions.manage`; the privileged route group also requires recent password confirmation.

Submitted membership/category/record/quality identifiers are re-resolved within the active Alliance before supported actions execute.

## 4. Input and validation contracts

Contribution inputs validate category/membership/value/evidence/period and owning action invariants. Corrections require a reason and preserve linked history; reversals preserve source evidence rather than overwriting records.

Report schedules validate active recipient membership, supported cadence (`daily`, `weekly`, `monthly`), timezone, and next due time. Export requests have no caller-selected tenant and use the active Alliance only.

Events-derived inputs follow [Event attendance reconciliation](../event-reconciliation.md).

## 5. Output and disclosure contracts

First-party reporting exposes only tenant-authorized contribution data and configured leaderboard/reporting views. The privileged file contract is defined in [Report exports](report-exports.md), including exact version/columns/MIME/evidence headers.

The external `/api/v1/contributions` response is deliberately narrower and Integrations-owned: approved records only, bounded to 250 rows with selected provenance fields. It is not the same schema as manager exports.

## 6. Internal actions, queries and services

Supported internal contracts include contribution recording/approval/correction/reversal actions, reporting/data-quality queries/services, `ContributionReportExporter`, report-schedule creation, and [Event attendance reconciliation](../event-reconciliation.md).

Notifications consumes Contributions-owned report schedules/due state through supported coordination actions; it does not become owner of contribution/report semantics.

## 7. Events, outbox and cross-domain consumers

Contribution/report transitions may create Audit/Platform-outbox evidence. Scheduled report delivery requests are handed to Notifications/shared outbox with deterministic delivery identity as documented by the Notifications contracts.

Integrations may expose externally eligible producer events through its webhook contract, but internal outbox publication alone does not define an external Contributions webhook schema.

## 8. Commands, jobs and scheduled work

`contributions:queue-reports {--limit=50}` is a Notifications-owned coordination command that consumes Contributions report-schedule state; the scheduler executes `--limit=50` every minute with one-server/overlap protection.

Contributions itself performs report exports synchronously. See [Notifications interfaces](../../notifications/interfaces/README.md) and [Contributions operations](../operations/README.md) for async/recovery ownership.

## 9. Files, imports, exports and external dependencies

The material file contract is [Report exports](report-exports.md): versioned CSV plus SpreadsheetML XML served as `.xls`. Exports are privileged disclosure surfaces and record immutable report-run/checksum evidence.

There is no accepted Contributions bulk import format. Externally represented contribution data is served via Integrations API JSON, not by reusing the export bytes/schema.

## 10. Failure, idempotency, versioning and compatibility

Cross-tenant IDs and insufficient permission fail closed. Correction/reversal retries preserve historical semantics rather than destructively replacing rows. Events reconciliation is deterministic/idempotent under its accepted contract.

`phase5.v1` is the current report export version. Export schema/version changes require coordinated [Report exports](report-exports.md), tests, and consumer review. Each explicit export request creates a new report-run evidence record; identical bytes may legitimately produce the same checksum across different runs.

## 11. Explicit non-capabilities

Contributions does not:

- edit source Events attendance;
- expose a public write API;
- treat the manager export schema as the external API schema;
- provide anonymous report downloads;
- own generic notification/mail transport; or
- create unexplained punitive/quality scores outside accepted explainable calculations.

## 12. Focused contracts, evidence and related documentation

Focused contracts:

- [Report exports](report-exports.md) — new P4 compatibility-sensitive CSV/SpreadsheetML contract.
- [Event attendance reconciliation](../event-reconciliation.md) — accepted cross-domain Events→Contributions contract reused by P4.

Related documentation:

- [Contributions domain](../README.md)
- [Contributions security](../security/README.md)
- [Contributions operations](../operations/README.md)
- [Integrations API](../../integrations/api.md)
- [Notifications](../../notifications/README.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
