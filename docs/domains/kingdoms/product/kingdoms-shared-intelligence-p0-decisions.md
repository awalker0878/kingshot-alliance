# KINGDOMS-005 K5-P0 design decisions

[← KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)

**Scope ID:** `KINGDOMS-005`  
**Gate:** `K5-P0` — consent, tenancy, same-Kingdom, safe-field, revocation and reshare contract lock  
**Status:** **Candidate — no runtime impact**  
**Runtime impact:** None. This record authorizes later sliced implementation only after protected P0 validation.

## 1. Purpose

K5 introduces a deliberately narrow cross-tenant read path over source-owned canonical game-Alliance observations. P0 exists to prevent “shared intelligence” from becoming implicit tenant federation, bulk export, diplomacy leakage or automatic decision authority.

K1-K4 remain authoritative. K5 may add an explicit authorization bridge, but it does not change ownership of roster/history/transfer/diplomacy/contact/ingestion state.

## 2. Sharing direction and ownership

A share is always directional:

- one source platform `Alliance` owns the intelligence;
- one different recipient platform `Alliance` receives read access;
- reverse sharing requires a separate agreement; and
- no recipient may reshare source-owned facts through K5.

The source remains the sole owner of its `TrackedKingdomAlliance` and `KingdomAllianceObservation` rows. K5 does not duplicate those rows into recipient-owned canonical tables.

## 3. Two-party consent model

### 3.1 Source invitation

An authorized source manager creates a one-time invitation using `kingdoms.manage` plus recent password confirmation.

The invitation secret:

- is cryptographically random and unguessable;
- is displayed only at creation;
- is stored only as a cryptographic hash;
- is bounded by expiry;
- is single-use;
- is excluded from logs, audit/outbox and ordinary diagnostics; and
- conveys only the ability to attempt acceptance, not immediate data access.

### 3.2 Recipient acceptance

An authorized manager redeems the invitation under their own active Alliance context using `kingdoms.manage` plus recent password confirmation.

Acceptance binds the recipient Alliance explicitly. It must reject:

- the source Alliance itself;
- a recipient in a different current Kingdom;
- an expired/used/revoked invitation;
- a stale/nonexistent source Alliance; and
- replay by another tenant after successful redemption.

### 3.3 Decline and revoke

The recipient may decline/leave; the source may revoke. These are access-reducing actions and must remain available even when ordinary sharing reads are blocked by drift/staleness.

Historical consent metadata may be retained for auditability, but revoked/declined agreements grant zero shared-data access.

## 4. Agreement lifecycle

The initial persisted lifecycle may normalize names, but semantics must distinguish:

- pending invitation;
- active accepted agreement;
- recipient-declined/left terminal state; and
- source-revoked terminal state.

Expired pending invitations are unusable. Implementation may persist an explicit expired state or derive expiry from `expires_at`, but expiry can never activate or preserve access.

Terminal agreements are not reactivated. A new collaboration after revoke/decline requires a new invitation/agreement.

## 5. Same-Kingdom boundary

A sharing agreement captures one `Kingdom`.

At acceptance:

- source and recipient Alliances must have the same current `kingdom_id`;
- that Kingdom becomes the captured sharing context.

At every shared read/mutation:

- source current Kingdom must equal captured Kingdom;
- recipient current Kingdom must equal captured Kingdom; and
- selected source tracking targets must belong to captured Kingdom.

If either Alliance changes Kingdom, access fails closed. The agreement is not silently retargeted and does not resume implicitly after context drift. Recovery requires a new deliberate agreement under the then-current shared Kingdom.

Implementation may mark a drifted agreement invalid/revoked when detected because that reduces access, but it may never silently restore it.

## 6. No tenant-directory requirement

K5 does not introduce a public/member searchable platform Alliance directory.

The invitation bearer token is the initial discovery/handshake mechanism. Before redemption, the recipient need not know an internal source tenant ID. After deliberate redemption, source and recipient may see the counterpart Alliance identity needed to explain/manage the agreement.

K5 must not expose manager rosters, member directories, tenant UUID enumeration or private tenant metadata as part of discovery.

## 7. Explicit shared-target selection

An active agreement alone shares **no observation data**.

The source manager must explicitly select each source-owned active `TrackedKingdomAlliance` target to share.

A selected item must:

- belong to the source Alliance;
- belong to captured Kingdom;
- reference the neutral game-side `KingdomAlliance` normally; and
- remain independently removable without terminating the whole agreement.

No wildcard “share all current/future tracked alliances” mode is approved in the initial increment.

## 8. Safe shared data contract

The initial recipient member-safe projection may include only:

- neutral/current game-Alliance display name/tag needed for context;
- accepted observed name/tag;
- accepted power when present;
- accepted member count when present;
- observation capture time;
- derived freshness/current-stale-missing presentation; and
- source platform Alliance identity needed to explain origin.

Bounded accepted history may be added in Slice C under the same field restrictions.

The recipient does not receive source `TrackedKingdomAlliance` manager notes or source tenant-internal IDs except an opaque sharing/item identifier required by the recipient UI.

## 9. Explicitly excluded source data

K5 may not expose through the share:

- player roster/snapshot data;
- transfer planning/readiness/completion state;
- diplomacy state/terms/rationale/review/expiry/transition history;
- diplomacy contacts/handles/notes/verification;
- source tracking notes;
- correction/invalidation reasons/actors;
- observation human actor IDs;
- K4 adapter/subscription/batch/candidate/cursor/source-record/hash provenance;
- raw external responses or credentials;
- audit/outbox internals;
- private free text; or
- scores/ranks/recommendations/predictions.

A machine-ingested K4-promoted observation may participate only because it is an accepted canonical K3 observation. Its K4 operational/source provenance remains source-private.

## 10. Recipient authorization and non-ownership

Once an agreement/item is active and context-valid:

- recipient managers/members with `alliance.view` may read the safe shared projection;
- agreement/invitation/item management requires `kingdoms.manage` plus recent password confirmation; and
- every recipient read starts from active recipient Alliance ownership and resolves the source only through the accepted agreement/item.

The recipient cannot:

- edit/invalidate source observations;
- change source tracking/diplomacy/contact state;
- request K4 replay/acquisition;
- automatically copy shared facts into recipient canonical observation history;
- automatically start local tracking;
- create transfer/diplomacy decisions from the share; or
- reshare source facts to a third Alliance.

## 11. Correction and invalidation semantics

Shared reads are live authorization projections over accepted source canonical observations.

If a source observation becomes invalidated:

- it ceases to appear as accepted shared intelligence;
- any corrected replacement appears only if it is itself accepted and within the selected target/history contract; and
- the recipient does not receive the private correction reason/actor.

K5 does not materialize an immutable recipient copy that defeats source correction or revocation.

## 12. History and freshness principles

Shared history is bounded/paginated and capture-time ordered under K3 semantics.

Only accepted non-invalidated source observations participate. Missing values remain missing; zero remains a recorded zero. Freshness is descriptive only and cannot auto-trigger diplomacy, transfer, roster or other decisions.

The exact history page/window bound is a Slice C implementation detail, but unbounded source history export is prohibited.

## 13. Revocation and drift semantics

Access checks are authoritative at read time.

Source revocation, recipient departure, shared-item removal or Kingdom-context invalidation must immediately prevent both current and history reads even if caches/jobs are stale.

K5 may use caches for performance only when cache keys/invalidations preserve source→recipient authorization. A cache hit is never authorization.

## 14. Audit and internal event contract

Material human actions create attributable audit/internal outbox evidence using safe metadata only, including concepts equivalent to:

- share invitation created;
- share accepted/declined/revoked;
- shared target added/removed; and
- context invalidated where persisted.

Invitation secrets, diplomacy/contact data, manager notes, observation payload bodies and K4 ingestion provenance are excluded.

All event names remain under `kingdoms.*` and therefore external-webhook ineligible under the accepted Integrations exclusion.

## 15. Persistence and retention principles

K5 may persist:

- source/recipient/captured-Kingdom agreement identity/state;
- invitation token hash plus bounded expiry/use metadata;
- explicit shared-target relationships;
- actor/timestamp fields needed for consent/history; and
- safe internal correlation IDs.

K5 should not persist recipient copies of source observation payload/history.

Invitation token material must be bounded/removed after expiry/use under Slice E retention. Agreement/item history may be retained for auditability after revocation without granting data access.

## 16. Query and performance principles

Cross-tenant reads must be recipient-first and authorization-joined:

1. resolve active recipient Alliance;
2. resolve active/context-valid agreement for that recipient;
3. resolve explicitly selected item;
4. resolve source-owned tracking/accepted observations beneath that item.

Global neutral `KingdomAlliance` identity may be joined for safe reference fields but never acts as the authorization bridge.

Recipient lists/history must be bounded and N+1 resistant. Realistic-volume gates are owned by Slice E.

## 17. Public integration boundary

K5 creates no:

- public Kingdoms API scope;
- inbound/public sharing endpoint;
- public webhook event;
- anonymous sharing URL/feed;
- public Alliance directory; or
- external token/API credential for machine-to-machine access.

The invitation token is a human consent bootstrap secret, not a reusable public API credential.

## 18. Explicit non-capabilities

K5-P0 does not approve or reserve hidden runtime placeholders for:

- roster/player sharing;
- transfer-plan sharing/automation;
- diplomacy/contact sharing/automation;
- cross-Kingdom sharing;
- transitive/recursive reshare;
- bulk tenant export;
- public directories/APIs/webhooks;
- source acquisition/scraping/OCR/bots;
- scoring/ranking/threat/desirability/risk models;
- battle prediction;
- automated recommendations/negotiation; or
- AI-generated player/alliance management decisions.

## 19. P0 exit decision

K5-P0 is Complete only when this decision record, increment scope, implementation plan and P0 security/privacy review agree; documentation clearly labels K5 as no-runtime planning; no living capability claims K5 runtime exists; and the exact containing P0 evidence/status head passes Dependency Review, CodeQL and full CI.

P0 acceptance authorizes Slice A only.