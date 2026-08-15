# Contributions operations profile

[← Contributions domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Contributions  
**Code owner:** `app/Domain/Contributions`  
**Primary operational boundary:** Contributions-owned persistence/reporting plus bounded Player/Alliance/Kingdom history composition over Events-owned facts

## 1. Operational purpose and runtime shape

Contributions owns synchronous non-Event contribution/reporting state and unified history/report composition. Events remains the source of truth for Event participation, attendance, results, metrics, and historical context.

Recurring report coordination is operated by Notifications through `contributions:queue-reports`; Contributions supplies report/query semantics and its own persisted report state rather than maintaining an Event reconciliation worker or duplicate Event ledger.

## 2. Persistent state and ownership

Durable Contributions state includes non-Event contribution records, evidence/provenance, correction/reversal relationships, data-quality state, report schedules and report runs.

Event participation/result/metric/history state remains persisted and operated by Events. Contributions reads that state through supported contracts when building Player, Alliance, Kingdom, report, or export views.

## 3. Configuration and runtime dependencies

Primary dependency is PostgreSQL. Unified Event history requires Events-owned tables to be available in the same system-of-record transaction/read environment. Scheduled report production also depends indirectly on scheduler continuity and the shared outbox/Notifications path.

No Contributions-specific external service or cross-domain reconciliation queue is required for core history/reporting.

## 4. Normal flow and background processing

Contributions mutations are synchronous and preserve provenance. History views query Contributions-owned records plus Events-owned facts without copying Event rows into contribution records.

Due report schedules are discovered by the recurring Notifications/Contributions coordination command and materialized with deterministic due-time/report-version identity. Large history/report workloads may be queued when explicitly implemented, but they remain read/report jobs rather than Event materialization jobs.

## 5. Health, observability and diagnostics

Inspect:

- Contributions record status and correction/reversal links;
- Player ID and source domain;
- immutable Event scope/target and occurrence IDs for Event-sourced report rows;
- metric definition/key compatibility;
- report schedule due time;
- report run/outbox state; and
- request/trace/audit correlation for privileged actions.

When a displayed Event fact appears incorrect, diagnose it in Events first. Do not patch a Contributions row to imitate a corrected Event fact.

## 6. Failure modes and diagnosis

Typical issues include unauthorized history target access, missing Event source facts, stale historical-context display data, incompatible metric aggregation, inconsistent Contributions correction/reversal chains, overdue report schedules, missing expected report run/outbox requests, query-volume regressions, or PostgreSQL failure.

Differentiate a source-domain data problem from a Contributions report/query problem before changing state.

## 7. Recovery, replay and reconciliation

There is **no Events-to-Contributions reconciliation/materialization replay** in the final model.

Recovery actions are:

1. correct/recover the owning source domain state;
2. rerun the affected bounded history/report query or report job; and
3. reuse logical report runs according to deterministic report identity where supported.

Never recover by copying Event facts into contribution records, deleting/recreating historical Event data, rewriting immutable Event targets, or fabricating report provenance.

## 8. Backup, restore, migration and rollback

Contributions and Events state are PostgreSQL-backed and restored together by shared database recovery. After restore verify representative:

- durable Player Event history;
- Alliance history including former-member results;
- Kingdom history including transferred-Player results;
- Contributions correction/reversal provenance; and
- due report schedule/run linkage.

EVENT-CONTRIB-001 is greenfield. Fresh migration from zero is required; there is no compatibility/backfill recovery path.

## 9. Capacity, query and performance boundaries

History/report/export queries must be scope-authorized, paginated/bounded, and explainable.

Performance review must separately cover:

- Player lifetime history;
- Alliance historical Event history;
- Kingdom historical Event history;
- compatible metric aggregation; and
- report/export generation.

Avoid N+1 current-affiliation lookups when displaying historical context. Scheduled report catch-up uses the shared configured batch limits. Repository fixtures/query assertions are regression gates, not production volume claims.

## 10. External-service degradation

Core Contributions/Event-history composition has no direct external dependency. Downstream delivery/notification or integration degradation belongs to Notifications/Integrations; source data remains durable while those paths recover.

## 11. Safe operator actions and stop conditions

Safe actions include validating Events-owned source facts, validating Contributions-owned record provenance, rerunning supported read/report jobs, and using shared scheduled-delivery recovery.

Stop if a repair would:

- overwrite historical Event ownership;
- use current membership to rewrite historical participation;
- cross an unauthorized Alliance/Kingdom target;
- destroy correction/reversal evidence;
- duplicate Event facts into Contributions; or
- mark downstream delivery successful manually.

## 12. Evidence, focused runbooks and related documentation

Retain source domain, Player/Event/occurrence IDs, immutable Event target, metric/category identity, report schedule/run IDs, due/report-version identity, request/trace IDs, before/after counts and release SHA.

Scheduled operations are covered by [Notifications scheduled delivery](../../notifications/operations/scheduled-delivery.md).

Related documentation:

- [Contributions domain](../README.md)
- [Event history composition](../event-history-composition.md)
- [Event contribution and historical intelligence](../../events/event-contribution-history.md)
- [Contributions security profile](../security/README.md)
- [Background processing](../../../operations/background-processing.md)