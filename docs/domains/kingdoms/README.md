# Kingdoms domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current — `KINGDOMS-001`, `KINGDOMS-002`, `KINGDOMS-003` Accepted; `KINGDOMS-004` `K4-P1` ingestion foundation runtime validated  
**Code owner:** `app/Domain/Kingdoms`  
**Primary authorization boundary:** `alliance.view` for member-safe reads; `kingdoms.manage` for management/private workflows; `alliance.manage` for Alliance→Kingdom setting

## 1. Purpose and ownership

Kingdoms owns approved Kingshot game-world reference identity and Alliance-owned workflows: neutral Kingdom/player/game-Alliance identity; roster/snapshots/intelligence; controlled CSV migration/export; transfer planning/handoff; game-Alliance tracking/observations/diplomacy/contacts/intelligence; and the K4 generic automated-ingestion control plane.

Accepted increment evidence: [K1](product/kingdoms-roster-intelligence-increment.md), [K2](product/kingdoms-transfer-planning-increment.md), [K3](product/kingdoms-alliance-intelligence-increment.md). K4 is [in progress](product/kingdoms-automated-ingestion-increment.md); its current living capability is [Automated game-data ingestion](automated-ingestion.md).

## 2. Scope

### In scope

- neutral Kingdom/player/game-Alliance reference identity;
- Alliance→Kingdom context consumption;
- Alliance-owned roster/snapshots/intelligence/import/export;
- transfer cycles/participants/groups/readiness/blockers/completion;
- tracked game Alliances, append factual observations/corrections, explicit diplomacy/NAP history, private contacts, descriptive intelligence;
- K4 code/config adapter allowlist, subscriptions, batches, bounded normalized candidates, quarantine/rejection, deterministic candidate identity, and manager status/control.

### Out of scope

- application/membership ownership;
- any unapproved source, scraping/OCR/bots/browser/game-client automation;
- K4 external acquisition/scheduler/worker or automatic observation promotion until later slices;
- cross-Alliance/shared Kingdom intelligence without separately approved opt-in scope;
- automatic transfer/diplomacy/negotiation or scoring/ranking/recommendations;
- public Kingdoms API/webhook contracts.

## 3. Domain model

Identity remains layered: global `User`; Alliance membership; neutral `KingdomPlayer`/`KingdomAlliance` within a `Kingdom`; and Alliance-owned observations/workflows.

`AllianceRosterEntry` + `PlayerSnapshot` own player roster/history. Transfer entities own planning/readiness/completion. `TrackedKingdomAlliance`, `KingdomAllianceObservation`, diplomacy/transition/contact entities own tenant game-Alliance intelligence.

K4 adds Alliance-owned `KingdomIngestionSubscription`, `KingdomIngestionBatch`, and `KingdomIngestionCandidate`. These capture adapter/version and Kingdom context plus safe operational/provenance state; they do not grant neutral identity or application authorization.

## 4. Core invariants

1. Neutral references never grant tenant access.
2. Stable game identifiers within one Kingdom are the only automatic neutral identity keys.
3. Display names/tags/handles/source labels never auto-merge identity.
4. Tenant-owned reads/mutations begin from explicit active Alliance context and submitted IDs are re-resolved beneath it.
5. Player/game-Alliance history is append-oriented; corrections preserve original evidence.
6. Missing data remains distinct from zero.
7. Diplomacy changes only through explicit human manager action; transfer completion is explicit/idempotent.
8. K4 subscription/batch/candidate rows capture Alliance/Kingdom context and never silently follow later Kingdom drift.
9. K4 production adapters are allowlisted in code/config; current production list is empty.
10. K4 missing stable identity quarantines and Slice A never creates K1/K3 business observations.
11. Internal `kingdoms.*` events never automatically become public webhooks.

## 5. Lifecycles and workflows

Alliance Kingdom setting remains Alliances-owned under `alliance.manage`; historical Kingdoms state fails closed after drift.

Roster/snapshot/intelligence and controlled CSV flows are documented in [Roster](roster.md), [Snapshots](snapshots.md), [Roster intelligence](intelligence.md), and [CSV migration](csv-migration.md).

Transfer planning/handoff is documented in [Transfer planning](transfer-planning.md). Game-Alliance tracking/observations/diplomacy/contacts/intelligence are documented in [Alliance intelligence and diplomacy](alliance-intelligence.md).

K4 managers may create/transition a subscription for an already registered adapter and reject their own quarantined candidates. Internal services can start/complete batches and stage bounded normalized candidates. Exact retries are deterministic. Current production does not acquire external data or promote candidates; see [Automated game-data ingestion](automated-ingestion.md).

## 6. Authorization and tenancy

- member-safe reads use `alliance.view`;
- roster/snapshot/import/transfer/game-Alliance/diplomacy/contact/K4 ingestion management uses `kingdoms.manage`;
- privileged mutations require recent password confirmation; and
- Alliance→Kingdom setting uses `alliance.manage`.

Global game identity, adapter identity, source cursor/window identity, coordinator/contact responsibility, or candidate state never grants application authorization. K4 submitted subscription/candidate IDs are tenant-scoped; manager presentation omits normalized payload bodies/source secrets.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — active tenant/current Kingdom.
- **Memberships** — optional roster/coordinator references only.
- **Authorization / Identity** — permission and assurance.
- **Audit / Platform** — audit/outbox evidence.
- **Integrations** — explicit external exposure boundary; current Kingdoms API/webhook remains excluded.

### Exposes

Member-safe K1–K3 presentation, manager-only accepted mutation/query contracts, K4 manager control/status and internal ingestion actions, and internal durable `kingdoms.*` events. K4 adapter registration is repository/operator configuration, not a public integration contract.

## 8. Persistence and data ownership

Neutral Kingdom/player/game-Alliance references are global reference data. Roster/history/import/transfer/tracking/observations/diplomacy/contacts/derived intelligence and K4 subscription/batch/candidate state are Alliance-owned.

K4 candidate normalized payload is bounded operational data. It is not a raw-source archive or canonical promoted history; K1/K3 business tables remain unchanged until later delegated promotion slices.

## 9. Events, outbox and integrations

Material privileged Kingdoms mutations create audit/internal outbox evidence. `alliance.kingdom_updated` and every `kingdoms.*` event remain excluded from generic external webhook fan-out, including wildcard subscriptions.

K4 uses internal `kingdoms.ingestion_*` event families with bounded identifiers/state/count/hash metadata. It creates no inbound API/webhook and stores no arbitrary endpoint/secret configuration.

## 10. HTTP, UI and API surfaces

First-party K1–K3 surfaces cover settings, roster/history/intelligence/import/export, transfers, tracked game-Alliance observation/diplomacy/contact/intelligence.

K4-P1 adds manager-only `GET /alliance/kingdom-ingestion/manage` plus password-confirmed subscription create/state-transition and candidate-rejection routes. There is no public/API ingestion route and no first-party route that stages/promotes arbitrary candidate payloads.

## 11. Background processing

K1–K3 behavior remains primarily synchronous with shared outbox publishing. K4-P1 adds internal batch/candidate domain actions but **no Kingdoms ingestion background processing**: no scheduler, queue job/partition, crawler, scraper, OCR worker, bot, external poller, cursor loop, or replay worker.

Those concerns remain gated to `K4-P4` after both promotion paths are validated.

## 12. Failure, idempotency and concurrency

Existing snapshot/observation retries, CSV committed-import identity and transfer-completion idempotency remain enforced.

K4 source windows are unique per subscription; candidate identity is deterministic SHA-256 over tenant/subscription/adapter/target/source/capture/stable-ID/payload identity; exact retries reuse durable state. Subscription/batch actions lock rows where lifecycle integrity requires it. Drift, unapproved adapters, unsupported/bounded values and missing stable identity fail closed/quarantine before business mutation.

## 13. Security and privacy

Kingdoms holds high-value tenant operational intelligence. Manager-private notes/reasons/contacts/provenance and K4 candidate/batch/subscription operational data must not cross tenant/public boundaries.

K4-P1 specifically excludes arbitrary endpoint and credential storage, raw external response archives, normalized-payload UI disclosure, stable-ID guessing, and cross-tenant object-ID mutation. See [Kingdoms security](security/README.md) and [K4 Slice A security review](security/kingdoms-automated-ingestion-foundation-security-review.md).

## 14. Observability and operations

Use safe tenant/reference/state/count/timing/hash/reason identifiers with shared request/trace/audit/outbox correlation. Do not log source secrets/raw responses/private text.

Domain guides: [Roster intelligence operations](operations/kingdoms-roster-intelligence.md), [Transfer planning operations](operations/kingdoms-transfer-planning.md), [Alliance intelligence operations](operations/kingdoms-alliance-intelligence.md), [Automated ingestion operations](operations/kingdoms-automated-ingestion.md).

## 15. Testing and architecture enforcement

Suites protect identity/tenancy, authorization/privacy, append history, retry/idempotency, CSV/transfer/diplomacy invariants, performance/query bounds, accessibility, public integration exclusion, and K4 allowlist/no-secret/stable-ID/quarantine/no-promotion boundaries.

K4-P1 exact runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` passed Dependency Review `31533284318`, CodeQL `31533284195`, CI `31533284398`: Pint 509, PHPStan 363/363 zero errors, 407 tests / 9,466 assertions, image/staging/backup/scan success.

## 16. Explicit non-capabilities

The current runtime does not implement a real Kingshot source, arbitrary manager network fetches, scraping/OCR/bots/browser/game-client automation, K4 scheduler/worker, automated player/game-Alliance observation promotion, auto roster/tracking/membership/transfer/diplomacy behavior, cross-Alliance shared intelligence, scoring/ranking/recommendations, or public Kingdoms API/webhook.

Production adapter allowlist is empty. Later K4 slices may implement only the governed behavior in the K4 plan; production source enablement remains separately approved.

## 17. Capability documents

- [Roster](roster.md)
- [Player snapshots](snapshots.md)
- [Roster intelligence](intelligence.md)
- [Controlled CSV migration](csv-migration.md)
- [Transfer planning](transfer-planning.md)
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Automated game-data ingestion](automated-ingestion.md)

Domain-owned evidence: [Product](product/README.md), [Security](security/README.md), [Operations](operations/README.md), [Interfaces](interfaces/README.md), [Testing](testing/README.md).

## 18. Related documentation

- [KINGDOMS-001 exit](product/kingdoms-roster-intelligence-exit-report.md)
- [KINGDOMS-002 exit](product/kingdoms-transfer-planning-exit-report.md)
- [KINGDOMS-003 exit](product/kingdoms-alliance-intelligence-exit-report.md)
- [KINGDOMS-004 scope](product/kingdoms-automated-ingestion-increment.md)
- [K4 Slice A validation](product/kingdoms-automated-ingestion-slice-a-validation.md)
- [Alliances](../alliances/README.md)
- [Authorization](../authorization/README.md)
- [Integrations](../integrations/README.md)
- [Program product documentation](../../product/README.md)
- [`app/Domain/Kingdoms/README.md`](../../../app/Domain/Kingdoms/README.md)
