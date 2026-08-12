# Opt-in shared Kingdom intelligence

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-005` through `K5-P2` / Slice B runtime validated; bounded shared history not yet implemented  
**Owning domain:** `Kingdoms`

## 1. Purpose

Opt-in shared Kingdom intelligence provides a deliberately authorized source-Alliance → recipient-Alliance path for selected safe game-Alliance observation facts.

K5 currently includes the P1 consent foundation plus P2 explicit target grants and a bounded safe **current-fact** recipient projection. Source observations remain source-owned. Bounded shared observation history is not yet implemented and remains `K5-P3`.

## 2. Scope and non-scope

Current scope includes:

- directional source→recipient invitation/agreement consent;
- source-manager explicit grant/removal of individual active source-owned tracked game Alliances;
- recipient-safe current facts for active, context-valid explicit grants; and
- persistent fail-closed K5 agreement invalidation when the supported Alliance→Kingdom workflow changes either participant's Kingdom.

Still out of scope: bounded shared history; full K5 source/recipient pages; roster/player/snapshot sharing; transfer sharing; diplomacy/contact sharing; cross-Kingdom sharing; tenant directory/search; transitive reshare; public API/webhooks; scoring/ranking/recommendations; and automatic decisions.

## 3. Model and state

`KingdomIntelligenceShare` captures directional source/recipient/captured-Kingdom consent plus hash-only invitation identity and pending/active/declined/revoked state.

`KingdomIntelligenceShareTarget` captures one explicit source grant from an agreement to one source-owned `TrackedKingdomAlliance`. Target state is `active|removed`. A unique share/tracking pair preserves grant history while requiring another deliberate manager action to reactivate a removed target.

Neither K5 table stores recipient copies of source observation history.

## 4. Invariants

1. Sharing is directional; reverse sharing requires another agreement.
2. Source and recipient are different Alliances.
3. Agreement activation requires both current Kingdoms to equal the captured invitation Kingdom.
4. Invitation plaintext is never persisted; redemption is single-use.
5. An active agreement alone shares no observation data.
6. Every P2 target is explicitly selected by the source; no wildcard share-all mode exists.
7. Target grant requires an active source-owned agreement, active source/recipient Alliances, matching current/captured Kingdom and active source-owned tracking in that Kingdom.
8. Recipient reads begin from active recipient Alliance authorization, then active agreement, active explicit grant and live source tracking/context.
9. Neutral `KingdomAlliance` identity never grants cross-tenant access.
10. Source canonical observations remain source-owned; recipient reads create no local tracking/observation copy.
11. Received intelligence cannot be used as an upstream K5 grant; reshare is non-transitive.
12. Latest current fact uses accepted K3 observation semantics: non-invalidated, capture-time ordered by `captured_at DESC, id DESC`.
13. Freshness reuses the K3 30-day descriptive boundary and remains `current|stale|missing`; missing is not zero.
14. Target removal, share revocation or context invalidation immediately removes recipient visibility.
15. A supported Alliance→Kingdom change terminalizes affected active K5 agreements; returning to the old Kingdom does not reactivate them.
16. All K5 events remain internal/public-webhook ineligible.
17. P2 exposes current facts only; bounded history remains P3.

## 5. Workflows

P1 consent remains unchanged: source creates a bounded one-time invitation; recipient accepts or declines; source may revoke; active recipient may leave.

P2 source managers may add an individual active source-owned tracking relationship to an active share, or remove a previously granted target. Both HTTP target mutations use active Alliance context, `kingdoms.manage`, and recent password confirmation.

Recipient current facts are provided by the internal `SharedKingdomIntelligenceCurrentQuery`. It first resolves active recipient/share/grant/context authorization, then loads only the latest accepted source observation for the authorized tracking set.

When an Alliance changes Kingdom through `UpdateAllianceKingdom`, affected active agreements and source pending invitations are terminalized before the transaction completes. A new sharing relationship requires a new invitation/agreement.

## 6. Authorization, tenancy and privacy

Consent and target mutations use first-party authenticated active-Alliance routes. Domain actions independently enforce `kingdoms.manage`; current recipient facts are member-safe data intended for later P4 presentation under `alliance.view`.

Target creation re-resolves the share under the source Alliance and tracking under that same source Alliance. Recipient current queries begin from `recipient_alliance_id`, not from a neutral game-Alliance or submitted tracking identifier.

The safe P2 projection contains only source Alliance ID/name, neutral/current game-Alliance name/tag, latest accepted observed name/tag, optional power/member count, capture time and freshness.

It excludes source tracking IDs, stable game IDs, manager notes, diplomacy/contact data, player/roster/transfer data, observation actor/correction/invalidation reason, K4 provenance/raw source data/secrets and Audit/outbox internals.

Counterpart Audit records use null actor where needed to avoid cross-tenant manager User-ID leakage.

## 7. Persistence and query semantics

`kingdom_intelligence_shares` stores consent metadata. `kingdom_intelligence_share_targets` stores explicit grant history. Source `KingdomAllianceObservation` rows remain canonical and are never copied into recipient-owned observation history.

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250. Under the focused multi-target fixture it uses no more than two SELECT queries: one recipient-first authorization/grant projection and one latest accepted observation query.

Latest observations require `invalidated_at IS NULL`, `captured_at <= as-of`, and K3 ordering by capture time then ID. If the latest source fact is invalidated, recipient output immediately falls back to the next accepted source fact if one exists; private invalidation metadata remains hidden.

There is no bounded shared-history query in P2.

## 8. Events/integrations/background processing

P1 consent events remain internal. P2 adds:

- `kingdoms.shared_intelligence_target_shared`;
- `kingdoms.shared_intelligence_target_removed`; and
- `kingdoms.shared_intelligence_context_invalidated`.

All remain `kingdoms.*` internal events and external-webhook ineligible. Durable evidence uses safe IDs/state/reason metadata only.

K5-P2 adds no background job, scheduler, public API, inbound callback, anonymous sharing feed or external machine credential.

## 9. Failure, idempotency and concurrency

Invalid/expired/used invitation, self-share, different-Kingdom activation, duplicate active agreement and unrelated-tenant IDs fail closed.

Target add rejects invalid/stale context or non-source tracking. Re-adding an already active target is idempotent; a removed target requires deliberate re-grant. Removal is access-reducing and remains safe even after other context changes.

Relevant mutation lock order is aligned to Alliance(s) → share → target where Kingdom drift can race with consent/grant changes. Source/recipient Alliances are locked in deterministic ID order for acceptance/grant operations.

Supported Kingdom drift permanently terminalizes affected agreements, so returning to the captured Kingdom cannot silently resume access.

## 10. Operations and observability

K5-P2 adds no K5 health command or background operational workload. Safe diagnostics are share/target IDs, authorized source/recipient Alliance IDs, captured Kingdom, state and timestamps.

Do not log invitation plaintext, shared observation payload bodies, source manager notes, diplomacy/contact data or K4 provenance. Do not repair or reactivate shares/targets by database edits.

Retention/cleanup of used/expired invitation material and realistic-volume cross-tenant capacity hardening remain P5 work.

## 11. Tests and validation

P2 runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed Dependency Review `31562753429`, CodeQL `31562753422`, and CI `31562753430`.

CI passed Pint for **550 files**, PHPStan/Larastan **390/390 with zero errors**, **440 tests / 10,025 assertions**, frontend/build, clean PostgreSQL migrations through `2026_08_12_020000_create_kingdom_intelligence_share_targets`, immutable image build, ephemeral staging, backup/restore, image scan and cleanup.

Focused evidence proves explicit-target-only visibility, strict safe-field projection, latest accepted/invalidation fallback, current/stale/missing semantics, no recipient canonical copy, no reshare, source-only target mutation, immediate removal/revoke/drift loss of access, no access resume after leaving and returning to a Kingdom, bounded two-SELECT current projection, and full/focused migration rollback/reapply integrity.

## 12. Related documentation

- [KINGDOMS-005 product increment](product/kingdoms-shared-intelligence-increment.md)
- [KINGDOMS-005 implementation plan](product/kingdoms-shared-intelligence-implementation-plan.md)
- [K5-P0 decisions](product/kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 exit report](product/kingdoms-shared-intelligence-p0-exit-report.md)
- [K5 Slice A validation](product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5 Slice A security review](security/kingdoms-shared-intelligence-foundation-security-review.md)
- [K5 Slice B validation](product/kingdoms-shared-intelligence-slice-b-validation.md)
- [K5 Slice B security review](security/kingdoms-shared-intelligence-current-facts-security-review.md)
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
