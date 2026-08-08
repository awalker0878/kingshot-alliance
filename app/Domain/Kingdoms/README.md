# Kingdoms domain

`Kingdoms` is the canonical owner for approved game/kingdom reference capabilities.

The approved product scope is [`KINGDOMS-001` — Kingdoms roster intelligence](../../../docs/product/kingdoms-roster-intelligence-increment.md), with delivery sequencing in the [KINGDOMS-001 implementation plan](../../../docs/product/kingdoms-roster-intelligence-implementation-plan.md).

## Current runtime ownership

Slice A / `K1-P1` established the first runtime foundation in this domain:

- first-class global `Kingdom` reference records keyed by canonical kingdom number;
- Kingdom lifecycle state (`active` / `archived`);
- canonical Kingdom resolution for alliance creation;
- an alliance-to-Kingdom relationship replacing the legacy free-form alliance kingdom string; and
- an authorized, password-confirmed, audited alliance Kingdom-association workflow with transactional-outbox publication.

Slice B / `K1-P2` adds the validated roster candidate:

- global neutral `KingdomPlayer` identity scoped to a Kingdom;
- alliance-owned `AllianceRosterEntry` observations and optional same-alliance membership linkage;
- `kingdoms.manage` for Owner, Leader and Officer built-in roles;
- member roster reads under `alliance.view`;
- password-confirmed roster create/update/mark-left mutations with audit/outbox evidence;
- tenant-scoped search/filter behavior and linkage-gap reporting; and
- private manager notes/contact details excluded from ordinary member roster payloads.

Slice C1 / `K1-P3` adds the snapshot-history implementation candidate:

- append-only alliance-scoped `PlayerSnapshot` observations tied to roster and neutral game-player identity;
- signed 64-bit integer power, optional progression/level and observed alliance/tag values;
- capture time, manual source and actor provenance;
- deterministic idempotency for exact accepted-observation retries;
- latest-observation and history queries scoped by active Alliance;
- roster current/stale/missing semantics driven by snapshot capture time rather than mutable roster metadata; and
- member-visible history plus password-confirmed `kingdoms.manage` snapshot recording.

The living contracts are documented in [`docs/domains/kingdoms.md`](../../../docs/domains/kingdoms.md), [`docs/domains/kingdoms-roster.md`](../../../docs/domains/kingdoms-roster.md), and [`docs/domains/kingdoms-snapshots.md`](../../../docs/domains/kingdoms-snapshots.md).

## Not implemented by Slice C1

Roster intelligence/trend calculations (`K1-P4`), CSV import/export (`K1-P5`), transfer planning, diplomacy/NAP intelligence and automated game-data ingestion remain later `KINGDOMS-001` phases or follow-on candidates. Do not add those as dormant schema or represent them as current accepted capability before their owning slice is implemented and passes its gate.
