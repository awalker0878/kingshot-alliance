# Kingdoms interfaces

[← Kingdoms domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P2 current-fact sharing validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary boundary:** Authenticated Alliance Kingdoms workspaces, K4 ingestion control/operations, K5 first-party consent/target mutations, bounded internal current-fact query, and internal-only `kingdoms.*` events

**P4 inventory decision:** Existing accepted Kingdoms capability contracts remain the focused contract authority; K5 extends this profile without changing the frozen P4 interface-inventory convention or creating a public Kingdoms API.

## 1. Boundary purpose and ownership

Kingdoms owns first-party neutral-game identity, roster/history/intelligence, transfer, game-Alliance tracking/observations/diplomacy/contacts, K4 ingestion, and K5 directional consent/target/current-fact sharing.

P2 adds explicit source target mutations and an internal recipient-safe current projection. Bounded shared history and complete K5 pages do not exist yet.

## 2. Surface inventory

Existing K1–K4 routes remain unchanged. K5 password-confirmed authenticated mutation routes are:

- `POST /alliance/kingdom-sharing/invitations` — source creates invitation and receives `{shareId, token}` once;
- `POST /alliance/kingdom-sharing/invitations/accept` — recipient accepts token;
- `POST /alliance/kingdom-sharing/invitations/decline` — recipient declines token;
- `POST /alliance/kingdom-sharing/{share}/revoke` — source revokes pending/active share;
- `POST /alliance/kingdom-sharing/{share}/leave` — active recipient leaves;
- `POST /alliance/kingdom-sharing/{share}/targets/{tracking}` — source explicitly grants one source-owned tracking target; and
- `POST /alliance/kingdom-sharing/{share}/targets/{target}/remove` — source removes one explicit target grant.

P2 adds no K5 GET/current/history route, public API/callback, tenant directory or complete sharing page surface.

## 3. Callers, authorization and tenancy

Consent/target mutations require active Alliance context, recent password confirmation and domain-level `kingdoms.manage`.

Source mutations resolve shares beneath `source_alliance_id`; target grant resolves tracking beneath the same source Alliance. Recipient current facts resolve only from the active recipient Alliance through an active/context-valid agreement and explicit active grant.

Neutral Kingdom/game-Alliance identity never authorizes K5 access.

## 4. Input and validation contracts

Invitation accept/decline accepts a required 64-character lowercase hexadecimal token; lookup uses its hash.

Acceptance rejects invalid/expired/used token, self-share, different-current-Kingdom context and duplicate active agreement.

P2 target grant accepts route share/tracking IDs only after source-tenant re-resolution and rejects terminal/stale agreements, inactive/different-Kingdom participants, inactive/non-source tracking and captured-Kingdom mismatch. Removal re-resolves target beneath the source-owned share.

No arbitrary observation payload, endpoint, credential, roster/diplomacy/contact data or recipient-supplied source fact enters P2 sharing.

## 5. Output and disclosure contracts

Invitation creation returns share ID plus plaintext token once. Consent/target mutations return redirect/status outcomes only.

`SharedKingdomIntelligenceCurrentQuery` is the P2 internal data interface and is bounded to 250 rows. Each row includes only:

- `shareTargetId`;
- source Alliance `{id,name}`;
- game Alliance `{name,tag}`;
- `freshness` (`current|stale|missing`); and
- latest accepted observation `{observedName,observedTag,power,memberCount,capturedAt}` or null.

It excludes source tracking/stable game IDs, private/K4 fields and history.

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
- `KingdomIntelligenceShareTokenService`; and
- `SharedKingdomIntelligenceCurrentQuery`.

There is no P2 shared-history query/service.

## 7. Events, outbox and cross-domain consumers

P1 consent events remain internal. P2 adds `kingdoms.shared_intelligence_target_shared`, `kingdoms.shared_intelligence_target_removed`, and `kingdoms.shared_intelligence_context_invalidated`.

All remain `kingdoms.*` external-webhook ineligible. Payloads use safe share/target/source/recipient/Kingdom/state/timing/reason metadata; invitation plaintext and shared observation/private/K4 payload bodies are excluded.

## 8. Commands, jobs and scheduled work

K5-P2 adds no Artisan command, queue job, scheduler entry or operator execution surface. K4 scheduled ingestion/maintenance remains unchanged.

Invitation retention/cleanup and broader capacity diagnostics remain P5 work.

## 9. Files, imports, exports and external dependencies

Controlled [CSV migration](../csv-migration.md) remains the material Kingdoms file contract. K5 adds no file contract or external provider dependency.

Invitation tokens are first-party consent-bootstrap secrets, not external API credentials. Current shared facts are not exported as files or a public feed.

## 10. Failure, idempotency, versioning and compatibility

Token redemption is single-use; failed acceptance does not consume it. Terminal agreements do not reactivate through K5 actions.

Target grant is idempotent while active; removal terminates access and deliberate re-grant is required. Supported Kingdom drift terminalizes affected agreements and later return does not restore them.

Latest accepted current fact follows K3 non-invalidated capture-time ordering; source invalidation changes the projection immediately.

P2 creates no public compatibility contract.

## 11. Explicit non-capabilities

P2 does not provide bounded shared history, complete K5 sharing pages, player/roster sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, reshare, tenant directory/search, public API/webhook, scoring/ranking/recommendations or automatic decisions.

## 12. Focused contracts, evidence and related documentation

- [Shared intelligence](../shared-intelligence.md)
- [K5 Slice A validation](../product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5 Slice A security review](../security/kingdoms-shared-intelligence-foundation-security-review.md)
- [K5 Slice B validation](../product/kingdoms-shared-intelligence-slice-b-validation.md)
- [K5 Slice B security review](../security/kingdoms-shared-intelligence-current-facts-security-review.md)
- [K5 implementation plan](../product/kingdoms-shared-intelligence-implementation-plan.md)
- [Automated ingestion](../automated-ingestion.md)
- [CSV migration](../csv-migration.md)
- [Kingdoms domain](../README.md)
- [Integrations interfaces](../../integrations/interfaces/README.md)
- [Integrations webhooks](../../integrations/webhooks.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)

P2 runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed Dependency Review `31562753429`, CodeQL `31562753422`, and CI `31562753430` with 440 tests / 10,025 assertions.
