# Kingdoms interfaces

[← Kingdoms domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P3 bounded shared history validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary boundary:** Authenticated Alliance Kingdoms workspaces, K4 ingestion control/operations, K5 first-party consent/target mutations, bounded internal current/history queries, and internal-only `kingdoms.*` events

**P4 inventory decision:** Existing accepted Kingdoms capability contracts remain the focused contract authority; K5 extends this profile without changing the frozen P4 interface-inventory convention or creating a public Kingdoms API.

## 1. Boundary purpose and ownership

Kingdoms owns first-party neutral-game identity, roster/history/intelligence, transfer, game-Alliance tracking/observations/diplomacy/contacts, K4 ingestion, and K5 directional consent/target/current/history sharing.

P3 adds a bounded internal recipient accepted-history query. Complete source/recipient K5 pages do not exist yet and remain P4 work.

## 2. Surface inventory

Existing K1–K4 routes remain unchanged. K5 password-confirmed authenticated mutation routes are:

- `POST /alliance/kingdom-sharing/invitations` — source creates invitation and receives `{shareId, token}` once;
- `POST /alliance/kingdom-sharing/invitations/accept` — recipient accepts token;
- `POST /alliance/kingdom-sharing/invitations/decline` — recipient declines token;
- `POST /alliance/kingdom-sharing/{share}/revoke` — source revokes pending/active share;
- `POST /alliance/kingdom-sharing/{share}/leave` — active recipient leaves;
- `POST /alliance/kingdom-sharing/{share}/targets/{tracking}` — source explicitly grants one source-owned tracking target; and
- `POST /alliance/kingdom-sharing/{share}/targets/{target}/remove` — source removes one explicit target grant.

P3 adds no K5 GET/current/history route, public API/callback, tenant directory or complete sharing page surface. Current/history remain internal domain-query contracts until P4.

## 3. Callers, authorization and tenancy

Consent/target mutations require active Alliance context, recent password confirmation and domain-level `kingdoms.manage`.

Source mutations resolve shares beneath `source_alliance_id`; target grant resolves tracking beneath the same source Alliance. Recipient current/history facts resolve only from the active recipient Alliance through an active/context-valid agreement and explicit active grant.

Every history page repeats live recipient/share/grant/source-context authorization. A continuation cursor never grants access by itself.

Neutral Kingdom/game-Alliance identity never authorizes K5 access.

## 4. Input and validation contracts

Invitation accept/decline accepts a required 64-character lowercase hexadecimal token; lookup uses its hash.

Acceptance rejects invalid/expired/used token, self-share, different-current-Kingdom context and duplicate active agreement.

Target grant accepts route share/tracking IDs only after source-tenant re-resolution and rejects terminal/stale agreements, inactive/different-Kingdom participants, inactive/non-source tracking and captured-Kingdom mismatch. Removal re-resolves target beneath the source-owned share.

P3 history accepts one explicit share-target ID, an optional opaque encrypted continuation cursor and an internally bounded page size. The cursor is target-bound and fixes the history `asOf` snapshot/keyset position/accepted-record count. Malformed, tampered or wrong-target cursors fail closed.

P4 must not expose an arbitrary client-controlled history `asOf` parameter or equivalent mechanism for repeatedly opening progressively older history windows.

No arbitrary observation payload, endpoint, credential, roster/diplomacy/contact data or recipient-supplied source fact enters K5 sharing.

## 5. Output and disclosure contracts

Invitation creation returns share ID plus plaintext token once. Consent/target mutations return redirect/status outcomes only.

`SharedKingdomIntelligenceCurrentQuery` is bounded to 250 rows. Each row includes only opaque target ID, source Alliance `{id,name}`, game Alliance `{name,tag}`, `current|stale|missing` freshness, and latest accepted `{observedName,observedTag,power,memberCount,capturedAt}` or null.

`SharedKingdomIntelligenceHistoryQuery` returns one target's safe context plus up to 50 accepted history items and an opaque `nextCursor`. Each history item includes only `{observedName,observedTag,power,memberCount,capturedAt,freshness}`.

One history traversal stops after 250 accepted observations. Both current/history exclude source tracking/stable game/observation IDs, actors, correction/invalidation metadata, private/K4 fields and recipient mutation authority.

## 6. Internal actions, queries and services

Current K5 contracts include:

- `CreateKingdomIntelligenceShareInvitation`;
- `AcceptKingdomIntelligenceShareInvitation`;
- `DeclineKingdomIntelligenceShareInvitation`;
- `RevokeKingdomIntelligenceShare`;
- `LeaveKingdomIntelligenceShare`;
- `AddKingdomIntelligenceShareTarget`;
- `RemoveKingdomIntelligenceShareTarget`;
- `InvalidateKingdomIntelligenceSharesForAllianceDrift`;
- `KingdomIntelligenceShareTokenService`;
- `SharedKingdomIntelligenceCurrentQuery`;
- `SharedKingdomIntelligenceHistoryQuery`; and
- `SharedKingdomIntelligenceHistoryCursor`.

The history cursor is an internal continuation mechanism, not a public authentication/authorization credential.

## 7. Events, outbox and cross-domain consumers

P1 consent events and P2 target/context events remain internal `kingdoms.*` external-webhook-ineligible events.

P3 adds no mutation event because history is read-only. Current/history payload bodies and encrypted cursors are not Audit/outbox payloads.

## 8. Commands, jobs and scheduled work

K5-P3 adds no Artisan command, queue job, scheduler entry or operator execution surface. K4 scheduled ingestion/maintenance remains unchanged.

Invitation retention/cleanup and broader capacity diagnostics remain P5 work.

## 9. Files, imports, exports and external dependencies

Controlled [CSV migration](../csv-migration.md) remains the material Kingdoms file contract. K5 adds no file/export contract or external provider dependency.

Invitation tokens and history cursors are first-party transient secrets/state, not external API credentials or public sharing links. Current/history facts are not exported as files or a public feed.

## 10. Failure, idempotency, versioning and compatibility

Token redemption is single-use; failed acceptance does not consume it. Terminal agreements do not reactivate through K5 actions.

Target grant is idempotent while active; removal terminates access and deliberate re-grant is required. Supported Kingdom drift terminalizes affected agreements and later return does not restore them.

Current/history accepted facts follow K3 non-invalidated capture-time ordering; source invalidation changes both projections immediately.

History uses encrypted target-bound keyset continuation with fixed snapshot and cumulative 250-record cap. Each continuation repeats live authorization.

P3 creates no public compatibility contract.

## 11. Explicit non-capabilities

P3 does not provide complete K5 source/recipient pages, arbitrary historical-window selection, player/roster sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, reshare, tenant directory/search, public API/webhook, scoring/ranking/recommendations or automatic decisions.

## 12. Focused contracts, evidence and related documentation

- [Shared intelligence](../shared-intelligence.md)
- [K5 Slice A validation](../product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5 Slice A security review](../security/kingdoms-shared-intelligence-foundation-security-review.md)
- [K5 Slice B validation](../product/kingdoms-shared-intelligence-slice-b-validation.md)
- [K5 Slice B security review](../security/kingdoms-shared-intelligence-current-facts-security-review.md)
- [K5 Slice C validation](../product/kingdoms-shared-intelligence-slice-c-validation.md)
- [K5 Slice C security review](../security/kingdoms-shared-intelligence-history-security-review.md)
- [K5 implementation plan](../product/kingdoms-shared-intelligence-implementation-plan.md)
- [Automated ingestion](../automated-ingestion.md)
- [CSV migration](../csv-migration.md)
- [Kingdoms domain](../README.md)
- [Integrations interfaces](../../integrations/interfaces/README.md)
- [Integrations webhooks](../../integrations/webhooks.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)

P3 runtime candidate `70739d320caab059d2102feda081be33754b77ec` passed Dependency Review `31564263865`, CodeQL `31564263863`, and CI `31564263891` with 443 tests / 10,086 assertions.
