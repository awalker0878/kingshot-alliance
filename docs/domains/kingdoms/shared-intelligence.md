# Opt-in shared Kingdom intelligence

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-005` through `K5-P3` / Slice C runtime validated; complete first-party sharing UX remains P4  
**Owning domain:** `Kingdoms`

## 1. Purpose

Opt-in shared Kingdom intelligence provides a deliberately authorized source-Alliance → recipient-Alliance path for selected safe game-Alliance observation facts.

K5 currently includes the P1 consent foundation, P2 explicit target/current-fact sharing, and P3 bounded accepted history for one explicitly shared target. Source observations remain canonical/source-owned. Complete source/recipient sharing pages and accessibility remain `K5-P4`.

## 2. Scope and non-scope

Current scope includes:

- directional source→recipient invitation/agreement consent;
- source-manager explicit grant/removal of individual active source-owned tracked game Alliances;
- recipient-safe current facts for active/context-valid explicit grants;
- bounded accepted source observation history for one active explicit grant; and
- persistent fail-closed K5 agreement invalidation when the supported Alliance→Kingdom workflow changes either participant's Kingdom.

Still out of scope: complete K5 source/recipient pages; roster/player/snapshot sharing; transfer sharing; diplomacy/contact sharing; cross-Kingdom sharing; tenant directory/search; transitive reshare; public API/webhooks; scoring/ranking/recommendations; and automatic decisions.

## 3. Model and state

`KingdomIntelligenceShare` captures directional source/recipient/captured-Kingdom consent plus hash-only invitation identity and pending/active/declined/revoked state.

`KingdomIntelligenceShareTarget` captures one explicit source grant from an agreement to one source-owned `TrackedKingdomAlliance`. Target state is `active|removed`; a removed target requires another deliberate source-manager action before sharing resumes.

Current/history reads project source-owned accepted `KingdomAllianceObservation` rows. K5 does not materialize recipient observation history.

## 4. Invariants

1. Sharing is directional; reverse sharing requires another agreement.
2. Source and recipient are different Alliances.
3. Agreement activation requires both current Kingdoms to equal the captured invitation Kingdom.
4. Invitation plaintext is never persisted; redemption is single-use.
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
18. P4 must not expose arbitrary client-controlled historical `asOf` windows without separate review.
19. All K5 events remain internal/public-webhook ineligible.

## 5. Workflows

P1 consent remains source invitation → recipient accept/decline → source revoke / recipient leave.

P2 source managers add/remove one active source-owned tracking target under an active share. Both mutations require active Alliance context, `kingdoms.manage`, and recent password confirmation.

Recipient current facts are provided by `SharedKingdomIntelligenceCurrentQuery`.

P3 accepted history is provided by `SharedKingdomIntelligenceHistoryQuery` for one explicit share target. The first page fixes an internal `asOf`; continuation pages use an encrypted target-bound cursor containing the fixed snapshot, keyset position and authenticated `seen` count. Pages are capped at 50 and one traversal stops at 250 accepted observations.

When an Alliance changes Kingdom through `UpdateAllianceKingdom`, affected active agreements/source pending invitations are terminalized before commit. A new relationship requires a new invitation/agreement.

## 6. Authorization, tenancy and privacy

Consent/target mutations use first-party authenticated active-Alliance routes. Domain actions independently enforce `kingdoms.manage`.

Current/history facts are member-safe data intended for P4 presentation under `alliance.view`. Every read re-authorizes the active recipient Alliance through an active share and active explicit grant, and revalidates source/recipient/tracking Kingdom/state.

Safe current/history data is limited to source Alliance ID/name, neutral/current game-Alliance name/tag, accepted observed name/tag, optional power/member count, capture time and descriptive freshness.

Excluded: source tracking IDs, stable game IDs, observation IDs, actors, correction/invalidation reason/linkage, manager notes, diplomacy/contact data, player/roster/transfer data, K4 provenance/raw source data/secrets and Audit/outbox internals.

Counterpart Audit records use null actor where needed to avoid cross-tenant manager User-ID leakage.

## 7. Persistence and query semantics

`kingdom_intelligence_shares` stores consent metadata; `kingdom_intelligence_share_targets` stores explicit grant history. Source `KingdomAllianceObservation` rows remain canonical.

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250 and uses no more than two SELECTs under the focused 12-target fixture.

`SharedKingdomIntelligenceHistoryQuery` uses 50-row maximum keyset pages and the K3 `HISTORY_LIMIT = 250`. Each page uses one authorization/context query and one accepted-observation query under the focused 260-observation fixture.

The history cursor is encrypted/authenticated and target-bound. It fixes `asOf`, last capture timestamp/order ID and cumulative accepted count. Invalid/tampered/wrong-target cursors fail with a bounded validation error.

Source invalidation removes the invalidated observation from both current and later history pages immediately. Corrected replacements appear only as their own accepted observations; private correction metadata remains source-private.

## 8. Events/integrations/background processing

P1 consent and P2 target/context events remain internal `kingdoms.*` events and external-webhook ineligible.

P3 adds no business mutation event because it adds a read-only history query. History payloads/cursors are not Audit/outbox payloads and must not be copied into general logs.

K5-P3 adds no background job, scheduler, public API, inbound callback, anonymous sharing feed or external machine credential.

## 9. Failure, idempotency and concurrency

Invalid/expired/used invitation, self-share, different-Kingdom activation, duplicate active agreement and unrelated-tenant IDs fail closed.

Target add rejects invalid/stale context or non-source tracking. Active re-add is idempotent; a removed target requires deliberate re-grant.

Current/history authorization is re-evaluated on each request/page. A stale history cursor cannot bypass target removal, share revocation or context invalidation.

Relevant mutation lock order remains Alliance(s) → share → target. Supported Kingdom drift permanently terminalizes affected agreements, so returning cannot silently resume access.

History keyset pagination avoids mutable client offsets; encrypted cursor state is bound to target/fixed snapshot and total accepted-record cap.

## 10. Operations and observability

K5-P3 adds no K5 health command/background workload. Safe diagnostics remain share/target identifiers, authorized Alliance IDs, captured Kingdom, state and timestamps.

Do not log invitation plaintext, history/current payload bodies, encrypted cursors, source manager notes, diplomacy/contact data or K4 provenance. Do not repair/reactivate shares/targets by database edits.

Retention/cleanup of used/expired invitation material plus realistic-volume current/history capacity and any authorization-safe caching remain P5 work.

## 11. Tests and validation

P3 runtime candidate `70739d320caab059d2102feda081be33754b77ec` passed Dependency Review `31564263865`, CodeQL `31564263863`, and CI `31564263891`.

CI passed Pint for **553 files**, PHPStan/Larastan **392/392 with zero errors**, **443 tests / 10,086 assertions**, frontend/build, clean PostgreSQL migrations, immutable image, ephemeral staging, backup/restore, image scan and cleanup.

Focused history evidence proves encrypted target-bound cursor behavior, deterministic accepted-only pagination, correction/invalidation privacy, hard 250-record traversal cap, 50-row page cap, no more than two SELECTs per page, no recipient canonical copy and immediate fail-closed history after target removal/share revoke/Kingdom drift including no resume after returning.

P2 current-fact evidence remains additive and accepted.

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
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
