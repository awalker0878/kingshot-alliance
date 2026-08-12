# KINGDOMS-005 K5-P0 exit report

[← KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)

**Scope:** `KINGDOMS-005`  
**Gate:** `K5-P0` — consent, tenancy, same-Kingdom, safe-field, revocation and reshare contract lock  
**Status:** **Complete**  
**Validated P0 candidate:** `d9e05fd06bd08050e5489598406cfb556d5bc0ac`  
**Runtime impact:** None — P0 authorizes Slice A agreement-foundation implementation only

## 1. Exit decision

`K5-P0` is **Complete**. The product scope, gated implementation plan, normative design decisions, P0 security/privacy review and domain/security navigation agree on one no-runtime contract for future opt-in shared Kingdom intelligence.

P0 authorizes only `K5-P1` / Slice A: the directional two-party sharing-agreement and invitation-consent foundation. It does **not** authorize observation disclosure, target sharing, recipient shared-intelligence reads or any later K5 capability.

## 2. Locked sharing boundary

The initial K5 design is:

- directional source Alliance → recipient Alliance;
- two-party manager opt-in;
- same-current-Kingdom only;
- one-time invitation bootstrap rather than searchable tenant enumeration;
- no data shared merely because an agreement exists;
- explicit source selection of each tracked game Alliance before any later disclosure;
- recipient read-only access only through the accepted agreement/item authorization bridge;
- source-owned canonical K3 observations remain source-owned and are not copied into recipient canonical history;
- source correction/invalidation remains authoritative;
- revocation, recipient departure, item removal and Kingdom drift fail closed; and
- no transitive or recursive reshare.

## 3. Consent and invitation-secret boundary

P0 locks the consent bootstrap as an unguessable one-time bearer token created by an authorized source manager and redeemed by an authorized recipient manager.

Only a cryptographic hash may be persisted. The plaintext token is shown only at creation, has bounded expiry and single-use semantics, and must be excluded from logs, Audit and outbox metadata.

Token possession alone never exposes intelligence. Redemption binds the active recipient Alliance only after self-share, source validity and same-Kingdom checks pass.

The repository already has an established invitation-token primitive using 32 cryptographically random bytes represented as hex and SHA-256 hashing; Slice A may reuse that pattern rather than introducing weaker bespoke token logic.

## 4. Locked safe-data classification

A later accepted sharing slice may expose only bounded member-safe game-Alliance factual observation fields: neutral/current display name/tag as needed, accepted observed name/tag, accepted power/member count when present, capture time, freshness/current-stale-missing presentation and source Alliance identity needed for explainability.

P0 explicitly excludes player/roster/snapshot data, transfers, diplomacy state/terms/history, diplomacy contacts/handles/notes, tracking notes, correction reasons/actors, K4 adapter/subscription/batch/candidate/cursor/source provenance, raw responses/secrets, audit/outbox internals, private free text, scores/ranks/recommendations and automated decisions.

A K4-promoted observation can later participate only because it is accepted canonical K3 history; K4 operational/source provenance remains source-private.

## 5. Same-Kingdom and ownership boundary

An accepted future agreement captures one Kingdom. Source and recipient current Kingdom must match that context at activation and every authorized shared read/mutation. Selected source tracking must also belong to that Kingdom.

If either Alliance changes Kingdom, access fails closed. The agreement cannot silently retarget or implicitly reactivate. Recovery requires a new deliberate agreement under a then-valid shared Kingdom.

Global neutral `KingdomAlliance` identity remains reference data only and cannot authorize cross-tenant access.

## 6. Recipient non-ownership boundary

The recipient cannot edit/invalidate source observations, mutate source tracking/diplomacy/contact state, invoke K4 acquisition/replay, automatically create local tracking, automatically copy shared facts into recipient canonical history, drive transfer/roster decisions through K5, or reshare source facts to another Alliance.

Future recipient queries must start from active recipient Alliance ownership, then resolve the active/context-valid agreement and explicit shared item before reaching source-owned accepted observations.

## 7. Public integration and event boundary

P0 creates no runtime route, UI, schema, job, public API, inbound endpoint, anonymous feed or webhook contract.

Any future K5 events remain under `kingdoms.*` and therefore external-webhook ineligible under the accepted Integrations boundary. Consent/item audit evidence may contain safe IDs/states/timestamps only; invitation plaintext and private/source payloads are excluded.

## 8. Retention and privacy boundary

K5 should persist the minimum consent/grant metadata required for authorization and historical explanation, not recipient copies of source observation history.

Invitation hash/expiry/use material requires bounded retention after use/expiry. Revoked agreement/item metadata may remain for audit history, but it grants no shared-data access and must not retain duplicated source observation payloads merely for sharing.

## 9. Protected validation evidence

Exact validated P0 candidate:

`d9e05fd06bd08050e5489598406cfb556d5bc0ac`

Protected runs:

- Dependency Review `31557697685` — **success**;
- CodeQL `31557697793` — **success**;
- CI `31557697725` — **success**.

CI evidence:

- PHP 8.5.9;
- Composer manifest/lock validation — success;
- Composer audit — no security vulnerability advisories;
- clean PostgreSQL migrations through `2026_08_11_220000_add_ingestion_scheduling` — success;
- Pint — **529 files**;
- PHPStan/Larastan — **374/374, 0 errors**;
- ParaTest/PHPUnit — **429 tests, 9,809 assertions**;
- frontend dependency audit/checks/build — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image vulnerability scan — success; and
- staging cleanup — success.

No K5 runtime migration/model/action/route/UI exists in this candidate, which is the expected P0 result.

## 10. Slice A authorization

P0 completion authorizes `K5-P1` / Slice A to implement only:

- directional sharing-agreement/invitation persistence;
- hash-only invitation token storage with expiry/single-use semantics;
- source invitation creation;
- recipient redemption/acceptance;
- recipient decline/leave;
- source revocation;
- self-share and different-Kingdom rejection;
- captured-Kingdom context;
- manager-only consent state; and
- attributable safe Audit/internal-outbox evidence where required.

Slice A must **not** expose any shared observation/current/history read path, create shared targets, create a tenant directory, add reshare, or widen the locked data classes.

Actual Slice A work may begin only after the exact containing evidence/status head that records P0 Complete / P1 Current also passes Dependency Review, CodeQL and full CI.