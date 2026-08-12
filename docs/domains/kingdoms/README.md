# Kingdoms domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current runtime — `KINGDOMS-001`–`KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P1 consent foundation validated  
**Code owner:** `app/Domain/Kingdoms`  
**Primary authorization boundary:** `alliance.view` for member-safe reads; `kingdoms.manage` for management/private workflows; `alliance.manage` for Alliance→Kingdom setting

## 1. Purpose and ownership

Kingdoms owns neutral Kingdom/player/game-Alliance identity; Alliance-owned roster/snapshots/intelligence; CSV migration/export; transfer planning; game-Alliance tracking/observations/diplomacy/contacts; K4 automated ingestion; and K5 directional sharing-consent state.

Accepted increment evidence: [K1](product/kingdoms-roster-intelligence-increment.md), [K2](product/kingdoms-transfer-planning-increment.md), [K3](product/kingdoms-alliance-intelligence-increment.md), and [K4](product/kingdoms-automated-ingestion-increment.md). K5 is [in progress](product/kingdoms-shared-intelligence-increment.md).

Current K5 runtime is the [shared-intelligence consent foundation](shared-intelligence.md) only. No cross-Alliance observation/current/history read exists yet.

## 2. Scope

### In scope

- neutral Kingdom/player/game-Alliance reference identity;
- Alliance-owned roster/history/intelligence/import/export;
- transfer planning/readiness/completion;
- tracked game Alliances, factual observation/correction history, explicit diplomacy, private contacts and descriptive intelligence;
- K4 allowlisted ingestion, delegated factual promotion, scheduler/cursor/retry/replay, source revocation, retention and health; and
- K5-P1 directional invitation/agreement consent with hash-only one-time token, same-Kingdom acceptance and tenant-scoped terminal actions.

### Out of scope

- application/membership ownership;
- unapproved ingestion sources/scraping/OCR/browser/game-client automation;
- machine roster/tracking creation/reactivation or observation correction/invalidation;
- K5 shared target/current/history observation reads until later protected slices;
- roster/player, transfer, diplomacy/contact or cross-Kingdom sharing;
- transitive reshare, public tenant directory, public Kingdoms API/webhook; and
- scoring/ranking/recommendations/automatic decisions.

## 3. Domain model

Identity remains layered: global `User`; Alliance membership; neutral `KingdomPlayer`/`KingdomAlliance` within a `Kingdom`; and Alliance-owned relationships/observations/workflows.

K1–K3 own roster/snapshot/transfer/game-Alliance intelligence. K4 owns `KingdomIngestionSubscription`, `KingdomIngestionBatch`, and `KingdomIngestionCandidate` operational state while promoted K1/K3 history retains independent provenance.

K5-P1 adds `KingdomIntelligenceShare`, a directional source/recipient/captured-Kingdom consent record with hash-only invitation token and pending/active/declined/revoked state. It contains no shared target or observation payload/history.

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
13. K5 invitation plaintext is never persisted; successful redemption is single-use.
14. K5 terminal decline/revoke state never reactivates through P1 actions.
15. An active K5-P1 agreement exposes no observation data by itself.
16. Internal `kingdoms.*` events never automatically become public webhooks.

## 5. Lifecycles and workflows

Existing roster/snapshot/intelligence/CSV, transfer, game-Alliance and K4 ingestion workflows remain documented in their capability contracts.

K5-P1 source managers create a one-time invitation; recipient managers accept or decline it; source managers can revoke; active recipients can leave. All mutations require `kingdoms.manage` plus recent password confirmation.

Acceptance locks source/recipient tenant rows and validates captured/current Kingdom. Revoke/decline/leave remain available after drift because they reduce access. P1 has no target-selection or shared-data read lifecycle.

## 6. Authorization and tenancy

Member-safe existing reads use `alliance.view`; Kingdoms management uses `kingdoms.manage`; privileged human mutations require recent password confirmation; Alliance→Kingdom setting uses `alliance.manage`.

K5 source actions resolve under source Alliance ownership; recipient leave resolves under recipient ownership; acceptance binds the active recipient Alliance after same-Kingdom validation. Submitted share IDs from unrelated tenants fail closed.

Global neutral `KingdomAlliance` identity is never the cross-tenant authorization bridge.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — tenant/current Kingdom and K5 source/recipient identities.
- **Memberships** — optional existing roster/coordinator references only.
- **Authorization / Identity** — permissions, actor identity and password assurance.
- **Audit / Platform** — Audit/outbox plus shared runtime infrastructure.
- **Integrations** — external-exposure boundary; `kingdoms.*` remains excluded.

### Exposes

Existing member/manager Kingdoms contracts, K4 internal ingestion services, and K5-P1 first-party consent mutations. K5 exposes no shared observation query or public integration contract yet.

## 8. Persistence and data ownership

Neutral Kingdom/player/game-Alliance references are global reference data. Roster/history/import/transfer/tracking/observations/diplomacy/contacts/K4 state remain Alliance-owned.

K5 `kingdom_intelligence_shares` stores only consent metadata. Source observations remain source-owned. P1 creates no recipient copy, target table or observation payload/history.

The P1 migration is the newest Kingdoms dependency and passes full rollback/reapply evidence.

## 9. Events, outbox and integrations

Material Kingdoms mutations create Audit/internal outbox evidence. `alliance.kingdom_updated` and every `kingdoms.*` event remain excluded from generic external webhook fan-out.

K5-P1 adds internal invitation-created/accepted/declined/revoked/left events using safe metadata only. Invitation plaintext and private source data remain excluded.

## 10. HTTP, UI and API surfaces

K1–K4 first-party surfaces remain unchanged. K5-P1 adds password-confirmed POST routes for invitation create/accept/decline and share revoke/leave.

P1 has no K5 GET/list/current/history route, no target-selection endpoint, no tenant directory/search, no public API/webhook and no new Vue/page surface.

## 11. Background processing

K4 retains its accepted scheduler/queue/maintenance behavior. K5-P1 adds no job, queue, scheduler or operator command.

Invitation retention/cleanup and cross-tenant read capacity remain later K5 concerns.

## 12. Failure, idempotency and concurrency

K4 idempotency/concurrency semantics remain unchanged.

K5-P1 invalid/expired/used token, self-share, different-Kingdom acceptance, duplicate active directional agreement and unrelated-tenant share IDs fail closed. Acceptance is transactional with deterministic source/recipient row locking; terminal revoke/leave are tenant-scoped row-locked actions.

Failed acceptance does not consume a token. Successful acceptance and decline consume it once.

## 13. Security and privacy

K5-P1 deliberately minimizes the new cross-tenant boundary: only consent metadata crosses, not observation data. Invitation secrets are hash-only at rest and excluded from Audit/outbox.

Source-side acceptance Audit uses null actor to avoid recipient manager User-ID leakage. Roster/player, transfer, diplomacy/contact, tracking notes, correction rationale and K4 provenance remain source-private.

See [Kingdoms security](security/README.md) and [K5 Slice A security review](security/kingdoms-shared-intelligence-foundation-security-review.md).

## 14. Observability and operations

K4 operational health remains unchanged. K5-P1 has no health command; safe diagnostics are consent IDs/state/timestamps only.

Do not log invitation plaintext or repair K5 consent state by database edits. See [Kingdoms operations](operations/README.md).

## 15. Testing and architecture enforcement

Suites protect identity/tenancy, privacy, history, K4 source/idempotency and K5 invitation/consent boundaries.

K5-P1 runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and CI `31559012861`: Pint 541 files, PHPStan/Larastan 384/384 zero errors, 434 tests / 9,911 assertions, frontend/build, clean migrations, immutable image, staging, backup/restore and scan.

## 16. Explicit non-capabilities

Current runtime still does not provide K5 shared target selection or recipient observation/current/history reads. It does not provide roster/player sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, transitive reshare, tenant directory, public Kingdoms API/webhook, scoring/ranking/recommendations or automatic decisions.

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
- [Alliances](../alliances/README.md)
- [Authorization](../authorization/README.md)
- [Integrations](../integrations/README.md)
- [Program product documentation](../../product/README.md)
- [`app/Domain/Kingdoms/README.md`](../../../app/Domain/Kingdoms/README.md)