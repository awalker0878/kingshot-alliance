# Opt-in shared Kingdom intelligence

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-005` **Accepted**; `K5-P0`–`K5-P6` Complete  
**Owning domain:** `Kingdoms`

## 1. Purpose

Opt-in shared Kingdom intelligence provides a deliberately authorized source-Alliance → recipient-Alliance path for selected safe game-Alliance observation facts.

K5 includes the P1 consent foundation, P2 explicit target/current-fact sharing, P3 bounded accepted history, P4 complete first-party member/manager presentation, P5 bounded retention/operations/capacity hardening, and P6 whole-increment acceptance. Source observations remain canonical/source-owned. P6 added acceptance evidence only and did not widen the runtime boundary.

## 2. Scope and non-scope

Current accepted scope includes:

- directional source→recipient invitation/agreement consent;
- source-manager explicit grant/removal of individual active source-owned tracked game Alliances;
- recipient-safe current facts for active/context-valid explicit grants;
- bounded accepted source observation history for one active explicit grant;
- member-safe first-party current/history presentation;
- manager-only first-party invitation/agreement/grant management;
- immediate persisted invitation-hash erasure on accept, decline and revoke;
- persistent fail-closed K5 agreement invalidation when the supported Alliance→Kingdom workflow changes either participant's Kingdom;
- bounded scheduled retention of eligible old K5 operational consent/grant rows;
- realistic-volume current/history regression/capacity evidence without a recipient cache/materialized copy; and
- whole-seam acceptance evidence for unrelated-tenant denial, no copy/reshare/mutation and immediate authorization loss.

Still out of scope: roster/player/snapshot sharing; transfer sharing; diplomacy/contact sharing; cross-Kingdom sharing; tenant directory/search; transitive reshare; public API/webhooks; scoring/ranking/recommendations; automatic decisions; recipient canonical materialization; and arbitrary historical-window reopening.

## 3. Model and state

`KingdomIntelligenceShare` captures directional source/recipient/captured-Kingdom consent plus pending/active/declined/revoked state. Pending invitations persist only a SHA-256 token hash; accepted/declined/revoked invitations erase that hash.

`KingdomIntelligenceShareTarget` captures one explicit source grant from an agreement to one source-owned `TrackedKingdomAlliance`. Target state is `active|removed`; a removed target requires another deliberate source-manager action before sharing resumes.

Current/history reads project source-owned accepted `KingdomAllianceObservation` rows. K5 does not materialize recipient observation history.

History continuation state is encrypted/authenticated transient cursor data, not a persisted data-ownership record or reusable authorization credential.

P5 operational retention may delete only sufficiently old pending/terminal/removed K5 operational rows. It never deletes active shares/grants, source tracking/observations, Audit events or outbox messages.

## 4. Invariants

1. Sharing is directional; reverse sharing requires another agreement.
2. Source and recipient are different Alliances.
3. Agreement activation requires both current Kingdoms to equal the captured invitation Kingdom.
4. Invitation plaintext is never persisted; redemption is single-use; terminal/consumed invitation hashes are erased.
5. An active agreement alone shares no observation data.
6. Every target is explicitly selected by the source; no wildcard share-all mode exists.
7. Target grant requires active source-owned agreement, active source/recipient Alliances, matching current/captured Kingdom and active source-owned tracking in that Kingdom.
8. Recipient reads begin from active recipient Alliance → active agreement → active explicit grant → live source tracking/context.
9. Neutral `KingdomAlliance` identity never grants cross-tenant access.
10. Source canonical observations remain source-owned; recipient reads create no local tracking/observation copy.
11. Received intelligence cannot be used as an upstream K5 grant; reshare is non-transitive.
12. Current/history use accepted K3 observation semantics: `invalidated_at IS NULL`, deterministic `captured_at DESC, id DESC`.
13. Current freshness remains K3 30-day `current|stale|missing`; history item freshness is descriptive `current|stale`.
14. Target removal, share revocation or context invalidation immediately removes current/history visibility.
15. Supported Alliance→Kingdom change terminalizes affected K5 agreements; returning to the old Kingdom does not reactivate them.
16. Shared history is paginated at up to 50 rows/page and capped at 250 accepted observations per traversal.
17. History continuation uses an encrypted cursor bound to the share target and fixed `asOf`; a cursor is never standalone authorization.
18. First-party presentation exposes no arbitrary client-controlled historical `asOf` window.
19. Manager agreement/grant state remains manager-only; safe current/history facts are member-safe.
20. Invitation plaintext exists only in the authenticated creation response/component memory and never in Inertia/session props.
21. All K5 events remain internal/public-webhook ineligible.
22. Retention has one total bounded work budget and cannot create/reactivate authorization.
23. Active shares/grants and canonical source tracking/observations are never retention-eligible.
24. Retention deletion repeats state/cutoff eligibility so stale candidate IDs cannot delete rows that became non-eligible.
25. Audit/outbox business evidence remains outside K5 operational retention.

## 5. Workflows

P1 consent remains source invitation → recipient accept/decline → source revoke / recipient leave. Accept, decline and revoke clear persisted invitation hashes immediately.

P2 source managers add/remove one active source-owned tracking target under an active share. Both mutations require active Alliance context, `kingdoms.manage`, and recent password confirmation.

Recipient current facts are provided by `SharedKingdomIntelligenceCurrentQuery`.

P3 accepted history is provided by `SharedKingdomIntelligenceHistoryQuery` for one explicit share target. The first page fixes an internal `asOf`; continuation pages use an encrypted target-bound cursor containing the fixed snapshot, keyset position and authenticated `seen` count. Pages are capped at 50 and one traversal stops at 250 accepted observations.

P4 exposes those accepted reads through `Alliance/KingdomSharing` and consent/grant management through `Alliance/KingdomSharingManage`. History links carry only explicit target + opaque server-issued cursor; the UI exposes no `asOf` selector.

P5 adds `kingdoms:enforce-sharing-retention --limit=500`, scheduled daily at 04:30 on one server without overlap. One invocation uses one total 1–2000 budget and processes expired pending invitations → old terminal shares → old removed grants.

When an Alliance changes Kingdom through `UpdateAllianceKingdom`, affected active agreements/source pending invitations are terminalized before commit. A new relationship requires a new invitation/agreement.

P6 adds no workflow. It composes the accepted workflows end to end through `KingdomSharedIntelligenceAcceptanceTest`.

## 6. Authorization, tenancy and privacy

Consent/target mutations use first-party authenticated active-Alliance routes. Domain actions independently enforce `kingdoms.manage`.

Current/history facts are member-safe under first-party active-Alliance presentation. Every read re-authorizes the active recipient Alliance through an active share and active explicit grant, and revalidates source/recipient/tracking Kingdom/state.

The management workspace requires `kingdoms.manage`; ordinary members cannot access agreement/grant controls or source trackable-target inventory.

Safe current/history data is limited to source Alliance ID/name, neutral/current game-Alliance name/tag, accepted observed name/tag, optional power/member count, capture time and descriptive freshness.

Excluded: invitation hashes/plaintext outside the creation response, source tracking IDs, stable game IDs, observation IDs, actors, correction/invalidation reason/linkage, manager notes, diplomacy/contact data, player/roster/transfer data, K4 provenance/raw source data/secrets and Audit/outbox internals.

Counterpart Audit records use null actor where needed to avoid cross-tenant manager User-ID leakage.

Retention does not widen tenant visibility or grant any recipient/operator read capability.

## 7. Persistence and query semantics

`kingdom_intelligence_shares` stores consent metadata; `kingdom_intelligence_share_targets` stores explicit grant history. Source `KingdomAllianceObservation` rows remain canonical.

The accepted P1 migration remains historical evidence. P4's forward `030000` migration makes `invitation_token_hash` nullable so consumed/terminal secret-derived values can be erased. Its rollback writes deterministic per-share retired placeholders solely to satisfy the historical non-null schema; reapply recognizes terminal placeholders and restores null. Pending invitation hashes are preserved.

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250. P5 realistic-volume evidence at 300 active grants proves exactly 250 returned rows, no more than two SELECTs and no recipient canonical observations.

`SharedKingdomIntelligenceHistoryQuery` uses 50-row maximum keyset pages and `HISTORY_LIMIT = 250`. P5 realistic-volume evidence at 1,000 source observations proves five 50-row pages, termination at 250 and no more than two SELECTs per page.

The reviewed encoded fixture ceilings are 160,000 bytes for the bounded current projection and 50,000 bytes per history page. These are regression/capacity evidence, not production throughput/latency SLOs.

The history cursor is encrypted/authenticated and target-bound. It fixes `asOf`, last capture timestamp/order ID and cumulative accepted count. Invalid/tampered/wrong-target cursors fail with a bounded validation error.

Source invalidation removes the invalidated observation from both current and later history pages immediately. Corrected replacements appear only as their own accepted observations; private correction metadata remains source-private.

## 8. Events/integrations/background processing

P1 consent and P2 target/context events remain internal `kingdoms.*` events and external-webhook ineligible.

P3/P4 presentation adds no public integration event. Current/history payloads/cursors and invitation plaintext are not Audit/outbox payloads and must not be copied into general logs.

P5 adds one internal Artisan/scheduler maintenance surface only. It adds no public API, inbound callback, anonymous sharing feed, external machine credential, queue job or external provider dependency.

P6 adds no integration surface.

## 9. Failure, idempotency and concurrency

Invalid/expired/used invitation, self-share, different-Kingdom activation, duplicate active agreement and unrelated-tenant IDs fail closed.

Target add rejects invalid/stale context or non-source tracking. Active re-add is idempotent; a removed target requires deliberate re-grant.

Current/history authorization is re-evaluated on each request/page. A stale history cursor cannot bypass target removal, share revocation or context invalidation.

Relevant mutation lock order remains Alliance(s) → share → target. Supported Kingdom drift permanently terminalizes affected agreements, so returning cannot silently resume access.

History keyset pagination avoids mutable client offsets; encrypted cursor state is bound to target/fixed snapshot and total accepted-record cap.

Retention is idempotent after eligible rows are gone. It selects bounded candidate IDs and repeats state/cutoff predicates during deletion, preserving a row that changed to a non-eligible state between the two steps.

## 10. Operations and observability

K5 provides the bounded retention command and schedule. Safe diagnostics remain share/target identifiers, authorized Alliance IDs, captured Kingdom, state/timestamps, configured retention windows and command result counts.

Default operational retention windows are 30 days after invitation expiry, 180 days for terminal shares and 90 days for removed grants. The command default/scheduled budget is 500 and runtime cap is 2000.

Do not log invitation plaintext, invitation hashes, history/current payload bodies, encrypted cursors, source manager notes, diplomacy/contact data or K4 provenance. Do not repair/reactivate shares/targets by database edits.

After database restore, live authorization remains authoritative. If old eligible operational rows are restored, rerun bounded retention only after normal restore validation.

See [shared-intelligence retention operations](operations/kingdoms-shared-intelligence-retention.md).

## 11. Tests and validation

Whole-increment runtime candidate `6f84b51ab27941f0fec2abce71f1f2f6325560e4` passed Dependency Review `31573301975`, CodeQL `31573301988`, and CI `31573301977`.

CI passed Pint for **560 files**, PHPStan/Larastan **394/394 with zero errors**, **452 tests / 10,322 assertions**, frontend lint/locked-format/type/build, clean PostgreSQL migrations, immutable image, ephemeral staging, backup/restore, image scan and cleanup.

`KingdomSharedIntelligenceAcceptanceTest` re-proves pending-secret handling, no visibility before explicit grant, correction-safe current/history, unrelated-tenant failure, recipient no-copy/no-reshare, member/manager prop isolation, immediate remove/revoke denial, terminal retention, canonical observation survival and durable Audit/outbox evidence.

P1–P5 consent/current/history/presentation/security/retention/capacity evidence remains additive and accepted.

## 12. Related documentation

- [KINGDOMS-005 product increment](product/kingdoms-shared-intelligence-increment.md)
- [KINGDOMS-005 implementation plan](product/kingdoms-shared-intelligence-implementation-plan.md)
- [K5-P0 decisions](product/kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 exit report](product/kingdoms-shared-intelligence-p0-exit-report.md)
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
- [K5 whole-increment exit report](product/kingdoms-shared-intelligence-exit-report.md)
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
