# Kingdoms roster intelligence product increment

[← Product and program documentation](README.md)

**Status:** Approved roadmap scope — implementation in progress  
**Scope ID:** `KINGDOMS-001`  
**Owning domain:** `Kingdoms`  
**Delivery model:** Post-program product increment; this is **not Phase 7**  
**Baseline dependency:** Accepted Phase 0–6 repository baseline and production-hardening controls

## 1. Purpose

`KINGDOMS-001` activates the currently reserved `Kingdoms` domain with a first-class game-world reference model and alliance roster intelligence.

The increment should replace the current loose kingdom string and spreadsheet-style roster tracking with a durable model that can answer:

- which kingdom an alliance belongs to;
- which Kingshot players the alliance is tracking;
- which tracked game players correspond to application memberships;
- how player and alliance power changes over time;
- which roster records are stale, newly joined, recently departed, or unlinked; and
- what historical observations produced current roster metrics.

The initial implementation is deliberately **manual/import first**. It must not depend on an unofficial scraper, bot, OCR workflow, or undocumented Kingshot interface. Approved automated ingestion can be added later against the same domain model.

## 2. Product outcome

Alliance leadership should be able to maintain an accurate game roster inside Kingshot Alliance and use historical snapshots to understand roster health and growth without maintaining a separate spreadsheet.

Members should be able to see the alliance roster and their linked game identity without conflating an application account with a Kingshot player record.

The increment establishes a durable foundation for later transfer planning, kingdom diplomacy, external game-data ingestion, and opt-in cross-alliance intelligence without implementing those capabilities now.

## 3. Core domain principles

### Kingdom is global reference data

A `Kingdom` represents a Kingshot kingdom/server and is not owned by one alliance. Multiple platform alliances may reference the same kingdom.

A kingdom number is the canonical external identifier. Alliance-scoped permissions never grant broad cross-alliance access merely because two alliances share a kingdom.

### Application identity is not game identity

A global application `User`, an `AllianceMembership`, and a Kingshot game player are separate concepts:

```text
User
  └─ AllianceMembership
       └─ optional link to alliance roster entry

Kingdom
  └─ KingdomPlayer
       └─ alliance-scoped roster entry
            └─ alliance-scoped snapshots
```

A Kingshot player may exist without an application account. An application user may belong to multiple alliances. A player name is display data and must not be treated as a stable identity key.

### Tenant observations remain tenant-scoped

A neutral kingdom/player reference must not become a path around alliance tenancy. Roster notes, membership links, status, snapshots, imports, exports, and derived metrics remain alliance-scoped.

No initial feature exposes another alliance's tracked roster or observations, even when both alliances are in the same kingdom.

### History is append-oriented

Power and other observed game values should be recorded as snapshots rather than overwritten as the only source of truth. Current views may project the latest observation, while historical values remain available for trends and auditability.

## 4. In-scope capabilities

### 4.1 First-class kingdoms

Introduce a first-class `Kingdom` record with at minimum:

- canonical kingdom number;
- lifecycle status such as `active` or `archived`;
- created/updated timestamps; and
- audit metadata for privileged changes where applicable.

Replace the current free-form `alliances.kingdom` representation with a relationship to the canonical kingdom model. The migration must preserve existing alliance kingdom values where they can be normalized safely and must not retain a permanent compatibility shim after the migration is complete.

An alliance can belong to one current kingdom at a time. Changing that association is a privileged alliance-management action and is audited.

### 4.2 Game-player identity

Introduce a neutral `KingdomPlayer` identity that belongs to a kingdom and can represent a Kingshot player independently of any site account.

The initial identity model should support:

- optional stable game/player identifier when known;
- current display name;
- kingdom relationship;
- created/updated timestamps; and
- future-safe merge/reference handling without deduplicating solely by player name.

If no stable game identifier exists, names must not be assumed globally unique.

### 4.3 Alliance roster entries

Introduce an alliance-scoped roster record that connects an alliance to a tracked `KingdomPlayer`.

A roster entry should support:

- alliance ID;
- kingdom-player ID;
- optional linked `AllianceMembership`;
- current observed player name when useful for the alliance view;
- game-side alliance role/rank when recorded;
- roster state such as active, tracked, or left;
- joined/left dates when known;
- leadership notes kept private to authorized roster managers;
- last-observed timestamp; and
- source/provenance for the latest update.

A membership link is optional. Linking must verify that the membership belongs to the same alliance as the roster entry.

The system should surface both mismatch directions:

- active application memberships with no linked game-player roster entry; and
- active game roster entries with no linked application membership.

### 4.4 Player snapshots

Record time-series observations rather than replacing historical game data.

An alliance-scoped player snapshot should support at minimum:

- alliance ID;
- roster/player reference;
- observed player name;
- power;
- optional level or comparable progression field;
- observed game-side alliance/tag when supplied;
- captured timestamp;
- source (`manual` or `csv` initially); and
- actor/import provenance where applicable.

Snapshot uniqueness/idempotency must prevent the same accepted observation from being multiplied by retries without blocking legitimate later observations.

The latest snapshot may be projected onto roster views for fast display, but historical snapshots remain the source for trends.

### 4.5 Roster management UI

Provide an authenticated alliance roster workspace that supports:

- list/search/filter roster entries;
- add a game player manually;
- edit alliance-scoped roster state and notes;
- link/unlink an application membership;
- record a new snapshot;
- mark a player as left without deleting history;
- inspect snapshot history; and
- identify stale or incomplete records.

Useful filters should include roster state, linked/unlinked membership, game role/rank, power range where data exists, and stale last-observed date.

### 4.6 CSV import and export

Provide a controlled roster import workflow so alliances can move off spreadsheets without waiting for automated game integration.

Import must include:

1. upload/parse;
2. validation and dry-run preview;
3. explicit confirmation;
4. deterministic matching rules;
5. row-level error reporting; and
6. an auditable committed result.

The first supported import should accept a documented subset such as player identifier, player name, power, level, game role, and roster state. The exact CSV contract becomes part of the living Kingdoms domain guide when implemented.

Import must never merge two players merely because their display names match. A stable game identifier may be used when present; otherwise the user must resolve ambiguous matches explicitly.

Provide an authenticated alliance-scoped CSV export of the current roster and latest observations. Exported private notes require the roster-management permission and must not be included in ordinary member-facing exports by default.

### 4.7 Roster intelligence dashboard

Provide an alliance-scoped roster dashboard derived from current roster state and snapshots.

Initial metrics should include:

- active tracked-player count;
- total recorded alliance power;
- average and median recorded power;
- players with stale/missing observations;
- recent joins and departures;
- 7-day and 30-day aggregate power change where sufficient history exists;
- membership-to-game-profile linkage coverage; and
- configurable manager views for notable individual growth/decline where the alliance chooses to use comparative metrics.

Metrics must distinguish missing data from zero values. Historical calculations must state their observation window and should not imply precision that the underlying manual/import data cannot support.

Comparative views must follow the program's existing rule that metrics should not encourage unhealthy play or punitive member management.

## 5. Authorization model

The initial implementation should reuse `alliance.view` for ordinary authenticated roster visibility and introduce one explicit mutation permission:

- `kingdoms.manage` — manage the alliance's game roster, membership links, snapshots, imports, exports containing management-only fields, and kingdom association where allowed by policy.

Recommended built-in defaults:

| Role | Roster view | `kingdoms.manage` |
| --- | --- | --- |
| Owner | Yes | Yes |
| Leader | Yes | Yes |
| Officer | Yes | Yes |
| Recruiter | Yes | No |
| Event Coordinator | Yes | No |
| Content Manager | Yes | No |
| Member | Yes | No |

The normal custom-role/permission model remains authoritative; these are built-in defaults, not hard-coded controller role checks.

Roster-management mutations require recent password confirmation, follow the existing active-alliance context, and emit attributable audit records.

Platform administrators do not implicitly act as alliance roster managers through alliance RBAC. Any cross-tenant support surface requires an explicit Platform-domain workflow rather than bypassing tenant authorization.

## 6. Proposed data ownership

Exact table names may be refined during implementation, but ownership should remain equivalent to:

| Concept | Ownership | Tenant scope |
| --- | --- | --- |
| Kingdom | Kingdoms | Global reference |
| Kingdom player identity | Kingdoms | Global neutral identity/reference |
| Alliance roster entry | Kingdoms | Alliance-scoped |
| Player snapshot | Kingdoms | Alliance-scoped |
| Application user | Identity | Global |
| Alliance membership | Memberships | Alliance-scoped |
| Audit event | Audit | Correlated to actor/alliance as applicable |
| Durable integration event | Platform outbox | Alliance-scoped where tenant data is involved |

Global Kingdom/KingdomPlayer records contain only neutral reference identity. Private notes, observations, roster status, and metrics belong to the alliance-scoped records.

## 7. Cross-domain contracts

### Alliances

`Alliances` owns the alliance aggregate and stores/uses the association to a Kingdom reference. `Kingdoms` owns the Kingdom entity and game-player/roster semantics.

### Memberships and Identity

`Kingdoms` may link a roster entry to an existing same-alliance membership but does not own account identity or membership lifecycle. Removing/leaving an application membership must not erase the game-player history.

### Recruitment

No Recruitment workflow changes are required in `KINGDOMS-001`. The roster model should expose an intentional future contract so an accepted/incoming recruit can later be associated with a tracked game player without duplicating identity models.

### Events and Rallies

No event/rally optimization is introduced in this increment. Future features may consume current roster/player data through a supported Kingdoms query rather than reading Kingdoms persistence internals.

### Contributions

Roster power snapshots are game observations, not contribution records. Contributions may consume explicit future metrics, but this increment must not silently convert power growth into contribution scoring.

### Integrations

No new public API or webhook schema is required for initial acceptance. Domain events should still use the transactional outbox where downstream side effects need durable publication. External Kingdoms API/webhook exposure requires an explicit contract update after the internal model is stable.

## 8. Delivery slices

### Slice A — Kingdom foundation

- `Kingdom` model and persistence;
- alliance-to-kingdom relationship;
- migration/backfill from the existing free-form alliance kingdom field;
- kingdom association UI/policy/audit; and
- architecture/domain documentation updates.

### Slice B — Roster foundation

- `KingdomPlayer` identity;
- alliance-scoped roster entries;
- optional membership linking;
- `kingdoms.manage` permission and built-in role defaults;
- manual roster CRUD; and
- tenant-isolation/authorization tests.

### Slice C — Snapshots and intelligence

- append-oriented player snapshots;
- snapshot history UI;
- latest-observation projections;
- stale/missing-data indicators; and
- roster intelligence dashboard/trend calculations.

### Slice D — Spreadsheet migration workflow

- CSV import preview/validation/confirmation;
- deterministic matching and ambiguity handling;
- current-roster CSV export;
- import/export audit evidence; and
- limits/performance validation for realistic alliance roster sizes.

The slices may be implemented through multiple pull requests, but `KINGDOMS-001` is accepted only when the complete increment works end to end.

## 9. Explicitly out of scope

The following are deferred to later approved increments:

- scraping Kingshot screens/sites or automating OCR ingestion;
- use of undocumented/unapproved game APIs;
- Discord/bot-based automatic roster ingestion;
- kingdom-wide cross-alliance roster visibility;
- public competitive kingdom/alliance rankings;
- transfer marketplace or player advertising;
- transfer-window workflow and incoming/outgoing transfer coordination;
- diplomacy/NAP/ally/rival relationship management;
- other-alliance intelligence and leadership-contact tracking;
- battle outcome prediction;
- hero/gear/troop optimization engines;
- AI-generated roster decisions or punitive recommendations; and
- automatic contribution scoring based on power growth.

These deferrals are intentional. They must not be partially introduced as hidden schema/UI placeholders unless required by the accepted `KINGDOMS-001` design itself.

## 10. Follow-on roadmap

The intended sequencing after `KINGDOMS-001` is:

| Candidate increment | Outcome | Dependency |
| --- | --- | --- |
| `KINGDOMS-002` — Transfer planning | Incoming/outgoing/staying status, destination kingdom, transfer groups, coordinator/readiness workflow | Stable roster/player identity |
| `KINGDOMS-003` — Kingdom alliance intelligence | Track external alliances, tags, power/member observations, diplomacy/NAP state, contacts and history | Stable Kingdom model |
| `KINGDOMS-004` — Approved data ingestion | API/bot/import adapters using an approved reliable interface with provenance, rate/error controls and replay safety | Stable manual/import contract |
| `KINGDOMS-005` — Opt-in kingdom intelligence | Explicitly consented cross-alliance aggregates/rankings and kingdom-level coordination | Privacy/authorization design plus validated adoption |

These are roadmap candidates, not approved implementation scope. Each requires its own product increment approval before runtime code is added.

## 11. Security, privacy, and abuse requirements

Implementation must include a current threat/security review covering at minimum:

- cross-alliance roster/snapshot disclosure;
- roster-note privacy;
- object-ID tampering and scoped binding;
- privilege escalation through roster management;
- CSV formula injection/export safety;
- malicious/oversized import files;
- ambiguous identity matching and accidental player merges;
- auditability of manual corrections/imports;
- stale or misleading game observations;
- abusive/punitive comparative metrics; and
- future ingestion trust boundaries without implementing them prematurely.

Game-facing information is not automatically public merely because it may be observable in-game. The platform must preserve its existing authenticated tenant boundary unless a later feature explicitly approves broader disclosure.

## 12. Operational and observability requirements

The increment must document and expose enough information to diagnose:

- import failures and rejected rows;
- authorization/tenant failures;
- snapshot creation failures;
- dashboard calculation/data-quality issues;
- outbox publication failures for Kingdoms events; and
- migration/backfill failures.

No recurring scheduler is required merely to support manual/import snapshots. If implementation introduces scheduled processing, the same change must update `../operations/background-processing.md`, configuration/observability guidance, and launch-health considerations.

## 13. Testing requirements

At minimum, acceptance requires:

- unit tests for identity/matching and trend calculations;
- feature tests for roster CRUD, linking, snapshots, dashboard and imports/exports;
- authorization tests for `alliance.view` and `kingdoms.manage`;
- tenant-isolation tests proving another alliance cannot read or mutate roster entries, notes, snapshots, links, imports or exports;
- migration tests preserving existing alliance kingdom values;
- duplicate/idempotency tests for imports and snapshots;
- CSV injection/validation tests;
- time-series tests with missing/irregular observations;
- accessibility validation for roster/dashboard/import workflows; and
- performance validation against realistic roster/snapshot volumes.

## 14. Acceptance criteria

`KINGDOMS-001` is complete only when all of the following are true:

1. An alliance references a first-class Kingdom rather than a permanent free-form kingdom string.
2. An authorized roster manager can create/update/leave-track Kingshot players without creating application users.
3. A roster entry can optionally link to a same-alliance application membership without conflating game and application identity.
4. Manual updates and confirmed CSV imports create durable historical observations rather than erasing prior power/history.
5. Members can view the current alliance roster while management-only notes/actions remain permission-protected.
6. Managers can identify stale records, recent roster changes, linkage gaps and 7/30-day aggregate power trends from recorded data.
7. Imports provide validation/preview and never merge players solely by display name.
8. Exports are tenant-scoped, safe against spreadsheet formula injection and auditable where privileged data is included.
9. Privileged mutations are password-confirmed, authorized, audited and tenant-isolated.
10. Domain events that require durable downstream processing use the existing transactional-outbox boundary.
11. Living domain, security, operations (if affected), architecture and capability documentation reflects the implemented contract.
12. Protected CI, tests, migrations, staging validation and relevant security/accessibility checks pass.
13. No unofficial automated game-data ingestion, transfer workflow, diplomacy system or cross-alliance ranking is represented as implemented.

## 15. Exit record

When implementation is complete, create a dedicated `KINGDOMS-001` acceptance/exit record under `docs/product/` with the validated head SHA, protected-check evidence, security/accessibility evidence, migration result, deferred work, and acceptance decision.

Do not create a Phase 7 exit report. Post-program increments retain their own stable scope IDs and acceptance records so the completed Phase 0–6 historical program remains intact.
