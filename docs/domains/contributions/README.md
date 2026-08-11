# Contributions domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Contributions`  
**Primary authorization boundary:** `contributions.manage`

## 1. Purpose and ownership

Contributions owns explainable Alliance contribution/participation records, calculation provenance, corrections/reversals, reporting, exports, data-quality state, report schedules/runs, and supported derivation from other domains' facts.

The domain separates recorded facts, calculated metrics, and subjective assessments so results remain explainable rather than opaque scoring.

## 2. Scope

In scope: categories/calculation metadata, contribution records/history, correction/reversal, data quality, reporting/leaderboards, exports, scheduled reports, and supported Events attendance reconciliation.

Out of scope: editing Events attendance, generic notification transport, API credential/webhook ownership, unexplained punitive scoring, and cross-tenant aggregation.

## 3. Domain model

Categories define unit/period/goals/evidence/self-report/leaderboard/data class and, for calculated metrics, calculation key/version/explanation.

Contribution records preserve Alliance/category/membership/source/data class/value/effective period/status/evidence/actor and correction/calculation provenance.

The material Events-derived workflow is documented in [Event attendance reconciliation](event-reconciliation.md).

## 4. Core invariants

1. Contribution results remain explainable from persisted records and versioned provenance.
2. Corrections reverse/link history rather than overwrite it destructively.
3. Approved non-reversed records drive current reporting for the applicable period.
4. Missing evidence/data-quality state never silently changes recorded values.
5. Events remains authoritative for attendance; reconciliation is deterministic and idempotent.
6. Leaderboard participation is explicit per category and may be disabled.
7. Tenant-owned reads/mutations begin from active Alliance context.

## 5. Lifecycles and workflows

Contribution records may be manual, self-reported, or calculated. Pending records follow the supported review/approval path. Corrections create linked replacement history; reversals preserve source evidence.

Data-quality refresh identifies missing evidence/current-period records without changing totals. Reporting provides member progress and manager reporting. CSV/SpreadsheetML exports retain record-level provenance and completion evidence. Scheduled report definitions are Contributions-owned while Notifications coordinates due-time requests.

Events-derived record materialization is defined in [Event attendance reconciliation](event-reconciliation.md).

## 6. Authorization and tenancy

Member surfaces require authenticated/verified active-Alliance context. Management, corrections, approvals, reversals, reconciliation, quality operations, exports, and report schedules require `contributions.manage` plus required recent Identity assurance.

## 7. Cross-domain contracts

Consumes Events attendance facts, Memberships identity, Notifications scheduled-request coordination, Authorization permissions, and Audit/Platform evidence infrastructure.

Exposes approved contribution/report semantics to first-party reporting and the bounded read-only Integrations API representation.

## 8. Persistence and data ownership

Contributions owns categories, records/history, correction/reversal links, calculation metadata, quality flags, report schedules/versions/runs, and export evidence. It does not own source Events facts or Notifications delivery coordination.

## 9. Events, outbox and integrations

Scheduled report requests use Notifications plus the shared Platform outbox. Integrations may expose approved records read-only; it does not gain write/semantic ownership.

## 10. HTTP, UI and API surfaces

Member views show own history/progress and enabled leaderboards. Manager surfaces expose record management, reporting, data quality, reconciliation, exports, and report schedules. External API details are Integrations-owned.

## 11. Background processing

Scheduled report coordination runs through Notifications/shared scheduler/outbox. Any large report work must remain retry-safe and tenant bound.

## 12. Failure, idempotency and concurrency

Cross-tenant identifiers fail closed; correction/reversal preserves history; scheduled report requests use deterministic identity; Events reconciliation behavior is defined in [event-reconciliation.md](event-reconciliation.md).

## 13. Security and privacy

Exports and Alliance-level reporting are privileged disclosure surfaces. Subjective assessments must not be presented as unexplained objective scores, and private evidence/member data stays tenant scoped.

## 14. Observability and operations

Operators should distinguish source-data gaps, evidence gaps, approval state, reconciliation state, scheduled report state, and export/run completion. See [Observability](../../operations/observability.md).

## 15. Testing and architecture enforcement

Tests protect calculation/effective-date semantics, correction/reversal history, reconciliation idempotency, reporting/export authorization, schedules, data quality, and domain ownership boundaries.

## 16. Explicit non-capabilities

Contributions does not edit Events attendance, provide generic messaging transport, own API credentials/webhooks, create opaque punitive scores, or aggregate another Alliance's state.

## 17. Capability documents

- [Event attendance reconciliation](event-reconciliation.md) — deterministic Events→Contributions derivation, reverse/restore behavior, and retry/concurrency semantics.

## 18. Related documentation

- [Events](../events/README.md)
- [Notifications](../notifications/README.md)
- [Integrations](../integrations/README.md)
- [Memberships](../memberships/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Contributions/README.md`](../../../app/Domain/Contributions/README.md)
