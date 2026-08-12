# Kingdoms interfaces

[← Kingdoms domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P1 consent foundation validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary boundary:** Authenticated Alliance Kingdoms workspaces, K4 ingestion control/operations, K5 first-party consent mutations, and internal-only `kingdoms.*` events

**P4 inventory decision:** Existing accepted Kingdoms capability contracts remain the focused contract authority; K5 extends this profile without changing the frozen P4 interface-inventory convention or creating a public Kingdoms API.

## 1. Boundary purpose and ownership

Kingdoms owns first-party neutral-game identity, roster/history/intelligence, transfer, game-Alliance tracking/observations/diplomacy/contacts, K4 ingestion, and now K5 directional sharing-consent state.

K5-P1 adds only first-party consent mutations. No shared-target/current/history read interface exists yet.

## 2. Surface inventory

Existing K1–K4 routes remain unchanged. P1 adds password-confirmed authenticated routes:

- `POST /alliance/kingdom-sharing/invitations` — source creates one invitation and receives `{shareId, token}` once;
- `POST /alliance/kingdom-sharing/invitations/accept` — recipient accepts a token;
- `POST /alliance/kingdom-sharing/invitations/decline` — recipient declines a token;
- `POST /alliance/kingdom-sharing/{share}/revoke` — source revokes its pending/active share; and
- `POST /alliance/kingdom-sharing/{share}/leave` — active recipient leaves.

There is no K5 GET/list/current/history route, tenant-directory endpoint, target-selection endpoint, public API or callback route in P1.

## 3. Callers, authorization and tenancy

Consent mutations require active Alliance context, recent password confirmation and domain-level `kingdoms.manage` authorization.

Source revoke resolves the submitted share beneath `source_alliance_id`; recipient leave resolves beneath `recipient_alliance_id`. Acceptance binds the active recipient Alliance only after locking source/recipient Alliances and validating the captured Kingdom. Neutral Kingdom/game-Alliance identity never authorizes a K5 operation.

## 4. Input and validation contracts

Invitation acceptance/decline accepts only a required 64-character lowercase hexadecimal token. The token is hashed before lookup.

Acceptance rejects invalid/expired/used token, self-share, different-current-Kingdom context and duplicate active directional agreement. Invitation TTL defaults to 72 hours and repository configuration clamps it to 1–168 hours.

P1 accepts no target ID, observation ID/payload, source endpoint, roster/diplomacy/contact data or arbitrary cross-tenant identifier for data disclosure.

## 5. Output and disclosure contracts

Invitation creation returns only the new share ID plus plaintext token in the creation response. Plaintext is not persisted or placed in Audit/outbox metadata.

Accept/decline/revoke/leave return redirect/status outcomes only. P1 provides no recipient shared-intelligence payload.

The source-side acceptance Audit record intentionally does not expose the recipient manager's User ID; recipient-side Audit remains attributable within the recipient tenant.

## 6. Internal actions, queries and services

P1 internal contracts are:

- `CreateKingdomIntelligenceShareInvitation`;
- `AcceptKingdomIntelligenceShareInvitation`;
- `DeclineKingdomIntelligenceShareInvitation`;
- `RevokeKingdomIntelligenceShare`;
- `LeaveKingdomIntelligenceShare`; and
- `KingdomIntelligenceShareTokenService`.

There is no P1 shared-target or shared-observation query/service.

## 7. Events, outbox and cross-domain consumers

P1 consent events are `kingdoms.shared_intelligence_invitation_created`, `...accepted`, `...declined`, `...revoked`, and `...left`.

They remain internal `kingdoms.*` events and external-webhook ineligible. Payloads use safe share/source/recipient/Kingdom/state/timing metadata only; invitation plaintext and observation/private/K4 payload data are excluded.

## 8. Commands, jobs and scheduled work

P1 adds no Artisan command, queue job, scheduler entry or operator execution surface. K4 scheduled ingestion/maintenance remains unchanged.

Invitation retention/cleanup is intentionally deferred to K5-P5 rather than introducing an unreviewed background task in P1.

## 9. Files, imports, exports and external dependencies

The controlled [CSV migration](../csv-migration.md) remains the material Kingdoms file contract. K5-P1 adds no file contract or external provider dependency.

Invitation tokens are first-party human-consent bootstrap secrets, not external API credentials.

## 10. Failure, idempotency, versioning and compatibility

Token redemption is single-use. Failed acceptance does not consume a token because validation and state mutation are transactional. Terminal declined/revoked states do not reactivate through P1 actions.

Source revoke/recipient leave remain available after Kingdom drift because they reduce access. Migration rollback/reapply now includes the K5 consent table as the newest Kingdoms dependency.

P1 creates no public compatibility contract.

## 11. Explicit non-capabilities

P1 does not provide shared target selection, recipient observation/current/history reads, player/roster sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, reshare, tenant directory/search, public API/webhook, scoring/ranking/recommendations or automatic decisions.

## 12. Focused contracts, evidence and related documentation

- [Shared intelligence](../shared-intelligence.md)
- [K5 Slice A validation](../product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5 Slice A security review](../security/kingdoms-shared-intelligence-foundation-security-review.md)
- [K5 implementation plan](../product/kingdoms-shared-intelligence-implementation-plan.md)
- [Automated ingestion](../automated-ingestion.md)
- [CSV migration](../csv-migration.md)
- [Kingdoms domain](../README.md)
- [Integrations interfaces](../../integrations/interfaces/README.md)
- [Integrations webhooks](../../integrations/webhooks.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)

P1 runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and CI `31559012861` with 434 tests / 9,911 assertions.