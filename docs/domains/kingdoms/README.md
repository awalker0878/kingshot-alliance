# Kingdoms domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current runtime — `KINGDOMS-001`–`KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P2 current-fact sharing validated  
**Code owner:** `app/Domain/Kingdoms`  
**Primary authorization boundary:** `alliance.view` for member-safe reads; `kingdoms.manage` for management/private workflows; `alliance.manage` for Alliance→Kingdom setting

## 1. Purpose and ownership

Kingdoms owns neutral Kingdom/player/game-Alliance identity; Alliance-owned roster/snapshots/intelligence; CSV migration/export; transfer planning; game-Alliance tracking/observations/diplomacy/contacts; K4 automated ingestion; and K5 directional consent, explicit target grants and safe recipient current-fact sharing.

Accepted increment evidence: [K1](product/kingdoms-roster-intelligence-increment.md), [K2](product/kingdoms-transfer-planning-increment.md), [K3](product/kingdoms-alliance-intelligence-increment.md), and [K4](product/kingdoms-automated-ingestion-increment.md). K5 is [in progress](product/kingdoms-shared-intelligence-increment.md).

Current K5 behavior is [Opt-in shared Kingdom intelligence](shared-intelligence.md). It includes current facts only; bounded shared history remains unimplemented P3 work.

## 2. Scope

### In scope

- neutral Kingdom/player/game-Alliance reference identity;
- Alliance-owned roster/history/intelligence/import/export;
- transfer planning/readiness/completion;
- tracked game Alliances, factual observation/correction history, explicit diplomacy, private contacts and descriptive intelligence;
- K4 allowlisted ingestion, delegated factual promotion, scheduler/cursor/retry/replay, source revocation, retention and health;
- K5 directional invitation/agreement consent with hash-only one-time token and same-Kingdom activation;
- K5 explicit source-owned target grant/removal; and
- bounded recipient-safe current-fact projection over source-owned accepted observations.

### Out of scope

- application/membership ownership;
- unapproved ingestion sources/scraping/OCR/browser/game-client automation;
- machine roster/tracking creation/reactivation or observation correction/invalidation;
- K5 bounded shared observation history until P3 is separately accepted;
- complete K5 source/recipient page set until P4;
- roster/player, transfer, diplomacy/contact or cross-Kingdom sharing;
- transitive reshare, public tenant directory, public Kingdoms API/webhook; and
- scoring/ranking/recommendations/automatic decisions.

## 3. Domain model

Identity remains layered: global `User`; Alliance membership; neutral `KingdomPlayer`/`KingdomAlliance` within a `Kingdom`; and Alliance-owned relationships/observations/workflows.

K1–K3 own roster/snapshot/transfer/game-Alliance intelligence. K4 owns `KingdomIngestionSubscription`, `KingdomIngestionBatch`, and `KingdomIngestionCandidate` operational state while promoted K1/K3 history retains independent provenance.

K5 adds `KingdomIntelligenceShare` for directional source/recipient/captured-Kingdom consent and `KingdomIntelligenceShareTarget` for one explicit source-owned tracking grant. Source `KingdomAllianceObservation` history remains canonical; K5 stores no recipient observation copies.

## 4. Core invariants

1. Neutral references never grant tenant access.
2. Stable game identifiers within one Kingdom are the only automatic neutral identity keys.
3. Display names/tags/handles/source labels never auto-merge identity.
4. Tenant-owned reads/mutations begin from explicit Alliance context and submitted IDs are re-resolved beneath it.
5. Player/game-Alliance history is append-oriented; human corrections preserve original evidence.
6. Machine game-Alliance promotion is append-only and cannot correct/invalidate existing history.
7. Missing data remains distinct from zero.
8. Diplomacy changes only through explicit human manager action; transfer completion remains explicit/idempotent.
9. K4 operational rows never silently follow Kingdom drift; cursor/retry/source rules remain fail closed.
10. K4 promotion requires existing owning-Alliance roster/tracking relationships and never creates/reactivates them.
11. K5 sharing is directional; reverse sharing requires another agreement.
12. K5 acceptance requires a different Alliance and both current Kingdoms equal the captured invitation Kingdom.
13. Invitation plaintext is never persisted; successful redemption is single-use.
14. Active agreement alone shares no observations; each target requires explicit source grant.
15. Recipient current reads authorize through active recipient → active agreement → active grant → source-owned active tracking/context.
16. Source observations remain canonical; recipient reads create no local tracking/history copy.
17. Received intelligence cannot be used as an upstream K5 grant.
18. Source invalidation immediately changes recipient current projection under accepted K3 semantics.
19. Target removal, agreement revocation or supported Kingdom drift immediately removes access.
20. Supported Kingdom drift terminalizes affected agreements so returning cannot silently resume consent.
21. Internal `kingdoms.*` events never automatically become public webhooks.
22. Bounded shared history is not a current runtime capability.

## 5. Lifecycles and workflows

Existing roster/snapshot/intelligence/CSV, transfer, game-Alliance and K4 workflows remain documented in their capability contracts.

K5 consent: source creates invitation; recipient accepts/declines; source revokes; active recipient leaves. K5 P2: source explicitly grants/removes one active source tracking target. Consent/target mutations require `kingdoms.manage` plus recent password confirmation.

The internal recipient current query validates active share/grant/context and loads only the latest accepted source observation. Supported Alliance→Kingdom changes terminalize affected K5 agreements and source pending invitations inside the Kingdom-change transaction.

## 6. Authorization and tenancy

Member-safe existing reads use `alliance.view`; Kingdoms management uses `kingdoms.manage`; privileged human mutations require recent password confirmation; Alliance→Kingdom setting uses `alliance.manage`.

K5 source mutations resolve shares/tracking beneath the active source Alliance. Recipient current facts resolve from active recipient Alliance through an active directional agreement and explicit active grant. Neutral game-Alliance identity never bridges tenant authorization.

P2 safe current facts are member-safe data intended for P4 first-party presentation; no public recipient API exists.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — tenant/current Kingdom plus K5 source/recipient identity and supported Kingdom-change lifecycle.
- **Memberships** — optional existing roster/coordinator references only.
- **Authorization / Identity** — permissions, actor identity and password assurance.
- **Audit / Platform** — Audit/outbox plus shared runtime infrastructure.
- **Integrations** — external-exposure boundary; `kingdoms.*` remains excluded.

### Exposes

Existing member/manager Kingdoms contracts, K4 internal ingestion services, K5 first-party consent/target mutations, and the internal bounded recipient-safe current-fact query. K5 exposes no public sharing API/webhook and no bounded history query yet.

## 8. Persistence and data ownership

Neutral Kingdom/player/game-Alliance references are global reference data. Roster/history/import/transfer/tracking/observations/diplomacy/contacts/K4 state remain Alliance-owned.

K5 `kingdom_intelligence_shares` stores consent metadata and `kingdom_intelligence_share_targets` stores explicit grant history. Source observations stay source-owned; no recipient observation/history rows are materialized.

The target migration depends on both the K5 share and K3 tracking tables and participates in full and focused migration rollback/reapply evidence.

## 9. Events, outbox and integrations

Material Kingdoms mutations create Audit/internal outbox evidence. `alliance.kingdom_updated` and every `kingdoms.*` event remain excluded from generic external webhook fan-out.

K5 consent events remain internal. P2 adds `kingdoms.shared_intelligence_target_shared`, `kingdoms.shared_intelligence_target_removed`, and `kingdoms.shared_intelligence_context_invalidated`, all using safe metadata only.

## 10. HTTP, UI and API surfaces

K1–K4 first-party surfaces remain unchanged. K5 consent POST routes create/accept/decline/revoke/leave agreements; P2 adds password-confirmed POST routes to add/remove explicit targets.

P2 current facts are an internal query and there is still no K5 current/history GET route, public API/webhook, tenant directory or complete K5 Vue/page surface. P4 owns full first-party source/recipient UX.

## 11. Background processing

K4 retains its accepted scheduler/queue/maintenance behavior. K5-P2 adds no job, queue, scheduler or operator command.

Invitation retention/cleanup and broader capacity/diagnostics remain P5 work.

## 12. Failure, idempotency and concurrency

K4 idempotency/concurrency semantics remain unchanged.

K5 invalid/expired/used token, self-share, different-Kingdom activation, duplicate active agreement, stale share context and unrelated-tenant share/tracking/target IDs fail closed. Re-adding an active target is idempotent; removed grants require deliberate re-grant.

Relevant consent/grant lock ordering is Alliance(s) → share → target, with source/recipient Alliances locked in deterministic ID order where both participate. Supported Kingdom drift terminalizes affected agreements, preventing later implicit access resume.

## 13. Security and privacy

K5 P2 is the first cross-tenant data path and uses a fixed safe-field projection rather than serializing source models wholesale.

The recipient receives source Alliance ID/name, neutral/current game-Alliance name/tag, latest accepted observed name/tag, optional power/member count, capture time and freshness only. Tracking IDs, stable game IDs, manager notes, diplomacy/contact data, roster/transfer data, actors/reasons, K4 provenance and private source data remain excluded.

See [Kingdoms security](security/README.md), [K5 Slice A security review](security/kingdoms-shared-intelligence-foundation-security-review.md), and [K5 Slice B security review](security/kingdoms-shared-intelligence-current-facts-security-review.md).

## 14. Observability and operations

K4 operational health remains unchanged. K5 P2 adds no health command; safe diagnostics remain grant/consent identifiers, state and timestamps.

The current projection is bounded to 250 rows and no more than two SELECTs in the focused 12-target fixture. Do not log invitation plaintext or shared payload bodies and do not repair/reactivate sharing via database edits.

## 15. Testing and architecture enforcement

Suites protect identity/tenancy, privacy, history, K4 source/idempotency and K5 consent/grant/current-fact boundaries.

K5-P2 runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed Dependency Review `31562753429`, CodeQL `31562753422`, and CI `31562753430`: Pint 550 files, PHPStan/Larastan 390/390 zero errors, 440 tests / 10,025 assertions, frontend/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

## 16. Explicit non-capabilities

Current runtime does not provide K5 bounded shared observation history, complete sharing pages, roster/player sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, transitive reshare, tenant directory, public Kingdoms API/webhook, scoring/ranking/recommendations or automatic decisions.

K4 production source enablement also remains separately unapproved.

## 17. Capability documents

- [Roster](roster.md)
- [Player snapshots](snapshots.md)
- [Roster intelligence](intelligence.md)
- [Controlled CSV migration](csv-migration.md)
- [Transfer planning](transfer-planning.md)
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Automated game-data ingestion](automated-ingestion.md)
- [Opt-in shared Kingdom intelligence](shared-intelligence.md)

Domain-owned evidence: [Product](product/README.md), [Security](security/README.md), [Operations](operations/README.md), [Interfaces](interfaces/README.md), [Testing](testing/README.md).

## 18. Related documentation

- [KINGDOMS-001 exit](product/kingdoms-roster-intelligence-exit-report.md)
- [KINGDOMS-002 exit](product/kingdoms-transfer-planning-exit-report.md)
- [KINGDOMS-003 exit](product/kingdoms-alliance-intelligence-exit-report.md)
- [KINGDOMS-004 exit](product/kingdoms-automated-ingestion-exit-report.md)
- [KINGDOMS-005 scope](product/kingdoms-shared-intelligence-increment.md)
- [KINGDOMS-005 implementation plan](product/kingdoms-shared-intelligence-implementation-plan.md)
- [K5-P0 exit](product/kingdoms-shared-intelligence-p0-exit-report.md)
- [K5 Slice A validation](product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5 Slice A security review](security/kingdoms-shared-intelligence-foundation-security-review.md)
- [K5 Slice B validation](product/kingdoms-shared-intelligence-slice-b-validation.md)
- [K5 Slice B security review](security/kingdoms-shared-intelligence-current-facts-security-review.md)
- [Alliances](../alliances/README.md)
- [Authorization](../authorization/README.md)
- [Integrations](../integrations/README.md)
- [Program product documentation](../../product/README.md)
- [`app/Domain/Kingdoms/README.md`](../../../app/Domain/Kingdoms/README.md)
