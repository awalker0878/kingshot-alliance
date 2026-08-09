# KINGDOMS-001 exit report

[← Kingdoms roster intelligence increment](kingdoms-roster-intelligence-increment.md)

**Scope ID:** `KINGDOMS-001`  
**Stage:** `K1-P6` whole-increment hardening and acceptance  
**Status:** Acceptance candidate pending final protected validation

## Outcome

`KINGDOMS-001` now exists as one integrated Kingdoms capability rather than a set of independent implementation slices:

- first-class global Kingdom reference and Alliance relationship;
- global neutral Kingshot player identity separated from application identity/membership;
- alliance-owned roster with optional same-alliance membership linkage and private manager data;
- append-only player snapshot history and latest/current-stale-missing projection;
- exact roster aggregates, linkage/movement quality indicators and bounded 7/30-day trends;
- manager-only non-ranked individual comparisons;
- strict dry-run/confirm CSV migration with identity ambiguity handling and provenance; and
- formula-safe member/management roster exports.

`K1-P6` adds no follow-on product capability. It validates the combined contract, closes cross-slice gaps and produces acceptance evidence.

## Acceptance-criteria review

| Increment acceptance criterion | `K1-P6` evidence |
| --- | --- |
| First-class Kingdom replaces free-form persistence | Kingdom migration/schema tests; living Kingdoms contract; no runtime `alliances.kingdom` column. |
| Managers can create/update/leave-track game players without Users | Roster feature suite plus combined acceptance workflow. |
| Optional same-alliance membership link without identity conflation | Roster invariant/tenant tests and combined workflow. |
| Manual + CSV observations create durable history | Snapshot/CSV suites and combined workflow preserving manual history after CSV update. |
| Members can see roster while management data/actions remain protected | Authorization/data-minimization/export tests plus combined workflow. |
| Managers can see stale/change/gap/7-day/30-day intelligence | Intelligence deterministic tests plus combined workflow. |
| Import previews changes and never merges by display name | CSV preview/ambiguity tests and stored-resolution contract. |
| Exports are tenant-scoped/formula-safe and management fields privileged | CSV export injection/authorization tests. |
| Privileged operations use confirmation/auth/audit/tenant isolation | Slice tests, same-Kingdom tenant tests and combined workflow. |
| Durable events use transactional outbox | Roster/snapshot/import/Kingdom tests; whole-increment integration boundary review. |
| Living domain/security/operations/architecture/capability documentation | `K1-P6` documentation set and indexes. |
| Protected CI/migrations/staging/security/accessibility pass | Pending exact final protected-head evidence below. |
| Unofficial ingestion/transfer/diplomacy/cross-alliance ranking remain absent | Architecture guard and explicit scope boundaries. |

## Domain-boundary review

The final model preserves the intended ownership split:

- Identity owns application `User` identity.
- Memberships owns user↔Alliance membership.
- Alliances owns the Alliance aggregate and `kingdom_id` relationship.
- Kingdoms owns global neutral Kingdom/game-player references plus alliance-owned roster/snapshot/import/intelligence behavior.
- Authorization owns permission vocabulary/effective role union; `kingdoms.manage` is not a controller role-name check.
- Audit/Platform provide the existing audit/outbox infrastructure.
- Integrations owns public API/webhook contracts and does not implicitly expose internal Kingdoms outbox events.

No new cross-domain persistence reach-through or compatibility layer is required by `K1-P6`.

## Security review

The [whole-increment Kingdoms security review](../security/kingdoms-roster-intelligence-security-review.md) covers cross-alliance disclosure, private notes/actor/import metadata, object-ID tampering, privilege escalation, identity ambiguity, append/replay semantics, CSV abuse/formula injection, metric abuse and external integration boundaries.

A material `K1-P6` finding was fixed: the generic webhook subsystem accepted wildcard subscriptions for tenant outbox events. Without a boundary, new Kingdoms durability events could have become an undocumented external contract. `QueueWebhookDeliveries` now rejects `alliance.kingdom_updated` and every `kingdoms.*` event before subscription fan-out. A regression test proves wildcard subscriptions cannot receive them. The existing `/api/v1` route guard also proves no public roster/snapshot/intelligence endpoint/scope was introduced.

## Accessibility review

The [KINGDOMS-001 accessibility review](kingdoms-roster-intelligence-accessibility.md) covers Kingdom settings, roster, management, history, intelligence and CSV migration.

`K1-P6` adds a source-level accessibility regression guard and closes the remaining CSV ambiguity-control gap with a programmatic per-row label. Kingdom/CSV validation exposes stronger error association/status semantics. Dense data tables retain semantic markup and narrow-viewport horizontal access.

Repository evidence does not claim a manual screen-reader/browser matrix that was not actually executed; that remains release QA guidance.

## Migration and rollback review

The full migration series is dependency-aware and the existing round-trip test now represents the complete increment:

1. import/provenance dependency is removed first;
2. snapshot schema is removed;
3. roster/permission dependencies are removed; and
4. Kingdom foundation restores the pre-increment free-form representation for development/test rollback verification.

Reapply recreates canonical Kingdom references, roster/snapshot/import schemas and snapshot import provenance. Malformed legacy Kingdom values remain fail-closed rather than silently discarded.

This repository rollback test is not a substitute for production backup/change approval after real data exists.

## Query/index and realistic-volume review

Roster and snapshot queries are tenant-first and use existing composite indexes on Alliance + roster/player/capture/state fields. Latest and baseline history projections are batched rather than loaded per player.

`tests/Performance/KingdomRosterPerformanceTest.php` seeds **150 tracked players / 450 snapshots**, calculates current + 7-day + 30-day intelligence and asserts the SELECT count remains within a fixed bounded budget. This is an N+1/query-shape regression gate, not a production load/capacity benchmark.

## Operations and observability review

[Kingdoms roster intelligence operations](../operations/kingdoms-roster-intelligence.md) documents persisted-state ownership, CSV failure diagnosis, snapshot/history interpretation, intelligence windows/query behavior, migration order and privacy rules.

The increment adds no dedicated scheduler, worker, crawler or external ingestion service. It uses normal request/trace correlation, audit and the existing transactional-outbox publisher. Import previews/results and snapshot provenance supply feature-specific diagnostic evidence without adding private-note logging.

## API/webhook compatibility review

- `/api/v1` remains the documented read-only alliance/events/contributions API.
- Existing `/api/v1/alliance.data.kingdom` remains derived representation compatibility.
- No Kingdoms roster/snapshot/intelligence scope or route exists.
- `alliance.kingdom_updated` and `kingdoms.*` outbox events remain internal and cannot fan out to webhooks, even under wildcard subscriptions.
- Any future external Kingdoms API/webhook requires an explicit product/integration contract and tests.

## Repository hygiene

The temporary `kingdoms-roster-intelligence-c2-validation.md` diagnostic marker is removed by `K1-P6`. The final PR diff must contain no temporary workflow, formatter or validation artifact.

## Protected validation

Pending final exact-head evidence:

- Dependency Review;
- CodeQL;
- frontend lint/format/Vue-TypeScript/production build;
- PostgreSQL migrations;
- Pint and PHPStan;
- complete PHPUnit/ParaTest suite including combined acceptance, performance, tenant, migration and accessibility guards;
- immutable production-image build;
- ephemeral staging deployment;
- backup/restore demonstration; and
- image vulnerability scan.

The exact validated implementation SHA, workflow run IDs and measured test/static-analysis counts will be recorded here only after the gate succeeds.

## Deferred work

The following remain unapproved/unimplemented follow-on candidates and are not required for `KINGDOMS-001` acceptance:

- automated Kingshot ingestion, scraping, OCR, bots or undocumented APIs;
- transfer planning;
- diplomacy/NAP management;
- cross-alliance roster intelligence/rankings;
- automated player scoring/recommendations; and
- public Kingdoms API/webhook contracts.

## Acceptance decision

**Pending.** Promote this record and the current capability/status documents to Accepted only after the exact final `K1-P6` implementation head passes every protected gate above.

Even after repository/product acceptance, a real production cutover remains governed separately by [production launch approval](production-launch-approval.md) and must not be inferred from this increment record.
