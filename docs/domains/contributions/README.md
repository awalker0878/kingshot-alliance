# Contributions domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Contributions`  
**Primary authorization boundary:** active Player for personal history; scope-specific current authority for organization reporting/mutations

## 1. Purpose and ownership

Contributions owns explainable non-Event contribution records, calculation provenance, corrections/reversals, reporting, exports, data-quality state, report schedules/runs, and the unified contribution/history read experience across Contributions-owned records and Events-owned historical facts.

Events remains authoritative for Event schedules, participation, attendance, rosters/Rally facts, results, metrics, and Event historical context. Contributions consumes those facts for reporting; it does not create a second canonical Event ledger.

The domain separates recorded facts, calculated metrics, and subjective assessments so results remain explainable rather than opaque scoring.

## 2. Scope

In scope:

- non-Event contribution categories/calculation metadata;
- non-Event contribution records/history and correction/reversal;
- Player cross-scope contribution/Event history composition;
- Alliance historical contribution/Event reporting;
- Kingdom historical contribution/Event reporting;
- data quality, reports/leaderboards, exports, and scheduled reports; and
- supported derivation from other domains where Contributions truly owns the derived record.

Out of scope:

- duplicating Events-owned results/metrics/attendance into a second canonical ledger;
- editing Events facts;
- generic notification transport;
- API credential/webhook ownership;
- unexplained punitive or universal scoring; and
- cross-target aggregation without current authorization.

## 3. Domain model

Non-Event Contribution categories define unit/period/goals/evidence/self-report/leaderboard/data class and, for calculated metrics, calculation key/version/explanation.

Contribution records are Player-historical and preserve category/source/data class/value/effective period/status/evidence/actor and correction/calculation provenance. They do not use Alliance membership as historical Player identity.

Unified history is a composed read model:

```text
active Player
  ├── Contributions-owned records for that player_id
  └── Events-owned Player/Alliance/Kingdom Event facts for that player_id
```

Organization history is based on immutable Event targets, not current membership:

```text
Alliance history → Events where scope=alliance and alliance_id=target Alliance
Kingdom history  → Events where scope=kingdom and kingdom_id=target Kingdom
```

See [Event contribution and historical intelligence](../events/event-contribution-history.md).

## 4. Core invariants

1. Player history is keyed by durable `player_id` and survives Alliance/Kingdom movement.
2. Corrections reverse/link Contributions-owned history rather than overwrite it destructively.
3. Approved non-reversed non-Event contribution records drive applicable Contributions reporting.
4. Events remains authoritative for Event participation/results/metrics; Contributions does not duplicate those facts as canonical contribution rows.
5. Alliance historical Event reporting follows immutable Event `alliance_id`, never the current roster.
6. Kingdom historical Event reporting follows immutable Event `kingdom_id`, never Players' current Kingdom placement.
7. Current authority controls organization-wide visibility; historical membership/context never grants authorization.
8. Missing evidence/data-quality state never silently changes recorded values.
9. Leaderboard participation is explicit per compatible category/metric and may be disabled.
10. Incompatible Event metrics are never added into an unexplained universal contribution score.

## 5. Lifecycles and workflows

Non-Event contribution records may be manual, self-reported, or calculated. Pending records follow supported review/approval. Corrections create linked replacement history; reversals preserve source evidence.

Data-quality refresh identifies missing evidence/current-period records without changing totals. Reporting provides Player history/progress, Alliance historical reporting, Kingdom historical reporting, manager reporting, exports, and scheduled reports.

Event history is queried from Events through supported read contracts; no reconciliation/materialization job is required merely to make Event facts visible in Contributions.

## 6. Authorization and tenancy

### Personal history

The currently active Player may view that exact Player's own contribution/Event history across historical Player, Alliance, and Kingdom Events. This view does not require the Player to remain in the Alliance or Kingdom represented historically.

A User's sibling Players do not share history merely because they share `user_id`.

### Alliance reporting and mutations

Alliance-wide reporting and Contributions-owned Alliance mutations require current active-Player authority for the exact Alliance, including `contributions.manage` where management is required. A current authorized leader may view historical Alliance Events from before their tenure and results for Players who have since left.

### Kingdom reporting

Kingdom-wide historical Event reporting requires current exact-Kingdom Player authority. A current authorized Kingdom leader may view historical Kingdom Event results for Players who later transferred Kingdoms.

Platform Administrator status does not bypass game-domain history authorization.

## 7. Cross-domain contracts

Consumes:

- **Events** — authoritative historical Event/occurrence/participation/result/metric facts;
- **Memberships/Authorization** — current Alliance authority and current eligibility where relevant;
- **Kingdoms/Authorization** — durable Player identity and current exact-Kingdom authority;
- **Notifications** — scheduled-request coordination; and
- **Audit/Platform** — evidence/shared infrastructure.

Exposes unified Player contribution/history reporting, Alliance/Kingdom historical reporting, and approved Contributions-owned records to first-party surfaces and bounded read-only Integrations contracts.

## 8. Persistence and data ownership

Contributions owns non-Event categories, non-Event records/history, correction/reversal links, calculation metadata, quality flags, report schedules/versions/runs, export evidence, and reporting/query composition.

Events owns all Event source facts and normalized Event metrics. Contributions does not own or mutate Events persistence.

## 9. Events, outbox and integrations

Events mutations emit Events-owned audit/outbox evidence. Contributions mutations emit Contributions-owned evidence. Unified reporting composes persisted facts without creating duplicate Event writes.

Scheduled report requests use Notifications plus the shared Platform outbox. Integrations may expose approved read models; it does not gain write/semantic ownership.

## 10. HTTP, UI and API surfaces

First-party surfaces include:

- **My Contributions / History** — active Player lifetime history across Player, Alliance, and Kingdom Events plus non-Event contribution records;
- **Alliance Contributions / Event History** — current-authority organization history including former members' historical results;
- **Kingdom Event History** — current-authority Kingdom history including transferred Players;
- Contributions record management, data quality, reports/leaderboards, exports, and schedules.

Historical organization views may display occurrence-time and current affiliation side by side for context, but current affiliation never filters the historical result set.

## 11. Background processing

Scheduled report coordination runs through Notifications/shared scheduler/outbox. Large report work must remain retry-safe and scope-bound. Event facts are not copied into Contributions by a background reconciliation process solely for reporting.

## 12. Failure, idempotency and concurrency

Cross-target identifiers fail closed; correction/reversal preserves Contributions history; scheduled report requests use deterministic identity; Event history queries authorize current scope before reading immutable historical targets.

Mutations follow ADR 0010 and remain domain-owned. Reporting is read composition and must not acquire broad mutation locks merely to join historical facts.

## 13. Security and privacy

Exports and organization-level reporting are privileged disclosure surfaces. Historical snapshots are evidence only and never grant authority. Subjective assessments must not be presented as unexplained objective scores, and private evidence/Player data remains scope-authorized.

## 14. Observability and operations

Operators should distinguish source domain, source Event/occurrence where applicable, metric/category, evidence gaps, approval state, scheduled report state, and export/run completion. See [Observability](../../operations/observability.md).

## 15. Testing and architecture enforcement

Tests protect:

- durable Player history across Alliance/Kingdom movement;
- current-authority versus historical-ownership separation;
- former-member visibility in authorized Alliance history;
- transferred-Player visibility in authorized Kingdom history;
- sibling Player isolation;
- correction/reversal history;
- compatible metric aggregation only;
- reporting/export authorization;
- schedules and data quality; and
- domain ownership boundaries between Events and Contributions.

## 16. Explicit non-capabilities

Contributions does not edit or duplicate Events facts, use current membership as historical identity, provide generic messaging transport, own API credentials/webhooks, create opaque punitive/universal scores, or grant game-domain access from Platform Administrator status.

## 17. Capability documents

- [Event history composition](event-history-composition.md) — Contributions-side composition of Events-owned history with non-Event contribution records.
- [Event contribution and historical intelligence](../events/event-contribution-history.md) — durable Player/Alliance/Kingdom historical ownership and Event-side reporting contract.

## 18. Related documentation

- [ADR 0011 — Historical Event and contribution ownership](../../adr/0011-event-history-and-contribution-ownership.md)
- [Events](../events/README.md)
- [Notifications](../notifications/README.md)
- [Integrations](../integrations/README.md)
- [Memberships](../memberships/README.md)
- [Kingdoms](../kingdoms/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Contributions/README.md`](../../../app/Domain/Contributions/README.md)
