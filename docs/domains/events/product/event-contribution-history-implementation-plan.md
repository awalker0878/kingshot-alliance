# EVENT-CONTRIB-001 — Event contribution history implementation plan

[← Events domain](../README.md)

**Document type:** Implementation plan  
**Status:** Complete  
**Owning domains:** Events and Contributions  
**Architecture authority:** [ADR 0011](../../../adr/0011-event-history-and-contribution-ownership.md)

## Objective

Deliver one durable historical model across Player-, Alliance-, and Kingdom-scoped KingShot Events so:

- a Player can view that Player's lifetime contribution/Event history across Alliance and Kingdom changes;
- current Alliance leadership can view the Alliance's complete historical Event record, including former members;
- current Kingdom leadership can view the Kingdom's complete historical Event record, including Players who later transfer; and
- Event metrics remain explainable and comparable only where their definitions are compatible.

The database is greenfield for this program. Canonical migrations/schema are changed directly to the final model; no compatibility columns, dual-write shims, backfills, or legacy User/membership authority paths are introduced.

## Canonical identity hierarchy

```text
Kingdom
└── id

Alliance
├── id            canonical Alliance identity
├── kingdom_id    → Kingdom.id
├── name
└── lifecycle/settings

Player
├── id            durable Player identity
└── current_kingdom_id → Kingdom.id
```

Every Event, result, membership, roster and historical context row uses `alliance_id` to reference `alliances.id`. `kingdom_id` belongs on Alliance and identifies the Kingdom that Alliance belongs to. Event history does not introduce a second GameAlliance/KingdomAlliance identity.

Alliance names are presentation data and are not identity. The same name may exist in different Kingdoms.

## Phase status

| Phase | Scope | Status |
| --- | --- | --- |
| EC-P0 | Final ownership, authorization, history and metric semantics | **Complete** |
| EC-P1 | Greenfield schema redesign and immutable historical targets | **Complete** |
| EC-P2 | KingShot Event metric catalogue | **Complete** |
| EC-P3 | Result/metric capture and derived participation facts | **Complete** |
| EC-P4 | Player cross-scope history query | **Complete** |
| EC-P5 | My Contributions / History UX | **Complete** |
| EC-P6 | Alliance historical Event intelligence | **Complete** |
| EC-P7 | Kingdom historical Event intelligence | **Complete** |
| EC-P8 | Contribution report/export integration | **Complete** |
| EC-P9 | Trend and comparative intelligence | **Complete** |
| EC-P10 | Security, isolation and concurrency coverage | **Complete** |
| EC-P11 | Query/index/performance review | **Complete** |
| EC-P12 | Documentation, cleanup and final verification | **Complete** |

## EC-P0 — Ownership and authorization contract — COMPLETE

The accepted contract is:

1. Player history follows durable `player_id`.
2. Alliance Event history follows immutable Event `alliance_id`.
3. Kingdom Event history follows immutable Event `kingdom_id`.
4. Current authority controls organization-wide read access; current membership/current Kingdom placement never determines historical ownership.
5. Events owns Event facts and metric persistence.
6. Contributions composes unified history/reporting with its own non-Event ledger; it does not duplicate Event facts.
7. Historical Player/Alliance/Kingdom snapshots are evidence only and never authorization state.
8. Platform Administrator status grants no game-domain Event-history bypass.
9. Incompatible Event metrics are never combined into an unexplained universal score.

ADR 0011, Events/Contributions living contracts and architecture tests protect this model.

## EC-P1 — Greenfield schema — COMPLETE

Canonical migrations now provide:

- immutable `events.scope` + exact Player/Alliance/Kingdom target identity;
- restrictive historical retention semantics for Event targets and occurrences;
- creation-time Event target display snapshots;
- normalized `event_metric_definitions`;
- normalized Event-, Alliance- and Player-result metric tables;
- `event_alliance_results` using canonical `alliance_id`;
- `event_player_contexts` with occurrence-time Player, Kingdom and represented-Alliance evidence;
- database guards that reject Alliance/Kingdom mismatches;
- natural uniqueness for result/context/metric identities; and
- history-oriented indexes.

Legacy JSON metric persistence was removed rather than retained as a compatibility path.

## EC-P2 — KingShot metric catalogue — COMPLETE

The system Event catalogue now defines Event-Type/scope measurement semantics. `score`, `rank` and `outcome` remain first-class result fields; normalized metrics represent component contribution facts only.

Metric definitions carry subject, value type, aggregation, dimension semantics, unit, contribution/reporting eligibility and comparison direction. Phase/objective dimensions are validated against exact occurrence state. There is no universal cross-Event contribution score.

## EC-P3 — Capture, provenance and historical context — COMPLETE

Events-owned result workflows now:

- write Event-, Alliance- and Player-subject normalized metrics;
- validate metric subject, type scope, value type and phase/objective dimensions;
- preserve `manual | derived | imported` provenance;
- use idempotent logical metric identity;
- freeze `event_player_contexts` once at the first evidence-bearing participation/result boundary; and
- preserve that frozen context when the Player later changes Alliance or Kingdom.

Evidence-bearing boundaries include confirmed registration/attendance/roster/Rally participation and result capture. Later historical corrections use the frozen occurrence context rather than current affiliation.

## EC-P4 — Exact-Player cross-scope history — COMPLETE

`EventPlayerHistoryQuery` returns one chronological Player history across Player-, Alliance- and Kingdom-scoped Events. Filters support date, Event scope/type, metric, historical represented Alliance/Kingdom and participation outcome.

The query starts from durable `event_player_contexts.player_id`. It never filters historical facts through the Player's current Alliance or current Kingdom.

`PlayerContributionHistoryQuery` composes those Event facts with genuine Contributions-owned records without copying Event facts into `contribution_records`.

## EC-P5 — My Contributions / History UX — COMPLETE

The active Player has a unified lifetime history surface under Contributions. The UI exposes chronological Event and non-Event contribution records, historical target/representation context, participation outcome, score/rank and normalized metrics with date/scope/type/metric/Alliance filters.

The page is exact-active-Player scoped; sibling Players owned by the same User are not aggregated.

## EC-P6 — Alliance historical Event intelligence — COMPLETE

Current Alliance-authorized viewers can browse all historical Events whose immutable target is that Alliance. Historical participants/results remain visible even when those Players have left or changed Alliances.

New leaders inherit access to the Alliance's historical record through current authority. Former leaders lose organization-wide access when current authority is lost.

## EC-P7 — Kingdom historical Event intelligence — COMPLETE

Current exact-Kingdom-authorized viewers can browse all historical Events targeted at that Kingdom, including Player and represented-Alliance contribution breakdowns.

Transferred Players remain in the old Kingdom's historical Event record. Alliance grouping uses occurrence-time canonical `alliance_id`; current membership never rewrites historical representation.

## EC-P8 — Contributions reports and exports — COMPLETE

Contribution reporting/export composition now includes Event facts alongside Contributions-owned records. Event rows expose source/kind, scope, Event Type, occurrence, historical Alliance/Kingdom, Player, score/rank/outcome, metric definition/dimension/value/unit and provenance.

Historical Alliance report rows are selected from Event target/result/context identity rather than current membership.

## EC-P9 — Trends and comparative intelligence — COMPLETE

`EventTrendQuery` provides:

- universal participation/reliability facts that are safe across Event Types;
- Player score series scoped to one exact Event Type + scope;
- Player metric series scoped to one exact Event Type + scope + metric key;
- Alliance/Kingdom Event score series; and
- Alliance/Kingdom metric series with exact compatible metric identity.

No incompatible Event metrics are arithmetically combined.

## EC-P10 — Security, isolation and concurrency — COMPLETE

Coverage protects the critical model, including:

- Player history surviving Alliance/Kingdom movement;
- Alliance history retaining former-member contributions;
- Kingdom history retaining transferred-Player contributions;
- new leaders seeing pre-tenure organization history;
- former/unrelated actors being denied organization-wide history;
- sibling Player isolation;
- Platform Administrator game-domain bypass rejection;
- historical snapshots never granting authority;
- wrong-Kingdom Alliance result rejection;
- immutable Event targets; and
- idempotent context/result/metric identities and Player-attributed audit.

## EC-P11 — Query/index/performance contract — COMPLETE

The history read paths are bounded and indexed for Player timeline, Event result lookup, metric definition/result lookup, Alliance result history and Player/Alliance/Kingdom Event target history.

History queries avoid current-affiliation N+1 reconstruction by reading frozen occurrence-time context directly. Trend queries use bounded windows/row limits.

PostgreSQL feature coverage asserts the required history indexes exist.

## EC-P12 — Final cleanup and verification — COMPLETE

Final cleanup leaves one consolidated Event Contribution History verification workflow and no temporary phase-specific formatter/static-analysis workflow. Stale Event-to-Contribution materialization, current-membership historical filtering, duplicate Alliance identity, and User/membership historical actor assumptions have been removed from the living contract.

The final feature verifier passed on implementation head `8702b892c63ca40e2f09b124c903d66e90a5f456` (Event Contribution History Verification run #273 / run `31866568608`):

- fresh PostgreSQL 18 migration from zero;
- Pint across all changed PHP;
- Larastan across all changed application PHP;
- Event/Contribution history architecture, schema, catalogue, capture, history, intelligence, security and performance contract suites;
- history-page ESLint and Prettier checks;
- TypeScript checking of the two history surfaces and their dependency graph; and
- a targeted Vite production build of the two history surfaces and their actual import graph.

The targeted frontend gates are intentional: repository `main` already contains unrelated pre-existing Event/Platform frontend debt that prevents a full repository TypeScript/build gate. EVENT-CONTRIB-001 verifies its own new frontend surfaces and dependency graph without weakening or absorbing that unrelated baseline debt.

## Definition of done

EVENT-CONTRIB-001 is complete when the application can independently answer:

```text
What has this Player contributed over their lifetime?
```

and:

```text
What happened historically for this Alliance or Kingdom?
```

without either answer depending on today's membership/Kingdom placement, while current Player-derived authority remains the only basis for organization-wide access.
