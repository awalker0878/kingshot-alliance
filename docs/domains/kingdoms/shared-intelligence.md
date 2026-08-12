# Opt-in shared Kingdom intelligence

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-005` through `K5-P4` / Slice D runtime validated; `K5-P5` selected pending exact transition-head validation  
**Owning domain:** `Kingdoms`

## 1. Purpose

Opt-in shared Kingdom intelligence provides a deliberately authorized source-Alliance → recipient-Alliance path for selected safe game-Alliance observation facts.

K5 currently includes the P1 consent foundation, P2 explicit target/current-fact sharing, P3 bounded accepted history, and P4 complete first-party member/manager presentation. Source observations remain canonical/source-owned. P5 retention/operations/capacity hardening is selected but not runtime-authorized until the containing transition head is protected-green.

## 2. Scope and non-scope

Current scope includes:

- directional source→recipient invitation/agreement consent;
- source-manager explicit grant/removal of individual active source-owned tracked game Alliances;
- recipient-safe current facts for active/context-valid explicit grants;
- bounded accepted source observation history for one active explicit grant;
- member-safe first-party current/history presentation;
- manager-only first-party invitation/agreement/grant management;
- immediate persisted invitation-hash erasure on accept, decline and revoke; and
- persistent fail-closed K5 agreement invalidation when the supported Alliance→Kingdom workflow changes either participant's Kingdom.

Still out of scope: roster/player/snapshot sharing; transfer sharing; diplomacy/contact sharing; cross-Kingdom sharing; tenant directory/search; transitive reshare; public API/webhooks; scoring/ranking/recommendations; automatic decisions; and P5 retention/capacity runtime until its transition gate opens.

## 3. Model and state

`KingdomIntelligenceShare` captures directional source/recipient/captured-Kingdom consent plus pending/active/declined/revoked state. Pending invitations persist only a SHA-256 token hash; accepted/declined/revoked invitations erase that hash.

`KingdomIntelligenceShareTarget` captures one explicit source grant from an agreement to one source-owned `TrackedKingdomAlliance`. Target state is `active|removed`; a removed target requires another deliberate source-manager action before sharing resumes.

Current/history reads project source-owned accepted `KingdomAllianceObservation` rows. K5 does not materialize recipient observation history.

History continuation state is encrypted/authenticated transient cursor data, not a persisted data-ownership record or reusable authorization credential.

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

## 5. Workflows

P1 consent remains source invitation → recipient accept/decline → source revoke / recipient leave. Accept, decline and revoke clear persisted invitation hashes immediately.

P2 source managers add/remove one active source-owned tracking target under an active share. Both mutations require active Alliance context, `kingdoms.manage`, and recent password confirmation.

Recipient current facts are provided by `SharedKingdomIntelligenceCurrentQuery`.

P3 accepted history is provided by `SharedKingdomIntelligenceHistoryQuery` for one explicit share target. The first page fixes an internal `asOf`; continuation pages use an encrypted target-bound cursor containing the fixed snapshot, keyset position and authenticated `seen` count. Pages are capped at 50 and one traversal stops at 250 accepted observations.

P4 exposes those accepted reads through `Alliance/KingdomSharing` and consent/grant management through `Alliance/KingdomSharingManage`. History links carry only explicit target + opaque server-issued cursor; the UI exposes no `asOf` selector.

When an Alliance changes Kingdom through `UpdateAllianceKingdom`, affected active agreements/source pending invitations are terminalized before commit. A new relationship requires a new invitation/agreement.

## 6. Authorization, tenancy and privacy

Consent/target mutations use first-party authenticated active-Alliance routes. Domain actions independently enforce `kingdoms.manage`.

Current/history facts are member-safe under first-party active-Alliance presentation. Every read re-authorizes the active recipient Alliance through an active share and active explicit grant, and revalidates source/recipient/tracking Kingdom/state.

The management workspace requires `kingdoms.manage`; ordinary members cannot access agreement/grant controls or source trackable-target inventory.

Safe current/history data is limited to source Alliance ID/name, neutral/current game-Alliance name/tag, accepted observed name/tag, optional power/member count, capture time and descriptive freshness.

Excluded: invitation hashes/plaintext outside the creation response, source tracking IDs, stable game IDs, observation IDs, actors, correction/invalidation reason/linkage, manager notes, diplomacy/contact data, player/roster/transfer data, K4 provenance/raw source data/secrets and Audit/outbox internals.

Counterpart Audit records use null actor where needed to avoid cross-tenant manager User-ID leakage.

## 7. Persistence and query semantics

`kingdom_intelligence_shares` stores consent metadata; `kingdom_intelligence_share_targets` stores explicit grant history. Source `KingdomAllianceObservation` rows remain canonical.

The accepted P1 migration remains historical evidence. P4's forward `030000` migration makes `invitation_token_hash` nullable so consumed/terminal secret-derived values can be erased. Its rollback writes deterministic per-share retired placeholders solely to satisfy the historical non-null schema; reapply recognizes terminal placeholders and restores null. Pending invitation hashes are preserved.

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250 and uses no more than two SELECTs under the focused 12-target fixture.

`SharedKingdomIntelligenceHistoryQuery` uses 50-row maximum keyset pages and the K3 `HISTORY_LIMIT = 250`. Each page uses one authorization/context query and one accepted-observation query under the focused 260-observation fixture.

The history cursor is encrypted/authenticated and target-bound. It fixes `asOf`, last capture timestamp/order ID and cumulative accepted count. Invalid/tampered/wrong-target cursors fail with a bounded validation error.

Source invalidation removes the invalidated observation from both current and later history pages immediately. Corrected replacements appear only as their own accepted observations; private correction metadata remains source-private.

## 8. Events/integrations/background processing

P1 consent and P2 target/context events remain internal `kingdoms.*` events and external-webhook ineligible.

P3/P4 presentation adds no public integration event. Current/history payloads/cursors and invitation plaintext are not Audit/outbox payloads and must not be copied into general logs.

K5 through P4 adds no dedicated background job, scheduler, public API, inbound callback, anonymous sharing feed or external machine credential.

## 9. Failure, idempotency and concurrency

Invalid/expired/used invitation, self-share, different-Kingdom activation, duplicate active agreement and unrelated-tenant IDs fail closed.

Target add rejects invalid/stale context or non-source tracking. Active re-add is idempotent; a removed target requires deliberate re-grant.

Current/history authorization is re-evaluated on each request/page. A stale history cursor cannot bypass target removal, share revocation or context invalidation.

Relevant mutation lock order remains Alliance(s) → share → target. Supported Kingdom drift permanently terminalizes affected agreements, so returning cannot silently resume access.

History keyset pagination avoids mutable client offsets; encrypted cursor state is bound to target/fixed snapshot and total accepted-record cap.

## 10. Operations and observability

K5 through P4 adds no K5 health command/background workload. Safe diagnostics remain share/target identifiers, authorized Alliance IDs, captured Kingdom, state and timestamps.

Do not log invitation plaintext, invitation hashes, history/current payload bodies, encrypted cursors, source manager notes, diplomacy/contact data or K4 provenance. Do not repair/reactivate shares/targets by database edits.

Retention/cleanup of expired/terminal agreement/grant operational records, realistic-volume current/history capacity and any authorization-safe caching remain P5 work. Immediate terminal invitation-hash erasure is already P4 runtime behavior and must not be deferred to P5 retention jobs.

## 11. Tests and validation

P4 runtime candidate `9a095ae62e9b913ece6d619c3744574f0b91fd6f` passed Dependency Review `31569202741`, CodeQL `31569202422`, and CI `31569202418`.

CI passed Pint for **556 files**, PHPStan/Larastan **393/393 with zero errors**, **448 tests / 10,160 assertions**, frontend lint/locked-format/type/build, clean PostgreSQL migrations, immutable image, ephemeral staging, backup/restore, image scan and cleanup.

Focused P4 evidence proves member/manager page-prop isolation, manager-only access, invitation plaintext non-persistence, immediate terminal hash erasure, nullable-hash migration down/up recovery, fail-closed lifecycle/context behavior, opaque bounded history navigation, absence of arbitrary `asOf` UI and accessible controls.

P2/P3 current/history query and no-copy evidence remains additive and accepted.

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
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
