# Capability completeness plan

Status: Current

This plan compares the current product with maintained Kingshot community tools. It treats external projects as discovery evidence, not as authoritative game data. Any copied game rule, coordinate set, footprint, cost table, provider behavior or march constant must have a verifiable source and an `observed_at`/version boundary before it becomes product logic.

## Discovery sources

- [Bleezy-D/Alliance-Layout-Planner](https://github.com/Bleezy-D/Alliance-Layout-Planner) — multi-Alliance Kingdom layout planning, HQs, Banners, Governor cities, Bear Traps, territory coverage, map structures/no-build zones, march times, hive presets, grouping/rotation, saved layouts and image export.
- [Gercekefsane/kingshot-bot](https://github.com/Gercekefsane/kingshot-bot) — alliance member monitoring, transfer planning, Crazy Joe guidance, Bear Hunt timers, calculators, recruitment, and multi-channel notifications.
- [adroiteck/discord-kingshot-bot](https://github.com/adroiteck/discord-kingshot-bot) — event guides, player profiles, rally calls, timers, announcements, and moderation workflows.
- [whiteout-project/Whiteout-Survival-Discord-Bot](https://github.com/whiteout-project/Whiteout-Survival-Discord-Bot) — a related multi-game implementation with player management, scheduled notifications, calculators, queues, and backup operations.
- [justncodes/ks-giftcode](https://github.com/justncodes/ks-giftcode) and the [official Century Games Gift Code Center](https://ks-giftcode.centurygame.com/) — gift-code workflow discovery and the safe official redemption boundary.

## Current coverage and remaining gaps

The application already has substantially deeper governed workflows than the bots in Alliance membership/access, recruitment review, content revisions, Events and participation, rosters/battle plans/rallies, King Perks, results, intelligence provenance, Kingdom transfers, platform administration, webhooks, Gift Codes, and retryable notifications.

The previous conclusion that the remaining work was only workflow consistency and operational depth is no longer complete. The Alliance Territory & Hive Planner is a major missing user capability: the application can coordinate battles and Alliances, but cannot yet model a Kingdom map, build a hive layout, validate planned structures, analyze territory/march outcomes, coordinate multiple Alliances spatially, or publish a durable layout revision.

The implementation queue and per-slice exit conditions are authoritative in the [delivery ledger](capability-delivery-ledger.md). The effort is not complete while any Territory & Hive Planner ledger item remains incomplete.

## Prioritized delivery plan

| Priority | Capability/UX | Outcome | Guardrail |
| --- | --- | --- | --- |
| Active | Alliance Territory & Hive Planner | Complete spatial planning from versioned map truth through editable/published layouts, analysis, comparison, import/export and Operations integration | GameWorld owns map facts/rules; Operations owns planning; community data remains provenance-gated |
| Complete | Pagination and list completeness | Opaque cursor pagination, stable sorting, URL filters and bounded query budgets for every potentially large list | Cursor scope is bound to actor, Alliance, filters and ordering |
| Complete | Shared workflow UX | Common page headers, filters, empty/loading/failure states, result receipts and permission-aware navigation | Server remains the authorization authority |
| Complete | Bulk workflows | Previewed, bounded bulk triage and correction with per-item outcomes, audit and failed-item retry | Each owner context keeps its business semantics |
| Complete | Gift Code trust lifecycle | Explicit review/dispute/expiry states, provenance and selective Governor retry | No undocumented provider automation |
| Complete | Announcements | Recurrence, test delivery, cancellation and truthful queued/sent/failed/read history with selective retry | Content owns intent; Communications owns delivery |
| Complete | Integration platform | Typed public events, secret rotation, broader event catalogue and committed OpenAPI/webhook schemas | Public schemas remain distinct from internal messages |
| Complete | Bot/API write parity | Revocable external identity pairing and idempotent Event response/registration writes | A client never supplies an arbitrary actor identity |
| Complete | Knowledge trust | Stale-content review queue, revisioned corrections and contextual Event links | No unreviewed or invented strategy claims |
| Complete | Operational diagnostics | Safe queue/outbox/delivery inspection, correlation search and allowlisted replay | Sensitive payloads are fingerprinted and replay remains idempotent |
| Evidence-gated | Calculators | Troop, Governor Gear, Charm and Hero Gear planning with saved scenarios | No implementation until the dataset gate in the delivery ledger is satisfied |

## Territory & Hive Planner scope

### Product outcomes

The complete capability must allow an authorized Governor/officer to:

1. create an Alliance or Kingdom-scoped territory plan against a concrete versioned map dataset;
2. place and edit HQs, Banners, Governor cities and Bear Traps using exact KingShot coordinates;
3. distinguish application-linked Players/Alliances from plan-local external references;
4. see territory coverage, gaps, disconnected chains, collisions, structure exclusions and placement-rule failures;
5. generate and customize Bear-hive layouts rather than copy opaque hard-coded coordinate blobs;
6. analyze distance and estimated march time with visible assumptions and no invented authoritative speed claim;
7. coordinate multiple Alliances on one Kingdom plan with independent visibility and counts;
8. compare layout alternatives using deterministic metrics such as banner count, covered Governors, invalid placements and march distances;
9. save draft work, publish immutable revisions, clone/restore versions and safely resolve stale-editor conflicts;
10. import/export schema-versioned JSON and export shareable PNG/SVG images;
11. operate all material functions by keyboard and through synchronized DOM controls, not only the canvas;
12. reference a published layout revision from applicable Operations workflows without turning `BattlePlans` into a spatial persistence owner.

### Data and rule taxonomy

The implementation must preserve three separate concepts:

- **Map fact** — what exists in the represented KingShot world: coordinates, zones, fixed structures, terrain/resource references and map bounds. Owned by GameWorld/KingdomMaps.
- **Game placement rule** — a sourced/versioned rule that determines whether a placement is legal. Owned by GameWorld/KingdomMaps and enforced authoritatively on the server.
- **Planning preference** — an Alliance/Kingdom planning choice such as a preferred Bear radius or target march time. Owned by Operations/TerritoryPlanning and reported as warnings/suggestions rather than fake game-rule violations.

Validation returns structured violations, warnings and suggestions. A boolean-only `canPlace` contract is insufficient.

### Geometry parity

Browser geometry is an immediate preview; Laravel remains authoritative. PHP and TypeScript implementations must consume shared golden fixtures for coordinates, footprints, collisions, rotations, coverage and placement outcomes so the browser cannot display green for a placement the server rejects.

### Persistence and history

Interactive movement remains client working state until an explicit save boundary. Server persistence is normalized for current editable state, while published/history revisions retain immutable schema-versioned snapshots with the selected map dataset ID and checksum. Event/Operations references target an immutable published revision, not a mutable plan head.

## Calculator gate

Community calculator pages demonstrate demand, but their visible results do not provide an authoritative, reviewable dataset contract. Calculator implementation starts only after the source, version, reconciliation, checksum, tests, and visible-provenance requirements in the delivery ledger are met.

## Engineering standards for every slice

1. Owner context keeps write semantics; cross-context pages live in `app/ReadModels` where composition is necessary.
2. Public write actions accept scalar IDs/value objects and never return Eloquent models.
3. Every material mutation is authorized against the active Player and concrete plan scope at commit time.
4. Geometry, placement, plan-analysis and import logic live in typed domain/services, never Vue components or controllers.
5. External/community data is immutable, source-labelled, observation/version bounded and checksummed before use as product truth.
6. Every page must be responsive, keyboard usable and localized through an existing/new domain; material journeys must be included in visual regression coverage.
7. `/docs` changes in the same pull request whenever ownership, integration flow, game-data policy or a user journey changes.
8. Full PHP, frontend, architecture, security, visual, container, staging, backup/restore and image-scan checks must pass before merge.
9. No compatibility shims, duplicate schemas, dual reads/writes or temporary legacy names are retained because the application is not deployed.
