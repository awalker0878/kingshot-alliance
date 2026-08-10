# Contributions domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Contributions`  
**Primary authorization boundary:** `contributions.manage`

## 1. Purpose and ownership

Contributions owns explainable contribution and participation reporting for an Alliance. It deliberately separates recorded facts, calculated metrics, and subjective assessments so totals remain understandable, reproducible, and auditable.

The domain owns contribution categories, historical contribution records, corrections/reversals, reporting semantics, report schedules/runs, data-quality flags, exports, and contribution-specific calculations. It does not own the source facts of other domains simply because those facts may be used in a calculation.

## 2. Scope

### In scope

- contribution category configuration and versioned calculation metadata;
- manual, member self-reported, and event-derived contribution records;
- approvals, corrections, reversals, and historical provenance;
- event-attendance reconciliation into contribution records;
- contribution/member progress, leaderboards, management reporting, and data-quality flags;
- CSV and SpreadsheetML exports;
- contribution report schedules and report-run evidence; and
- cross-domain coordination with Events, Notifications, Integrations, Audit, and Platform.

### Out of scope

- editing Events attendance truth;
- generic notification transport ownership;
- API credential or webhook persistence;
- opaque or punitive scoring that cannot be explained from recorded facts and configured rules; and
- cross-tenant reporting.

## 3. Domain model

### Contribution categories

A category defines:

- name, description, and unit;
- daily, weekly, monthly, season, or custom period;
- optional per-member goal;
- evidence requirement;
- whether members may self-report;
- whether the category participates in leaderboards;
- data class: `recorded_fact`, `calculated_metric`, or `subjective_assessment`; and
- for calculated metrics, calculation key, calculation version, and human-readable explanation.

Season and custom categories carry explicit effective start/end dates. Calculated categories must carry versioned calculation metadata.

### Contribution records

Records are historical observations rather than mutable totals. Each record carries:

- Alliance, category, and membership identity;
- source (`manual`, `self_reported`, or `event_participation`);
- data class and value;
- effective period;
- pending, approved, or reversed status;
- evidence and actor attribution;
- calculation provenance when derived; and
- correction/reversal provenance.

Corrections create replacement records linked through `correction_of_record_id`; the original remains retained and is reversed.

### Event-derived participation

The supported calculated rule is `event_attendance`. Reconciliation reads Events-owned registrations whose status is `attended`, materializes one approved contribution record per category/registration, and records the configured calculation version.

### Data quality

Data-quality flags identify:

- records missing evidence required by their category; and
- active members missing a record for an active category/current period.

Refreshing data quality never changes contribution values.

### Reports and schedules

Report schedules are Alliance-scoped and identify recipient membership, cadence, time zone, next due time, and report version. Report runs retain report version, request/execution status, and export evidence.

## 4. Core invariants

1. Contribution history is explainable from persisted records and calculation provenance.
2. Corrections never silently overwrite historical records; they reverse the original and create a linked replacement.
3. Event attendance remains authoritative in Events; Contributions may reconcile it but never independently edits it.
4. `event_attendance` reconciliation is idempotent.
5. If attendance changes away from `attended`, the derived contribution record is reversed; if it returns to `attended`, the same record is restored rather than multiplied.
6. Data-quality refresh never changes contribution values.
7. Leaderboards sum only approved, non-reversed records for the category's current effective period.
8. A category can opt out of leaderboards completely.
9. Calculation versions are preserved so historical results remain explainable after rules change.
10. All tenant-owned reads and mutations begin from active Alliance context.

## 5. Lifecycles and workflows

### Record contribution

Records may enter from manual entry, member self-report, or an approved calculated source. Pending records can be reviewed/approved according to the domain's management workflow.

### Correct or reverse

A correction creates a replacement linked to the original and reverses the original. Reversal remains attributable and preserves the historical chain.

### Reconcile event attendance

Reconciliation reads Events-domain attendance, creates/restores/reverses the corresponding deterministic calculated records, and never mutates the Events source.

### Refresh data quality

The domain identifies missing evidence and missing current-period records without changing measured values.

### Reporting

Member reporting contains the member's own record history/progress plus Alliance leaderboards only where enabled.

The management dashboard includes:

- contribution totals and pending approvals;
- attendance/no-show summary;
- recruitment volume/joined count;
- membership joins/leaves;
- missing-data flags;
- calculation descriptions/versions;
- scheduled report state; and
- report-run history.

### Export

Interactive exports support CSV and Excel-readable SpreadsheetML. Rows preserve record-level provenance rather than publishing only aggregate totals.

Each export records:

- report version;
- format;
- row count;
- SHA-256 checksum;
- requesting user and Alliance;
- completion timestamp.

### Schedule report

Schedules identify recipient, cadence, time zone, next due time, and report version. Notifications converts due schedule occurrences into durable report requests using deterministic idempotency.

## 6. Authorization and tenancy

All member surfaces require authenticated, verified, active-Alliance context.

Management, exports, corrections, approvals, reversals, reconciliation, quality operations, and schedules require `contributions.manage`. Privileged HTTP mutations also require recent password confirmation.

Mutable identifiers are re-resolved under the active `alliance_id` and fail closed for another tenant.

## 7. Cross-domain contracts

### Consumes

- **Events** — authoritative attendance facts used by `event_attendance` reconciliation.
- **Memberships** — Alliance membership identities used for contribution ownership/report recipients.
- **Notifications** — due-time coordination for scheduled report requests.
- **Authorization** — `contributions.manage` and member access decisions.
- **Audit/Platform** — attributable audit and transactional-outbox infrastructure.

### Exposes

- contribution records/calculation/report semantics to first-party reporting; and
- approved contribution records to the documented read-only Integrations API contract.

Integrations does not become owner of the contribution records it exposes.

## 8. Persistence and data ownership

Contributions owns categories, historical records, correction/reversal links, data-quality flags, report schedules, report versions, and report runs.

Reporting is derived from approved domain state rather than maintained as an opaque second mutable total store.

## 9. Events, outbox and integrations

Notifications queues due scheduled-report requests through the transactional outbox with a deterministic idempotency key. Contributions owns report schedules/versions/runs and the meaning of the report; Notifications owns due-time delivery coordination.

The documented read-only integration may expose approved contribution records. This does not create a write API or transfer mutation ownership to Integrations.

## 10. HTTP, UI and API surfaces

Member-facing surfaces show only the member's own history/progress plus explicitly enabled Alliance leaderboards.

Management surfaces expose Alliance-level reporting, quality flags, record management, reconciliation, exports, and report schedules under `contributions.manage`.

The external read-only API contract is documented by the Integrations domain.

## 11. Background processing

Scheduled report due-time coordination runs through Notifications and the shared scheduler/outbox foundation. Contributions retains the schedule/run state needed to make repeated execution safe.

Large-report implementation must remain retry-safe and tenant-bound; no hidden cross-tenant report worker is authorized.

## 12. Failure, idempotency and concurrency

- Event reconciliation is idempotent per category/registration.
- Attendance changes reverse/restore the same logical calculated record rather than producing duplicate history.
- Corrections/reversals preserve source history instead of destructive mutation.
- Report requests use deterministic schedule/due-time/version identity through Notifications.
- Cross-tenant identifiers fail closed when re-resolved beneath the active Alliance.

## 13. Security and privacy

Exports, management reporting, corrections, approvals, and schedules are privileged disclosure/mutation surfaces. They require the management permission and recent password confirmation where applicable.

Reporting must not collapse subjective assessments into unexplained objective scores. Sensitive evidence and member information remain tenant scoped and should not be copied into public surfaces or routine logs.

## 14. Observability and operations

Operators should be able to distinguish:

- missing source data;
- missing evidence;
- pending approvals;
- reconciliation state;
- scheduled report state; and
- report-run/export completion.

See [Notifications](../notifications/README.md), [Background processing](../../operations/background-processing.md), and [Observability](../../operations/observability.md).

## 15. Testing and architecture enforcement

Tests should protect:

- calculation accuracy/effective dating;
- correction and reversal history;
- event-reconciliation idempotency;
- export authorization and tenant isolation;
- scheduled-report retry safety;
- data-quality behavior; and
- the architectural boundary that Events owns attendance while Notifications owns due-time coordination.

## 16. Explicit non-capabilities

Contributions does not:

- independently edit Events attendance;
- provide a generic email/SMS/push transport;
- own API credentials/webhooks;
- create unexplained punitive member scores; or
- aggregate another Alliance's contribution records.

## 17. Capability documents

No separate Contributions capability files are required at present; the current contract remains coherent in this root document.

## 18. Related documentation

- [Events domain](../events/README.md)
- [Notifications domain](../notifications/README.md)
- [Integrations domain](../integrations/README.md)
- [Memberships domain](../memberships/README.md)
- [Security baseline](../../security/security-baseline.md)
- [Operations index](../../operations/README.md)
- [`app/Domain/Contributions/README.md`](../../../app/Domain/Contributions/README.md)
