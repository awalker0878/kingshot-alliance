# Kingdoms domain

`Kingdoms` is the canonical owner for approved game/kingdom reference capabilities.

The approved product scope is [`KINGDOMS-001` — Kingdoms roster intelligence](../../../docs/product/kingdoms-roster-intelligence-increment.md), with delivery sequencing in the [KINGDOMS-001 implementation plan](../../../docs/product/kingdoms-roster-intelligence-implementation-plan.md).

## Current runtime ownership

Slice A / `K1-P1` introduces the first runtime foundation in this domain:

- first-class global `Kingdom` reference records keyed by canonical kingdom number;
- Kingdom lifecycle state (`active` / `archived`);
- canonical Kingdom resolution for alliance creation;
- an alliance-to-Kingdom relationship replacing the legacy free-form alliance kingdom string; and
- an authorized, password-confirmed, audited alliance Kingdom-association workflow with transactional-outbox publication.

The living contract is documented in [`docs/domains/kingdoms.md`](../../../docs/domains/kingdoms.md).

## Not implemented by Slice A

Game-player identity, alliance roster entries, player snapshots, roster intelligence, CSV import/export and the planned `kingdoms.manage` permission remain later `KINGDOMS-001` phases. Do not add those as dormant schema or represent them as current capability before their owning slice is implemented and validated.
