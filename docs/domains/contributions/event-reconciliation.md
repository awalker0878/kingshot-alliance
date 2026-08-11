# Event attendance reconciliation

[← Contributions domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Contributions

## 1. Purpose

Defines the supported cross-domain process that materializes Contribution records from Events-owned attendance without transferring attendance ownership into Contributions.

The current calculated rule is `event_attendance`.

## 2. Scope and non-scope

In scope:

- reading authoritative attended registrations from Events;
- deterministic creation of calculated Contribution records;
- preservation of calculation key/version/provenance;
- reverse/restore behavior when attendance changes; and
- retry/idempotency semantics.

Out of scope:

- editing Event attendance;
- inferring attendance from Rally participation;
- destructive rewrite of historical contribution records; and
- opaque score calculation unrelated to configured categories.

## 3. Model and state

A calculated contribution category identifies `event_attendance` plus a calculation version and explanation.

For each qualifying Event registration, Contributions materializes one logical approved contribution record carrying source/provenance that identifies the Events fact and calculation version.

The contribution record remains Contributions-owned even though its source fact is Events-owned.

## 4. Invariants

1. Events is authoritative for attendance state.
2. Reconciliation never mutates Events persistence.
3. One category/registration produces at most one logical calculated record.
4. Repeated reconciliation is idempotent.
5. `attended` creates or restores the logical record.
6. A change away from `attended` reverses that logical record rather than deleting it.
7. Returning to `attended` restores the same logical record rather than multiplying history.
8. Calculation key/version and source provenance remain preserved for explainability.
9. Cross-Alliance registrations/categories never reconcile together.

## 5. Workflows

### Reconcile attended registration

The manager-triggered/supported reconciliation selects the configured category and Events-owned attended registrations in the same Alliance, derives deterministic logical identity, and creates or restores the approved calculated record.

### Attendance becomes no-show/other non-attended state

A previously materialized calculated record is reversed while preserving its historical identity/provenance.

### Attendance returns to attended

The previously reversed logical record is restored. A second independent record is not created for the same category/registration identity.

### Calculation-version change

New calculations use the configured current version. Historical records retain the version used when they were materialized so old results remain explainable.

## 6. Authorization, tenancy and privacy

Reconciliation requires active-Alliance context plus `contributions.manage`; privileged HTTP execution requires recent password confirmation where applicable.

Both category and Events facts are resolved under the same Alliance. Source identifiers from another tenant fail closed.

## 7. Persistence and query semantics

Contributions owns the calculated record, provenance, reversal/restoration state, and calculation metadata. Events owns Event/occurrence/registration/attendance rows.

Reporting sums only approved, non-reversed contribution records for the applicable category/effective period.

## 8. Events, integrations and background processing

Reconciliation may create audit/outbox evidence for accepted Contribution transitions. It does not create a public Event or Contribution write API.

Scheduled report coordination is a separate Notifications/Contributions workflow and is not part of attendance reconciliation.

## 9. Failure, idempotency and concurrency

- Duplicate runs resolve to the same logical category/registration record.
- Source attendance changes use reverse/restore instead of duplicate creation.
- Missing source data is not interpreted as zero contribution.
- Cross-tenant identifiers fail closed.
- A failed transaction must not leave partially materialized provenance/state.

## 10. Operations and observability

Operators should be able to distinguish source attendance state, materialized record state, calculation version, and reversal/restoration history.

Repair Events source facts in Events and rerun the supported reconciliation; do not directly edit Contribution provenance to imitate a source change.

## 11. Tests and validation

Tests should cover:

- deterministic category/registration identity;
- initial materialization;
- duplicate retry;
- attended → non-attended reversal;
- non-attended → attended restoration;
- calculation-version preservation;
- tenant isolation; and
- Events ownership of attendance.

## 12. Related documentation

- [Contributions domain](README.md)
- [Events domain](../events/README.md)
- [Notifications domain](../notifications/README.md)
- [Authorization](../authorization/README.md)
- [Audit](../audit/README.md)
