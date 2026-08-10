# KINGDOMS-003 Slice A validation

[← KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)

**Scope:** `KINGDOMS-003` Slice A / `K3-P1`  
**Status:** **Validated**  
**Validated runtime SHA:** `f57b81a7550b9a5cb94a2ae233e31da5805c8b55`  
**Baseline:** fully green `K3-P0` contract head `838a65afb26b43bb5a686c2a32f99594362098c4`

## Validated capability

Slice A validates the first runtime foundation for `KINGDOMS-003`:

- global neutral `KingdomAlliance` references scoped to one Kingdom;
- alliance-owned `TrackedKingdomAlliance` relationships with captured Kingdom context;
- stable game alliance ID as the only automatic neutral identity reuse key;
- same name/tag without stable identity never auto-merges;
- explicit assign-once stable-ID resolution with conflict/replacement failure rather than silent merge;
- active/archived tenant tracking with one active row per tenant/reference and retained historical rows;
- Alliance-Kingdom drift preserving historical reads while normal edits fail closed and archival remains available;
- member-safe versus manager-private tracking presentation;
- `alliance.view` safe reads and `kingdoms.manage` plus recent password confirmation for mutations;
- private manager notes excluded from audit/outbox payload metadata;
- internal-only `kingdoms.alliance_intelligence_*` durability events; and
- no observations, diplomacy/NAP state, contacts, derived intelligence, automated ingestion, scoring/ranking, shared cross-tenant intelligence or public Kingdoms API/webhook surface.

## Protected validation

Exact runtime SHA `f57b81a7550b9a5cb94a2ae233e31da5805c8b55` passed:

- Dependency Review run `31340635373`: **success**;
- CodeQL run `31340635382`: **success**; and
- CI run `31340635375`: **success**.

CI evidence on that exact SHA:

- frontend dependency audit, ESLint, pinned Prettier, Vue/TypeScript checks and production build: success;
- PHP dependency audit: success / no security vulnerability advisories;
- PostgreSQL migrations, including `2026_08_09_140000_create_kingdom_alliance_tracking.php`: success;
- Pint: **452 files passed**;
- PHPStan/Larastan: **324 files / 0 errors**;
- ParaTest/PHPUnit: **314 tests / 3661 assertions**;
- immutable production image build/identification: success;
- ephemeral staging deployment: success;
- backup/restore demonstration: success;
- image vulnerability scan: success; and
- staging cleanup: success.

## Acceptance notes

The protected suite covers stable-ID reuse/conflict behavior, duplicate name/tag no-merge behavior, cross-tenant submitted-ID isolation, permission/password boundaries, member/private serialization, Kingdom drift/archive recovery, archive idempotency/re-tracking history, private-note event safety, complete Kingdoms migration rollback/reapply, accessibility, public-API non-exposure and future-slice schema guards.

A temporary diagnostic validation head was used only to print the repository's pinned Pint/Prettier rewrites. `package.json` and `composer.json` were restored to their original blobs before the validated runtime SHA; no diagnostic script is present in the validated diff.

`K3-P1` is **Validated**. `KINGDOMS-003` remains **In progress**; append-oriented alliance observations remain `K3-P2`, and later diplomacy/contact/intelligence slices remain pending. Whole-increment acceptance remains `K3-P6`.
