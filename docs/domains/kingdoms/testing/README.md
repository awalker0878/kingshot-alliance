# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P1 consent foundation validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral identity, tenant-owned Kingdoms workflows, K4 ingestion isolation/idempotency, K5 consent isolation and explicit public/decision-automation non-capabilities

**P5 evidence decision:** Current proof is mapped by executable suite/evidence class and immutable candidate identities; K5 adds focused cross-tenant consent evidence without replacing K1–K4 accepted evidence.

## 1. Critical claims and validation ownership

Kingdoms validation protects identity/tenant separation, append history, human-only governance, K4 source boundaries and K5's new two-party sharing-consent seam.

At P1, K5 must prove only consent/agreement state exists and that no observation/current/history sharing path has accidentally appeared.

## 2. Executable suite mapping

All six PHPUnit groups remain material: Architecture, Feature, Integration, Performance, TenantIsolation and Unit. K5-P1 adds Feature/migration/tenant-boundary evidence to accepted K1–K4 suites.

Architecture protects ownership/non-capabilities/public exposure; Feature protects consent workflows; Integration protects Audit/outbox and migration behavior; TenantIsolation protects source/recipient/share-ID boundaries; existing Performance/Unit suites remain additive.

## 3. Architecture and domain-boundary validation

Existing architecture guards continue to enforce no public Kingdoms API/wildcard webhook, stable-ID identity boundaries and documentation conventions.

P1 adds only password-confirmed POST consent routes. Focused tests assert there is no sharing index or shared-observation read route and no P1 target-sharing table.

## 4. Authorization, tenancy, security and privacy validation

`KingdomIntelligenceSharingFoundationTest` proves recent password confirmation and `kingdoms.manage`, source/recipient tenant scoping, same-Kingdom acceptance, self-share rejection, unrelated-tenant revoke/leave rejection, hash-only token storage and no plaintext token in outbox payloads.

Acceptance uses the active recipient Alliance and captured source Kingdom. Different-Kingdom acceptance fails without consuming the token. Access-reducing decline/revoke/leave remain available when context drifts.

## 5. Feature, interface and integration validation

Focused P1 scenarios cover invitation creation, accept, decline, revoke and leave; token expiry/single use; duplicate active directional agreement rejection; terminal idempotency; source-side acceptance actor privacy; absence of observation/payload/K4 fields on the consent table; and no shared-data GET interface.

Audit/outbox assertions verify safe internal consent evidence while keeping invitation plaintext out of durable event payloads.

## 6. Idempotency, concurrency and asynchronous validation

Successful acceptance consumes a pending invitation once. Exact token replay fails. Duplicate active directional agreement creation is rejected.

Acceptance locks the share and source/recipient Alliance rows in deterministic ID order; revoke/leave use tenant-scoped row locks. Terminal declined/revoked state never reactivates through P1 actions.

P1 adds no asynchronous job/cache authorization path; database/domain checks remain authoritative.

## 7. Persistence, migration, rollback and recovery evidence

`2026_08_12_010000_create_kingdom_intelligence_shares` is included in clean PostgreSQL CI and in the full Kingdoms migration backfill/round-trip test.

The round trip drops the K5 consent table before the K4/K3/K2/K1 dependency chain and reapplies it after K4 scheduling, then asserts token-hash, recipient-Alliance and captured-Kingdom columns exist.

Backup/restore passed with the new consent table. P1 stores no shared observation payload/history, so recovery cannot recreate a recipient data copy that never existed.

## 8. Performance, query and capacity evidence

Existing K1–K4 query gates remain accepted. P1 is mutation-only and adds no recipient shared-data list/history query, so it establishes no new read-performance claim.

P2 must add bounded current-fact query evidence when the first recipient projection is implemented; realistic-volume cross-tenant capacity hardening remains owned by P5.

## 9. Accessibility and frontend evidence

P1 adds no new Vue/page surface. Consent endpoints are mutation interfaces only, so no new source-level accessibility artifact exists yet.

The full candidate still passed frontend dependency audit, ESLint/Prettier/Vue-TypeScript checks and production frontend build. P4 remains responsible for complete source/recipient first-party UX/accessibility.

## 10. Historical accepted evidence

K1–K4 historical accepted SHAs/run IDs remain immutable evidence.

K5-P0 candidate `d9e05fd06bd08050e5489598406cfb556d5bc0ac` passed DR `31557697685`, CodeQL `31557697793`, CI `31557697725` with 429 tests / 9,809 assertions.

K5-P1 runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed:

- Dependency Review `31559012856`;
- CodeQL `31559012854`;
- CI `31559012861`;
- Pint — 541 files;
- PHPStan/Larastan — 384/384, zero errors;
- ParaTest/PHPUnit — 434 tests / 9,911 assertions;
- frontend/build and clean migrations — success; and
- immutable image, staging, backup/restore, scan and cleanup — success.

## 11. Evidence identity, retention and supersession

Historical accepted evidence remains immutable. Current behavior follows current code/tests/living contracts.

K5 P1 runtime acceptance is attached to the exact implementation candidate above. The exact containing evidence/status head that records P1 Complete / P2 Current must independently pass protected gates before P2 implementation begins.

Future token-retention behavior belongs to P5 and must not erase acceptance evidence or canonical K3 observations.

## 12. Gaps, non-capabilities and related documentation

P1 does not validate shared target selection, recipient current/history projection, correction propagation, shared-data query performance, recipient member UX, reshare prevention at a data path, or retention cleanup. Those remain P2–P5 work.

P1 still provides no player/roster sharing, transfers, diplomacy/contact sharing, cross-Kingdom sharing, public APIs/webhooks, tenant directory, scoring/ranking/recommendations or automatic decisions.

Related: [Shared intelligence](../shared-intelligence.md), [Slice A validation](../product/kingdoms-shared-intelligence-slice-a-validation.md), [Slice A security review](../security/kingdoms-shared-intelligence-foundation-security-review.md), [Security profile](../security/README.md), [Operations profile](../operations/README.md), [Interfaces](../interfaces/README.md), [testing/evidence standard](../../../product/testing-evidence-standard.md), [P5 testing/evidence coverage matrix](../../../product/testing-evidence-coverage-matrix.md).