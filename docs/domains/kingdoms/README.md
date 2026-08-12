# Kingdoms domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current runtime — `KINGDOMS-001`–`KINGDOMS-005` Accepted  
**Code owner:** `app/Domain/Kingdoms`  
**Primary authorization boundary:** `alliance.view` for member-safe reads; `kingdoms.manage` for management/private workflows; `alliance.manage` for Alliance→Kingdom setting

## 1. Purpose and ownership

Kingdoms owns neutral Kingdom/player/game-Alliance identity; Alliance-owned roster/snapshots/intelligence; CSV migration/export; transfer planning; game-Alliance tracking/observations/diplomacy/contacts; K4 automated ingestion; and accepted K5 directional consent, explicit target grants, safe current/history projections, first-party sharing presentation and bounded operational retention.

Accepted increment evidence: [K1](product/kingdoms-roster-intelligence-increment.md), [K2](product/kingdoms-transfer-planning-increment.md), [K3](product/kingdoms-alliance-intelligence-increment.md), [K4](product/kingdoms-automated-ingestion-increment.md), and [K5](product/kingdoms-shared-intelligence-increment.md).

Current K5 behavior is [Opt-in shared Kingdom intelligence](shared-intelligence.md). P0–P6 are Complete; whole-increment acceptance is recorded in the [K5 exit report](product/kingdoms-shared-intelligence-exit-report.md).

## 2. Scope

### In scope

- neutral Kingdom/player/game-Alliance reference identity;
- Alliance-owned roster/history/intelligence/import/export;
- transfer planning/readiness/completion;
- tracked game Alliances, factual observation/correction history, explicit diplomacy, private contacts and descriptive intelligence;
- K4 allowlisted ingestion, delegated factual promotion, scheduler/cursor/retry/replay, source revocation, retention and health;
- K5 directional invitation/agreement consent with hash-only pending token identity and same-Kingdom activation;
- K5 explicit source-owned target grant/removal;
- bounded recipient-safe current-fact projection;
- bounded recipient-safe accepted history for one explicit target using opaque encrypted continuation cursors;
- member-safe first-party current/history page;
- manager-only first-party sharing consent/grant workspace;
- bounded scheduled retention of eligible old K5 operational sharing metadata;
- realistic-volume current/history capacity regression evidence; and
- whole-increment cross-tenant acceptance evidence.

### Out of scope

- application/membership ownership;
- unapproved ingestion sources/scraping/OCR/browser/game-client automation;
- machine roster/tracking creation/reactivation or observation correction/invalidation;
- roster/player, transfer, diplomacy/contact or cross-Kingdom sharing;
- transitive reshare, public tenant directory, public Kingdoms API/webhook;
- recipient canonical shared-intelligence copy/materialization; and
- scoring/ranking/recommendations/automatic decisions.

## 3. Domain model

Identity remains layered: global `User`; Alliance membership; neutral `KingdomPlayer`/`KingdomAlliance` within a `Kingdom`; and Alliance-owned relationships/observations/workflows.

K1–K3 own roster/snapshot/transfer/game-Alliance intelligence. K4 owns `KingdomIngestionSubscription`, `KingdomIngestionBatch`, and `KingdomIngestionCandidate` operational state while promoted K1/K3 history retains independent provenance.

K5 adds `KingdomIntelligenceShare` for directional consent and `KingdomIntelligenceShareTarget` for one explicit source-owned tracking grant. Current/history queries read source `KingdomAllianceObservation` rows directly under live authorization; K5 stores no recipient observation copies.

Pending invitations retain only a one-way token hash. P4 erases that hash on accept, decline and revoke through a forward nullable-column migration; accepted historical P1/P2 migrations remain unchanged.

P5 retention can remove only eligible old pending/terminal/removed K5 operational rows. Active shares/grants, canonical source tracking/observations, Audit events and outbox messages remain outside that destructive boundary.

History continuation state is encrypted/authenticated transient cursor data, not a persisted data-ownership record or reusable authorization credential.

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
13. Invitation plaintext is never persisted; redemption is single-use; accepted/declined/revoked invitation hashes are erased.
14. Active agreement alone shares no observations; each target requires explicit source grant.
15. Recipient current/history reads authorize through active recipient → active agreement → active grant → source-owned active tracking/context.
16. Source observations remain canonical; recipient reads create no local tracking/history copy.
17. Received intelligence cannot be used as an upstream K5 grant.
18. Current/history use accepted K3 semantics; invalidated observations cease participating immediately.
19. Target removal, agreement revocation or supported Kingdom drift immediately removes current/history access.
20. Supported Kingdom drift terminalizes affected agreements so returning cannot silently resume consent.
21. History pages are capped at 50 and one traversal at 250 accepted observations.
22. History continuation cursor is encrypted, target-bound and fixed to one `asOf` snapshot; it never substitutes for live authorization.
23. First-party K5 presentation exposes no arbitrary client-selected historical `asOf` windows.
24. Manager agreement/grant state remains manager-only; safe shared facts/history are member-safe.
25. Invitation plaintext is never persisted into Inertia/session page state.
26. Internal `kingdoms.*` events never automatically become public webhooks.
27. K5 retention owns one total work budget, clamped to 1–2000, and cannot create/reactivate authorization.
28. Active shares/grants and canonical source tracking/observations are never K5 retention-eligible.
29. Retention repeats state/cutoff predicates during deletion so stale candidate IDs cannot delete newly non-eligible rows.
30. Audit/outbox evidence remains outside K5 operational retention.

## 5. Lifecycles and workflows

Existing roster/snapshot/intelligence/CSV, transfer, game-Alliance and K4 workflows remain documented in their capability contracts.

K5 consent: source creates invitation; recipient accepts/declines; source revokes; active recipient leaves. Accept, decline and revoke erase the persisted invitation hash. K5 target lifecycle: source explicitly grants/removes one active source tracking target. Consent/target mutations require `kingdoms.manage` plus recent password confirmation where defined.

Current facts use `SharedKingdomIntelligenceCurrentQuery`. Bounded history uses `SharedKingdomIntelligenceHistoryQuery` with 50-row keyset pages, 250 accepted-observation traversal limit and encrypted target-bound continuation state.

P4 first-party presentation uses `Alliance/KingdomSharing` for member-safe facts/history and `Alliance/KingdomSharingManage` for manager-only consent/grant operations. History navigation uses explicit target + opaque server cursor only.

P5 retention uses `kingdoms:enforce-sharing-retention --limit=500`, scheduled daily at 04:30 on one server without overlap. Cleanup priority is expired pending invitations, old terminal shares, then old removed target grants.

Supported Alliance→Kingdom changes terminalize affected K5 agreements and source pending invitations inside the Kingdom-change transaction.

P6 adds no lifecycle; it re-proves the complete accepted seam end to end.

## 6. Authorization and tenancy

Member-safe existing reads use `alliance.view`; Kingdoms management uses `kingdoms.manage`; privileged human mutations require recent password confirmation; Alliance→Kingdom setting uses `alliance.manage`.

K5 source mutations resolve shares/tracking beneath the active source Alliance. Recipient current/history facts resolve from active recipient Alliance through active directional agreement and explicit active grant. Every history page repeats live authorization; stale cursor state does not bypass removal/revoke/drift.

The management page requires `kingdoms.manage`; safe K5 current/history presentation is first-party member-safe. No public recipient API exists.

The P5 retention command is an internal operator surface and does not grant tenant read/write authority.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — tenant/current Kingdom plus K5 source/recipient identity and supported Kingdom-change lifecycle.
- **Memberships** — optional existing roster/coordinator references only.
- **Authorization / Identity** — permissions, actor identity and password assurance.
- **Audit / Platform** — Audit/outbox plus shared runtime infrastructure.
- **Integrations** — external-exposure boundary; `kingdoms.*` remains excluded.

### Exposes

Existing member/manager Kingdoms contracts, K4 internal ingestion services, K5 first-party consent/target mutations, bounded current/history queries, authenticated first-party K5 sharing pages and one bounded internal retention command/schedule. K5 exposes no public sharing API/webhook.

## 8. Persistence and data ownership

Neutral Kingdom/player/game-Alliance references are global reference data. Roster/history/import/transfer/tracking/observations/diplomacy/contacts/K4 state remain Alliance-owned.

K5 sharing/target tables store only consent/grant metadata. Source observations stay source-owned; no recipient observation/history rows are materialized.

`invitation_token_hash` is nullable in current schema so consumed/terminal secret-derived values can be erased. Rollback compatibility uses deterministic retired placeholders only when reverting to the historical P1 non-null schema; reapply restores terminal null values.

P5 operational retention can delete eligible K5 share/target rows but does not delete canonical source observations or Audit/outbox evidence.

The encrypted history cursor is request continuation state only and is not persisted by the domain.

## 9. Events, outbox and integrations

Material Kingdoms mutations create Audit/internal outbox evidence. `alliance.kingdom_updated` and every `kingdoms.*` event remain excluded from generic external webhook fan-out.

K5 consent/target/context events remain internal and safe-metadata-only. Current/history presentation and P5 retention add no public event contract. History/current payloads, invitation plaintext and cursors are not event payloads.

P5 retention explicitly preserves Audit/outbox rows. P6 adds no event contract.

## 10. HTTP, UI and API surfaces

K1–K4 first-party surfaces remain unchanged. K5 consent POST routes create/accept/decline/revoke/leave agreements; target POST routes add/remove explicit grants.

K5 includes authenticated first-party GET presentation for member-safe current/history facts and a manager-only sharing workspace. History uses opaque continuation cursors and exposes no arbitrary historical-window control.

P5 adds an internal Artisan command/scheduler entry only. There is still no public K5 data API/webhook, anonymous feed, tenant directory/search or external callback/credential.

## 11. Background processing

K4 retains its accepted scheduler/queue/maintenance behavior.

K5 P5 adds no queue job but adds a daily scheduled retention command at 04:30. It runs `onOneServer()` and `withoutOverlapping(60)`, with one total work budget defaulting to 500 and clamped to 1–2000.

Default windows are 30 days after invitation expiry, 180 days for terminal shares and 90 days for removed grants. Immediate invitation-hash erasure remains synchronous P4 behavior.

K5 introduces no read cache/materialized recipient projection. P6 adds no background processing.

## 12. Failure, idempotency and concurrency

K4 idempotency/concurrency semantics remain unchanged.

K5 invalid/expired/used token, self-share, different-Kingdom activation, duplicate active agreement, stale share context, unrelated-tenant IDs and invalid/wrong-target history cursors fail closed.

Active target re-add is idempotent; removed grants require deliberate re-grant. History continuation uses keyset ordering rather than mutable offsets and repeats live recipient/share/grant/context authorization on each page.

Relevant consent/grant lock ordering remains Alliance(s) → share → target. Supported Kingdom drift terminalizes affected agreements, preventing later implicit access resume.

Retention is idempotent after eligible rows are removed and repeats eligibility at delete time, preventing a stale candidate set from overriding a newer active/re-granted state.

## 13. Security and privacy

K5 current/history/manager projections explicitly whitelist safe fields rather than serialize source models wholesale.

Recipients receive source Alliance ID/name, neutral/current game-Alliance name/tag, accepted observed name/tag, optional power/member count, capture time and descriptive freshness. History additionally uses opaque continuation state only.

Invitation plaintext appears only in the authenticated creation response/component memory. Pending invitation hashes are never page props and are erased on accept/decline/revoke.

Tracking IDs, stable game IDs, observation IDs, manager notes, diplomacy/contact data, roster/transfer data, actors/reasons/correction linkage, K4 provenance and private source data remain excluded.

P5 retention is operational metadata minimization only; active/canonical/Audit/outbox state remains protected.

See [Kingdoms security](security/README.md), [K5 Slice D presentation security review](security/kingdoms-shared-intelligence-presentation-security-review.md), [K5 Slice E retention security review](security/kingdoms-shared-intelligence-retention-security-review.md), and [K5 whole-increment exit report](product/kingdoms-shared-intelligence-exit-report.md).

## 14. Observability and operations

K4 operational health remains unchanged. K5 P5 adds metadata-only retention command counts but no dedicated health dashboard/alert.

Current projection is bounded to 250 rows. P5 proves 300 active grants still return only 250 rows in no more than two SELECTs and within the reviewed 160,000-byte encoded fixture ceiling.

History remains 50 rows/page and 250 accepted observations/traversal. P5 proves 1,000 source observations still produce only five 50-row pages, no more than two SELECTs/page and a reviewed 50,000-byte encoded page ceiling.

Retention is bounded to one total 1–2000 rows/run. Safe operational evidence includes release identity, run timestamp/environment, requested limit and returned per-class/total counts.

Do not log invitation plaintext/hashes, current/history payload bodies or history cursors; do not repair/reactivate sharing via database edits.

See [K5 retention operations](operations/kingdoms-shared-intelligence-retention.md).

## 15. Testing and architecture enforcement

Suites protect identity/tenancy, privacy, history, K4 source/idempotency and K5 consent/grant/current/history/presentation/retention boundaries.

K5 whole-increment runtime candidate `6f84b51ab27941f0fec2abce71f1f2f6325560e4` passed Dependency Review `31573301975`, CodeQL `31573301988`, and CI `31573301977`: Pint 560 files, PHPStan/Larastan 394/394 zero errors, 452 tests / 10,322 assertions, frontend lint/format/type/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

P5 evidence includes bounded/idempotent retention, active/canonical/Audit/outbox preservation, realistic-volume current/history query/response bounds and zero recipient canonical copies. P6 adds whole-seam acceptance evidence for unrelated-tenant denial, safe correction propagation, no copy/reshare/mutation and immediate authorization loss. P1–P4 evidence remains additive.

## 16. Explicit non-capabilities

Current runtime does not provide arbitrary historical-window selection, roster/player sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, transitive reshare, tenant directory, public Kingdoms API/webhook, recipient canonical materialization, scoring/ranking/recommendations or automatic decisions.

K4 production source enablement also remains separately unapproved. K5 repository acceptance is not approval for a broader public or production-policy scope.

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
- [K5 Slice C validation](product/kingdoms-shared-intelligence-slice-c-validation.md)
- [K5 Slice C security review](security/kingdoms-shared-intelligence-history-security-review.md)
- [K5 Slice D validation](product/kingdoms-shared-intelligence-slice-d-validation.md)
- [K5 Slice D presentation security review](security/kingdoms-shared-intelligence-presentation-security-review.md)
- [K5 Slice E validation](product/kingdoms-shared-intelligence-slice-e-validation.md)
- [K5 Slice E security review](security/kingdoms-shared-intelligence-retention-security-review.md)
- [K5 Slice E retention operations](operations/kingdoms-shared-intelligence-retention.md)
- [KINGDOMS-005 exit](product/kingdoms-shared-intelligence-exit-report.md)
- [Alliances](../alliances/README.md)
- [Authorization](../authorization/README.md)
- [Integrations](../integrations/README.md)
- [Program product documentation](../../product/README.md)
- [`app/Domain/Kingdoms/README.md`](../../../app/Domain/Kingdoms/README.md)
