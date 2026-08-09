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

Slice C1 / `K1-P3` adds the validated snapshot-history candidate:

- append-only alliance-scoped `PlayerSnapshot` observations tied to roster and neutral game-player identity;
- signed 64-bit integer power, optional progression/level and observed alliance/tag values;
- capture time, manual source and actor provenance;
- deterministic idempotency for exact accepted-observation retries;
- latest-observation and history queries scoped by active Alliance;
- roster current/stale/missing semantics driven by snapshot capture time rather than mutable roster metadata; and
- member-visible history plus password-confirmed `kingdoms.manage` snapshot recording.

Slice C2 / `K1-P4` adds the validated roster-intelligence candidate:

- active/tracked roster totals, exact recorded-power average and median;
- current/stale/missing snapshot-quality counts;
- seven-day joins/departures and membership-linkage coverage;
- exact aggregate 7-day and 30-day power change using bounded historical baselines with no interpolation;
- member-visible aggregate intelligence under `alliance.view`; and
- manager-only, alphabetical individual comparison detail under `kingdoms.manage` with no ranking or scoring.

Slice D / `K1-P5` adds the validated controlled CSV migration candidate:

- strict `kingdoms-roster.v1` UTF-8 CSV schema with 1 MiB / 500-row limits;
- dry-run create/update/ambiguous/rejected row classification before roster persistence;
- stable game ID as the only automatic identity-match key and explicit manager resolution for display-name ambiguity;
- checksum-backed alliance-owned import records and atomic password-confirmed confirmation;
- fail-closed preview-drift checks plus idempotent committed-import retries;
- CSV provenance carried through the existing roster/snapshot actions, audit trail and transactional outbox; and
- member/management current-roster exports with private-field gating, private/no-store responses and spreadsheet-formula neutralization.

Slices B, C1, C2 and D have passed their protected implementation gates and remain review candidates until accepted into the dependency stack.

The living contracts are documented in [`docs/domains/kingdoms.md`](../../../docs/domains/kingdoms.md), [`docs/domains/kingdoms-roster.md`](../../../docs/domains/kingdoms-roster.md), [`docs/domains/kingdoms-snapshots.md`](../../../docs/domains/kingdoms-snapshots.md), [`docs/domains/kingdoms-intelligence.md`](../../../docs/domains/kingdoms-intelligence.md), and [`docs/domains/kingdoms-csv-migration.md`](../../../docs/domains/kingdoms-csv-migration.md).

## Not implemented by Slice D

Transfer planning, diplomacy/NAP intelligence, public roster/intelligence API or webhook exposure, cross-alliance rankings, automated scoring/recommendations and automated game-data ingestion remain follow-on candidates and are not approved current runtime scope. `K1-P6` whole-increment hardening/acceptance also remains outstanding. Do not add follow-on capabilities as dormant schema or represent `KINGDOMS-001` as Accepted before its final acceptance gate completes.
