# KINGDOMS-005 K5-P0 security and privacy review

[← Kingdoms security profile](README.md)

**Scope:** `KINGDOMS-005` / `K5-P0`  
**Status:** Candidate — planning only; no runtime capability exists  
**Reviewed contract:** [K5-P0 design decisions](../product/kingdoms-shared-intelligence-p0-decisions.md)

## 1. Review purpose

K5 creates the first deliberate authorization bridge between two platform Alliance tenants inside the Kingdoms domain. P0 therefore treats cross-tenant disclosure, consent, revocation and context drift as the primary security boundary.

The proposed design is acceptable for later sliced implementation only if sharing remains directional, two-party opt-in, same-Kingdom, per-target, read-only and restricted to explicitly safe canonical game-Alliance observation fields.

## 2. Primary assets at risk

The source Alliance owns high-value private intelligence around tracked game Alliances. K5 must prevent accidental disclosure of:

- tracking notes;
- diplomacy state/terms/history;
- diplomacy contacts/handles/notes;
- player/roster/snapshot data;
- transfer state;
- correction/invalidation rationale and actor attribution;
- K4 acquisition/adapter/subscription/batch/candidate/cursor/provenance internals;
- audit/outbox internals; and
- source credentials/raw responses/private free text.

The only initially shareable payload is the narrow member-safe game-Alliance factual observation projection locked by P0.

## 3. Cross-tenant authorization threat

The largest risk is treating global neutral `KingdomAlliance` identity as a permission bridge. It is not.

Every recipient read must begin from the active recipient Alliance, resolve an active/context-valid source→recipient sharing agreement, resolve an explicitly selected shared target, and only then reach source-owned tracking/accepted observations.

Submitted source IDs, tracked-alliance IDs, observation IDs or neutral IDs cannot independently authorize access. Cross-tenant object substitution must fail closed.

## 4. Consent-bootstrap threat

A public/searchable tenant directory would enlarge metadata exposure and enumeration risk solely to establish a share.

P0 instead uses an unguessable one-time invitation bearer secret. Security requirements are:

- cryptographically strong random token;
- only a cryptographic hash persisted;
- plaintext shown only at creation;
- bounded expiry;
- single-use redemption;
- recent-password-confirmed source creation and recipient redemption;
- no token in logs/audit/outbox URLs/referrers where avoidable; and
- token possession alone cannot expose data before an authorized recipient manager binds their active Alliance.

The invitation is therefore a consent bootstrap, not a reusable API credential.

## 5. Same-Kingdom isolation

K5 must not become a bridge between Kingdom contexts.

At acceptance and every read/mutation, source and recipient current `kingdom_id` values must equal the captured sharing Kingdom, and selected source tracking must also belong to that Kingdom.

If either Alliance drifts, access fails closed. P0 explicitly rejects silent retargeting or implicit reactivation after drift. A new deliberate agreement is required.

## 6. Data-minimization boundary

The safe shared projection is intentionally narrower than the source manager view.

Allowed data is limited to display/current factual game-Alliance observation fields, capture time, freshness and source Alliance identity needed for explainability. Even where a canonical observation originated from K4 machine ingestion, adapter/version/subscription/batch/record/hash provenance remains source-private.

K5 must project fields explicitly rather than serialize source models or reuse manager resource payloads wholesale.

## 7. Recipient non-ownership and mutation threat

Recipient access is read-only. K5 must not create a path for the recipient to:

- update/invalidate source observations;
- mutate source tracking/diplomacy/contact state;
- invoke K4 acquisition/replay;
- create or reactivate local tracking automatically;
- copy shared observations automatically into recipient canonical history;
- change transfer/roster state; or
- reshare source facts to another Alliance.

The recipient may independently use existing human workflows where authorized, but K5 itself cannot translate shared facts into business mutation authority.

## 8. Revocation and stale-cache threat

Source revocation, recipient departure, item removal or Kingdom drift must remove access immediately at the authorization layer.

Caching may never replace agreement/item/context checks. Cache keys must include recipient/share scope and invalidation must be conservative. If authorization state is uncertain, reads fail closed.

Retained audit/history metadata after revocation must not contain the shareable observation payload and cannot itself grant access.

## 9. Correction/invalidation leakage

Recipient history must be a projection over source observations that are currently accepted under K3 semantics.

When a source observation is invalidated, the recipient must stop seeing it as accepted intelligence. Private invalidation reason/actor must not cross the share. A corrected replacement may appear only as a new accepted source observation.

K5 must not materialize an immutable recipient copy that would preserve invalidated facts beyond source authorization/history rules.

## 10. Reshare and confused-deputy threat

Sharing is directional and non-transitive. A recipient cannot use a source share as a data source for another K5 share.

Share-item creation is source-owned only and must point to the source's own `TrackedKingdomAlliance`. Recipient-owned or received shared items cannot be selected as an upstream source.

This prevents K5 from becoming an uncontrolled information propagation graph.

## 11. Event, logging and observability boundary

Material consent/item mutations may create Audit/internal outbox evidence using safe IDs, states, timestamps and actor attribution.

Prohibited in event/log payloads:

- invitation plaintext;
- source observation payload bodies where not required;
- tracking/diplomacy/contact private text;
- K4 source/secret details; and
- another tenant's management metadata.

All K5 events remain `kingdoms.*` and external-webhook ineligible. No public API/webhook/feed is approved.

## 12. Retention/privacy boundary

K5 should persist consent and authorization metadata, not duplicate source observation history.

Invitation token hashes/expiry/use state require bounded retention after expiry/use. Revoked agreement/item metadata may be retained for auditability, but recipient access to source observations must be absent.

A future requirement for recipient-side export/materialization would require a separate privacy/retention design and is outside K5 initial scope.

## 13. Abuse cases explicitly rejected

P0 rejects:

- guessing tenant/tracking/observation IDs to cross Alliance boundaries;
- self-sharing to bypass manager presentation rules;
- using a token after redemption/expiry/revocation;
- replaying one invitation for multiple recipients;
- sharing across Kingdoms;
- wildcard share-all-current/future targets;
- recipient reshare;
- public tenant enumeration;
- exposing diplomacy/contact/roster/transfer/K4 internals;
- using shared intelligence to auto-drive decisions; and
- anonymous/public link access to observation data.

## 14. Required Slice A security evidence

Before K5-P1 can be accepted, tests must prove at minimum:

- invitation plaintext is never persisted;
- token hash matching is constant-time/standard-framework safe;
- token expiry and single-use behavior;
- self-share rejection;
- different-Kingdom rejection;
- source/recipient tenant ID substitution rejection;
- recent-password requirements on both consent sides;
- source revoke and recipient decline/leave;
- terminal agreement non-reactivation;
- no shared-observation route/query exists in Slice A; and
- audit/outbox data excludes invitation secret/private content.

## 15. P0 security decision

The K5 design is acceptable to proceed to protected P0 validation **only** with the constraints in this review and the K5-P0 decision record.

P0 approval authorizes agreement-foundation implementation only. It does not approve any shared observation disclosure until Slice B independently passes its tenant/privacy/security gate, and it does not approve player/roster, diplomacy/contact, transfer, cross-Kingdom, public API/webhook, reshare, scoring or automated-decision capability.