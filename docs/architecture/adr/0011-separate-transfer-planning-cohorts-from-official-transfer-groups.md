# ADR 0011 — Separate transfer planning cohorts from official Transfer Groups

Status: Accepted — 2026-08-23

## Context

Kingdom Transfer previously used `TransferGroup` for an Alliance-defined planning bucket used to coordinate participants. KingShot also uses **Transfer Group** as an official, event-scoped game concept that determines which Kingdoms may transfer with one another during a specific Transfer Window.

Keeping both meanings would make domain rules, schema, routes, telemetry, UX and support diagnostics ambiguous. The application is not deployed, so retaining compatibility vocabulary has no product value and would create a durable modeling defect.

## Decision

1. The Alliance-owned planning concept is renamed cleanly to **Transfer Cohort** (`TransferCohort`, `transfer_cohorts`, `transfer_cohort_id`).
2. **Transfer Group** means only the official KingShot grouping of Kingdoms for one Transfer Window.
3. Official Transfer Group membership is window-scoped sourced game truth. It is never written as a timeless property of a Kingdom.
4. Mutable Governor facts used by transfer eligibility are append-only observations with provenance and an explicit validity boundary rather than current-truth columns.
5. Eligibility remains derived by `GameWorld/KingdomTransfers` from a selected Transfer Window, target Kingdom, official game facts and current observations. No persisted eligibility boolean is introduced.
6. Existing workflow readiness remains an independent Alliance planning concern and is never mutated automatically by eligibility evaluation.
7. External/community material may identify candidate rules but cannot make an eligibility requirement authoritative. Missing authoritative evidence remains an explicit unknown/evidence gate.

## Consequences

- Code, migrations, routes, localization, audit/outbox event names and product language must use `cohort` for Alliance planning buckets.
- No `TransferGroup` compatibility class, alias, table, route or dual-read/dual-write path is retained for the old planning meaning.
- The official group model may reuse the `TransferGroup` name because the conflicting planning concept is removed first.
- Eligibility queries compose owner-scoped transfer state but domain decisions remain in typed services/value objects rather than controllers, Vue or generic read models.
- Corrections to sourced game facts preserve history. Current assessment changes are explainable from the observations and rule inputs used at evaluation time.

## Rejected alternatives

### Keep `TransferGroup` for planning and call the official concept something else

Rejected because it preserves misleading domain vocabulary and forces every future developer/operator to translate between product and game terminology.

### Store official group on `Kingdom`

Rejected because official grouping can change between Transfer Windows.

### Persist `is_eligible`

Rejected because eligibility depends on time, target, phase, evidence freshness and mutable observations; a boolean would become stale and would not explain the decision.

### Infer unpublished KingShot eligibility rules from community tools

Rejected because discovery evidence is not authoritative product truth.
