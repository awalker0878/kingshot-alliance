# Kingdoms domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current — `KINGDOMS-001`, `KINGDOMS-002`, `KINGDOMS-003` Accepted; `KINGDOMS-004` through `K4-P5` runtime validated with P6 acceptance next after the containing transition head is protected-green  
**Code owner:** `app/Domain/Kingdoms`  
**Primary authorization boundary:** `alliance.view` for member-safe reads; `kingdoms.manage` for management/private workflows; `alliance.manage` for Alliance→Kingdom setting

## 1. Purpose and ownership

Kingdoms owns approved Kingshot game-world reference identity and Alliance-owned workflows: neutral Kingdom/player/game-Alliance identity; roster/snapshots/intelligence; controlled CSV migration/export; transfer planning/handoff; game-Alliance tracking/observations/diplomacy/contacts/intelligence; and governed K4 automated-ingestion control, factual promotion, generic scheduling, source-revocation, retention and operational health.

Accepted increment evidence: [K1](product/kingdoms-roster-intelligence-increment.md), [K2](product/kingdoms-transfer-planning-increment.md), [K3](product/kingdoms-alliance-intelligence-increment.md). K4 is [in progress](product/kingdoms-automated-ingestion-increment.md); current behavior is [Automated game-data ingestion](automated-ingestion.md).

## 2. Scope

### In scope

- neutral Kingdom/player/game-Alliance reference identity;
- Alliance→Kingdom context consumption;
- Alliance-owned roster/snapshots/intelligence/import/export;
- transfer planning/readiness/completion;
- tracked game Alliances, factual observation/correction history, explicit diplomacy, private contacts, descriptive intelligence;
- K4 code/config adapter allowlist, subscriptions, batches, bounded candidates, quarantine/rejection, deterministic identity, manager status/control;
- delegated factual player-snapshot and game-Alliance-observation promotion to existing owning-Alliance relationships;
- generic K4 scheduled acquisition, opaque cursor, bounded retry/circuit, concurrency guards and password-confirmed replay; and
- source-revocation reconciliation, bounded operational retention/pruning and aggregate health/capacity monitoring.

### Out of scope

- application/membership ownership;
- unapproved concrete sources, manager-configured endpoints/credentials, scraping/OCR/browser/game-client automation;
- machine roster/tracking creation or reactivation;
- machine game-Alliance correction/invalidation;
- cross-Alliance/shared intelligence without separately approved opt-in scope;
- automatic transfer/diplomacy/contact actions or scoring/ranking/recommendations; and
- public Kingdoms API/webhook contracts.

## 3. Domain model

Identity remains layered: global `User`; Alliance membership; neutral `KingdomPlayer`/`KingdomAlliance` within a `Kingdom`; and Alliance-owned relationships/observations/workflows.

`AllianceRosterEntry` + `PlayerSnapshot` own player roster/history. Transfer entities own planning/readiness/completion. `TrackedKingdomAlliance`, `KingdomAllianceObservation`, diplomacy/transition/contact entities own tenant game-Alliance intelligence.

K4 adds Alliance-owned `KingdomIngestionSubscription`, `KingdomIngestionBatch`, and `KingdomIngestionCandidate`. Subscriptions carry bounded scheduler/cursor/failure state; batches carry source-window/next-cursor state. Promoted K1/K3 history copies bounded machine provenance without operational-row FKs.

## 4. Core invariants

1. Neutral references never grant tenant access.
2. Stable game identifiers within one Kingdom are the only automatic neutral identity keys.
3. Display names/tags/handles/source labels never auto-merge identity.
4. Tenant-owned reads/mutations begin from explicit Alliance context and submitted IDs are re-resolved beneath it.
5. Player/game-Alliance history is append-oriented; human corrections preserve original evidence.
6. Machine game-Alliance promotion is append-only and cannot correct/invalidate existing history.
7. Missing data remains distinct from zero.
8. Diplomacy changes only through explicit human manager action; transfer completion remains explicit/idempotent.
9. K4 operational rows capture Alliance/Kingdom context and never silently follow Kingdom drift.
10. K4 promotion requires an existing owning-Alliance roster or active tracking relationship; it never creates/reactivates one.
11. K4 scheduled workers re-resolve tenant/current-Kingdom/source version; queue identity is never authority.
12. K4 cursor advances only after Completed/Partial batch state; exact source-window/candidate/promoted retry remains idempotent.
13. Adapter removal/version drift disables acquisition rather than substituting another source/version.
14. K4 operational retention cannot delete promoted K1/K3 canonical history or rewrite copied provenance.
15. Production adapters remain repository/config allowlisted; current production list is empty.
16. Internal `kingdoms.*` events never automatically become public webhooks.

## 5. Lifecycles and workflows

Alliance Kingdom setting remains Alliances-owned. Historical Kingdoms state fails closed after drift.

Roster/snapshot/intelligence/CSV flows are documented in [Roster](roster.md), [Snapshots](snapshots.md), [Roster intelligence](intelligence.md), and [CSV migration](csv-migration.md). Transfer planning is [Transfer planning](transfer-planning.md). Game-Alliance business workflows are [Alliance intelligence and diplomacy](alliance-intelligence.md).

K4 managers control approved subscriptions/rejection/replay. The scheduler claims due approved subscriptions, dispatches isolated per-subscription jobs, acquires a bounded page through an acquisition-capable adapter, delegates staging and P2/P3 promotion, completes the batch, then advances its opaque cursor.

P5 maintenance periodically reconciles current source approval and disables revoked adapters; daily retention redacts/prunes age-qualified operational scaffolding; on-demand health produces payload-free aggregate attention signals. See [Automated game-data ingestion](automated-ingestion.md).

## 6. Authorization and tenancy

- member-safe reads use `alliance.view`;
- roster/snapshot/import/transfer/game-Alliance/diplomacy/contact/K4 management uses `kingdoms.manage`;
- privileged human mutations/replay require recent password confirmation; and
- Alliance→Kingdom setting uses `alliance.manage`.

Machine acquisition/promotion/maintenance derives authority only from already-owned K4 state after tenant/source re-resolution. Neutral identity, adapter/source/cursor/candidate state never grants application authorization.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — active tenant/current Kingdom.
- **Memberships** — optional roster/coordinator references only.
- **Authorization / Identity** — permission, actor identity and assurance.
- **Audit / Platform** — audit/outbox plus shared scheduler/queue runtime.
- **Integrations** — explicit external-exposure boundary; Kingdoms API/webhook remains excluded.

### Exposes

Member-safe presentation, manager-only accepted mutation/query contracts, K4 manager control/status, internal staging/promotion/scheduler/maintenance services, and internal durable `kingdoms.*` events. K4 adapter/acquisition registration is repository/operator configuration, not a public integration contract.

## 8. Persistence and data ownership

Neutral Kingdom/player/game-Alliance references are global reference data. Roster/history/import/transfer/tracking/observations/diplomacy/contacts/derived intelligence and K4 operational state are Alliance-owned.

K4 normalized candidates are bounded operational data, not raw-source archives. Default operational retention is 30-day terminal payload redaction, 90-day terminal candidate/batch retention, 180-day quarantined-candidate retention and 30-day disabled-subscription scheduling/failure compaction. Promoted K1/K3 history stores bounded source provenance independently.

## 9. Events, outbox and integrations

Material Kingdoms mutations create audit/internal outbox evidence. `alliance.kingdom_updated` and every `kingdoms.*` event remain excluded from generic external webhook fan-out.

K4 uses internal ingestion lifecycle/promotion/replay events plus accepted K1/K3 observation events. P5 maintenance adds no public integration surface. No inbound public API/webhook or arbitrary endpoint/secret configuration exists.

## 10. HTTP, UI and operator surfaces

K1–K3 first-party surfaces cover settings, roster/history/intelligence/import/export, transfers, and tracked game-Alliance observation/diplomacy/contact/intelligence.

K4 adds manager-only ingestion status/control and password-confirmed quarantined-candidate replay. There is no HTTP route for arbitrary source payload staging, scheduler invocation, direct promotion, retention/reconciliation/health or public source callbacks.

Operator commands include queueing due ingestion, source reconciliation, operational retention and aggregate ingestion health. These commands are internal runtime interfaces, not public APIs.

## 11. Background processing

K4 uses `kingdoms:queue-ingestion --limit=100` every minute, transactional due claims, dedicated `kingdoms-ingestion` Horizon queue, unique/overlap-protected per-subscription jobs, bounded timeout/retries/backoff/circuit state and opaque cursor advancement.

P5 adds source reconciliation every five minutes and operational retention daily at 04:15 with single-server/overlap protection. Production has zero configured ingestion adapters, so no real external source is polled in default production state.

## 12. Failure, idempotency and concurrency

Existing snapshot/observation/CSV/transfer idempotency remains enforced. K4 source windows/candidate identities are deterministic; exact promoted-candidate retry returns existing canonical history. Cursor advancement uses locks and requires completed/partial batch state.

Adapter removal/version drift, Kingdom drift, circuit-open state, source-window/cursor conflicts, unknown/ambiguous/inactive targets, relationship absence and invalid bounded facts fail closed. Failure state uses bounded codes, not raw exception/source text. Source reconciliation is idempotent after disablement.

## 13. Security and privacy

Kingdoms holds high-value tenant operational intelligence. Manager-private notes/reasons/contacts/provenance and K4 scheduler/operational state must not cross tenant/public boundaries.

K4 excludes arbitrary endpoint/credential storage, raw-response archives, normalized-payload UI/health disclosure, stable-ID guessing, cross-tenant mutation, auto roster/tracking creation, machine observation correction and diplomacy/contact automation. See [Kingdoms security](security/README.md).

## 14. Observability, capacity and operations

Use safe tenant/reference/state/count/timing/hash/cursor/reason/promoted-record identifiers with request/trace/audit/outbox correlation. Do not log source secrets/raw responses/private text.

P5 operational health is bounded aggregate evidence. A representative fixture of 250 subscriptions, 40 failed batches and 110 candidates is required to remain at no more than eight SELECT queries. This is not a real-source throughput SLO.

Domain guides: [Roster intelligence operations](operations/kingdoms-roster-intelligence.md), [Transfer planning operations](operations/kingdoms-transfer-planning.md), [Alliance intelligence operations](operations/kingdoms-alliance-intelligence.md), [Automated ingestion operations](operations/kingdoms-automated-ingestion.md).

## 15. Testing and architecture enforcement

Suites protect identity/tenancy, authorization/privacy, append history, human-only correction/diplomacy, retry/idempotency, migration/accessibility, public integration exclusion and K4 allowlist/no-secret/stable-ID/quarantine/no-auto-relationship/scheduler/replay/retention/revocation boundaries.

K4-P5 runtime candidate `eb706a96c9c875dd41e932e0691e4258f33e01f1` passed DR `31552113152`, CodeQL `31552113044`, CI `31552113042`: Pint 528, PHPStan 374/374 zero errors, 428 tests / 9,736 assertions, image/staging/backup/scan success.

## 16. Explicit non-capabilities

The current runtime does not implement an approved real Kingshot source, arbitrary manager network fetches, source credential storage, scraping/OCR/browser/game-client automation, auto roster/tracking/membership/transfer/diplomacy/contact behavior, machine K3 correction/invalidation, cross-Alliance shared intelligence, scoring/ranking/recommendations, or public Kingdoms API/webhook.

Generic scheduler/acquisition/maintenance mechanics exist, but the production adapter allowlist is empty. Production source enablement remains separately approved.

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
- [K4 Slice D validation](product/kingdoms-automated-ingestion-slice-d-validation.md)
- [K4 Slice E validation](product/kingdoms-automated-ingestion-slice-e-validation.md)
- [K4 Slice E security review](security/kingdoms-automated-ingestion-operations-security-review.md)
- [Alliances](../alliances/README.md)
- [Authorization](../authorization/README.md)
- [Integrations](../integrations/README.md)
- [Program product documentation](../../product/README.md)
- [`app/Domain/Kingdoms/README.md`](../../../app/Domain/Kingdoms/README.md)
