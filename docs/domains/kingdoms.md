# Kingdoms

[← Domain documentation](README.md)

**Accepted increments:** [`KINGDOMS-001` — Kingdoms roster intelligence](../product/kingdoms-roster-intelligence-increment.md) and [`KINGDOMS-002` — Transfer planning](../product/kingdoms-transfer-planning-increment.md)  
**Current state:** **Accepted**  
**Acceptance evidence:** [KINGDOMS-001 exit report](../product/kingdoms-roster-intelligence-exit-report.md), [KINGDOMS-002 exit report](../product/kingdoms-transfer-planning-exit-report.md)

`Kingdoms` owns Kingshot game-world reference identity plus alliance-owned roster observations/history/intelligence, controlled spreadsheet migration, and accepted transfer planning. Detailed workflow contracts remain in [Kingdoms roster](kingdoms-roster.md), [player snapshots](kingdoms-snapshots.md), [roster intelligence](kingdoms-intelligence.md), [controlled CSV migration](kingdoms-csv-migration.md), and [transfer planning](kingdoms-transfer-planning.md).

## Ownership model

The domain deliberately separates:

1. **Application identity** — global `User`, owned by Identity.
2. **Alliance membership** — user↔Alliance relationship, owned by Memberships.
3. **Kingshot identity** — neutral `KingdomPlayer` reference within a `Kingdom`, owned by Kingdoms.
4. **Alliance-owned game observations/workflows** — roster entries, snapshots, imports/intelligence and transfer planning, owned by Kingdoms under explicit Alliance tenancy.

A roster entry may optionally link a Kingshot player to an active membership in the same Alliance. That link does not merge the records or make the application account the source of truth for game identity.

## Global reference data

### Kingdom

A `Kingdom` is global reference data with a ULID primary key, unique positive canonical Kingdom number, lifecycle state and timestamps. An Alliance stores `kingdom_id`; the legacy free-form `alliances.kingdom` persistence column is removed.

### KingdomPlayer

A `KingdomPlayer` is global neutral reference data scoped to one Kingdom. It may carry a stable game-player ID and neutral current display name.

Stable game-player ID inside the Kingdom is the only automatic identity-match key. Display names are not unique and never auto-merge identity.

Multiple alliances may reference the same Kingdom/player. That never grants access to another alliance's roster, notes, snapshots, imports, metrics or transfer planning.

## Alliance-owned roster and snapshots

`AllianceRosterEntry` belongs to one Alliance and one `KingdomPlayer` and owns tenant-specific observed name, optional same-alliance membership link, game role/rank, lifecycle state, joined/left dates, private manager notes, observation time and source/provenance.

`PlayerSnapshot` is append-only historical observed game state. Normal roster edits and transfer completion do not rewrite history. A snapshot exists only when an actual observation is supplied through the accepted snapshot contract.

Ordinary roster/history/intelligence reads use `alliance.view`; management uses `kingdoms.manage`. Privileged mutations require recent password confirmation under accepted Kingdoms controls.

## Roster intelligence and controlled CSV

Roster intelligence derives tenant-scoped tracked-player counts, recorded-power aggregates, current/stale/missing quality, recent movement, linkage coverage and bounded 7/30-day trends. Manager detail is diagnostic/alphabetical, not ranking or punitive scoring.

The accepted `kingdoms-roster.v1` CSV path performs bounded full-file preview/validation, stable-ID-only automatic matching, explicit resolution of name ambiguity, password-confirmed transactional commit, drift detection, idempotent checksum confirmation, provenance-aware snapshots and formula-neutralized private exports.

## Alliance Kingdom settings

Changing an Alliance's Kingdom remains an Alliance-setting mutation under `alliance.manage`, not `kingdoms.manage`. Archived Kingdoms cannot be newly selected. The workflow uses active-Alliance context, recent password confirmation, transaction/locking, audit and durable internal outbox evidence.

Active transfer plans capture their own home-Kingdom context. If the Alliance Kingdom later differs, transfer mutations fail closed rather than silently retargeting the plan.

## Accepted transfer planning

`KINGDOMS-002` adds alliance-owned:

- transfer plans (`draft`, `open`, `locked`, `closed`, `cancelled`);
- incoming/outgoing/staying participant intent;
- source/destination planning under direction rules;
- transfer groups and same-alliance coordinators;
- manual readiness transitions and blocker history; and
- explicit idempotent completion/roster handoff.

Coordinator assignment is workflow responsibility only and never grants `kingdoms.manage`.

`confirmed` readiness is planning-only. Actual completion is a separate privileged action permitted only in the locked-plan phase. Incoming/outgoing completion reuses accepted roster actions; staying is a roster no-op. Completion never fabricates snapshots and does not move neutral player identity merely because a destination was planned.

See [Kingdoms transfer planning](kingdoms-transfer-planning.md) for the complete lifecycle/privacy/handoff contract.

## Tenant and privacy boundary

The active Alliance is authoritative for all alliance-owned Kingdoms behavior. Reads/mutations re-resolve submitted roster, membership, snapshot/import, plan, participant, group, blocker and handoff identifiers beneath the tenant/plan boundary.

Ordinary member payloads exclude private manager notes, restricted blocker detail, management membership identifiers, import-management metadata, snapshot actor identity and richer completion provenance. Managers receive only the additional information required by approved workflows.

See the [KINGDOMS-001 security review](../security/kingdoms-roster-intelligence-security-review.md) and [KINGDOMS-002 security review](../security/kingdoms-transfer-planning-security-review.md).

## Audit, outbox and external integrations

Material privileged Kingdoms mutations record audit evidence and transactional-outbox messages.

Those event families remain internal. The public API remains limited to documented alliance/events/contributions read scopes. `alliance.kingdom_updated` and `kingdoms.*`, including `kingdoms.transfer_*`, are excluded from generic webhook fan-out even for wildcard subscriptions.

A public Kingdoms/transfer API or webhook schema requires a separate explicitly approved integration increment.

## Migration and rollback

The accepted migration chain retains dependency-safe rollback/reapply evidence for both increments. `KINGDOMS-002` adds transfer plans → participants → groups → readiness/blockers → completions above the accepted `KINGDOMS-001` baseline and rolls them back in reverse order.

No compatibility shim or dormant future-schema field is retained solely to simplify later work.

## Operations

Accepted Kingdoms behavior uses synchronous request workflows, PostgreSQL, audit and the existing outbox publisher; it adds no Kingdoms-specific scheduler, crawler, game-data ingestion worker or transfer executor.

Operational guidance:

- [Kingdoms roster intelligence operations](../operations/kingdoms-roster-intelligence.md)
- [Kingdoms transfer planning operations](../operations/kingdoms-transfer-planning.md)

## Explicit deferrals

The accepted Kingdoms runtime does **not** implement:

- scraping, OCR, bots or undocumented/unapproved Kingshot APIs;
- automated game-data ingestion;
- transfer marketplace/public advertising;
- inferred transfer eligibility/readiness;
- transfer pass/ticket/resource optimization;
- player/destination rankings or automated stay/leave recommendations;
- bulk or automatic in-game transfer execution;
- diplomacy/NAP/ally/rival intelligence;
- cross-alliance Kingdoms rankings/intelligence;
- automated/AI punitive player scoring/recommendations; or
- public Kingdoms API/webhook contracts.

Those remain follow-on candidates requiring separate scope approval. `KINGDOMS-001` and `KINGDOMS-002` are Accepted repository/product capabilities; real production cutover remains separately not yet approved.
