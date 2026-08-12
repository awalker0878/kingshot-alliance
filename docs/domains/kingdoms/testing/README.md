# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P2 current-fact sharing validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral identity, tenant-owned Kingdoms workflows, K4 ingestion isolation/idempotency, K5 consent/grant/current-fact isolation and explicit public/decision-automation non-capabilities

**P5 evidence decision:** Current proof is mapped by executable suite/evidence class and immutable candidate identities; K5 adds focused cross-tenant evidence without replacing K1–K4 accepted evidence.

## 1. Critical claims and validation ownership

Kingdoms validation protects identity/tenant separation, append history, human-only governance, K4 source boundaries and K5's explicit cross-tenant consent/grant/read seam.

P2 must prove only explicitly granted safe current facts cross tenants, source canonical ownership remains intact, recipient reads do not copy or reshare facts, and removal/revoke/drift immediately fail closed. Bounded shared history remains outside current evidence.

## 2. Executable suite mapping

All six PHPUnit groups remain material: Architecture, Feature, Integration, Performance, TenantIsolation and Unit. K5-P2 adds feature/migration/tenant/query evidence to accepted K1–K4 and P1 suites.

Architecture protects ownership/non-capabilities/public exposure; Feature protects consent/grant/current workflows; Integration protects Audit/outbox/migrations; TenantIsolation protects source/recipient/grant boundaries; focused query assertions protect bounded current projection behavior.

## 3. Architecture and domain-boundary validation

Existing architecture guards continue to enforce no public Kingdoms API/wildcard webhook, stable-ID identity boundaries and documentation conventions.

K5 adds password-confirmed mutation routes only. Focused tests assert there is no K5 current/history GET route. P2's recipient current projection is an internal query, not a new public interface.

P1's historical no-sharing invariant is retained correctly under P2: invitation creation alone creates zero target grants and no observation data is disclosed.

## 4. Authorization, tenancy, security and privacy validation

`KingdomIntelligenceSharingFoundationTest` continues to prove password/permission, hash-only token, same-Kingdom consent, tenant scoping and token secrecy.

`KingdomSharedIntelligenceCurrentFactsTest` proves source-only target mutation, recipient-first visibility, explicit-target-only sharing, safe-field whitelisting, same-Kingdom/context checks, target removal/revocation/drift failure and no access resume after returning to the captured Kingdom.

`KingdomSharedIntelligenceIsolationTest` proves recipient current reads create no local tracking/observation copy, received source tracking cannot be re-granted through an outbound share, and missing observation remains missing rather than zero.

## 5. Feature, interface and integration validation

Focused P2 scenarios cover:

- explicit source target grant/removal;
- unshared source target absence;
- safe current projection shape;
- latest accepted observation selection;
- source invalidation fallback without private reason disclosure;
- current/stale/missing freshness;
- no copy/no reshare;
- unrelated-tenant share/tracking/target substitution rejection;
- supported Kingdom drift terminalization and no implicit reactivation;
- internal target/context Audit/outbox evidence; and
- no current/history public GET surface.

## 6. Idempotency, concurrency and asynchronous validation

P1 consent token/idempotency behavior remains protected. P2 active grant re-add is idempotent; removed state requires deliberate re-grant.

Acceptance/grant locking aligns to deterministic Alliance(s) → share → target ordering where Kingdom drift can race. Supported Kingdom changes terminalize affected agreements inside the same transaction.

P2 adds no asynchronous job/cache authorization path; database/domain checks remain authoritative.

## 7. Persistence, migration, rollback and recovery evidence

Both K5 migrations are included in clean PostgreSQL CI.

The full Kingdoms round trip drops `kingdom_intelligence_share_targets` before the parent K5 share and older K3/K1 dependencies, then reapplies it after the parent share. The focused K3 round trip also temporarily drops/reapplies the target table because of its FK to `tracked_kingdom_alliances`.

Backup/restore passed with K5 consent/grant state. Source observations remain canonical and no recipient observation history copy exists to restore.

## 8. Performance, query and capacity evidence

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250.

A focused fixture with 12 explicit targets asserts the current projection uses no more than two SELECT queries while returning all 12 authorized rows. This proves bounded/no-N+1 current behavior for the slice, not a production throughput SLO.

P3 must establish bounded history query behavior; realistic-volume current/history capacity remains P5 work.

## 9. Accessibility and frontend evidence

P2 adds no new K5 Vue/page surface; target routes are mutation interfaces and the recipient current projection is internal. Full source/recipient first-party UX/accessibility remains P4.

The P2 candidate still passed frontend dependency audit, ESLint/Prettier/Vue-TypeScript checks and production frontend build.

## 10. Historical accepted evidence

K1–K4 historical accepted SHAs/run IDs remain immutable evidence.

K5-P0 candidate `d9e05fd06bd08050e5489598406cfb556d5bc0ac` passed DR `31557697685`, CodeQL `31557697793`, CI `31557697725` with 429 tests / 9,809 assertions.

K5-P1 candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed DR `31559012856`, CodeQL `31559012854`, CI `31559012861` with 434 tests / 9,911 assertions.

K5-P2 runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed:

- Dependency Review `31562753429`;
- CodeQL `31562753422`;
- CI `31562753430`;
- Pint — 550 files;
- PHPStan/Larastan — 390/390, zero errors;
- ParaTest/PHPUnit — 440 tests / 10,025 assertions;
- frontend/build and clean migrations — success; and
- immutable image, staging, backup/restore, scan and cleanup — success.

## 11. Evidence identity, retention and supersession

Historical accepted evidence remains immutable. Current behavior follows current code/tests/living contracts.

K5 P2 runtime acceptance is attached to the exact implementation candidate above. The exact containing evidence/status head that records P2 Complete / P3 Current must independently pass protected gates before P3 implementation begins.

Future invitation/grant retention behavior belongs to P5 and must not erase acceptance evidence or canonical K3 observations.

## 12. Gaps, non-capabilities and related documentation

P2 does not validate bounded shared history, history pagination/query bounds, complete recipient/source sharing UX/accessibility or retention cleanup. Those remain P3–P5 work.

Current runtime still provides no player/roster sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, public APIs/webhooks, tenant directory, scoring/ranking/recommendations or automatic decisions.

Related: [Shared intelligence](../shared-intelligence.md), [Slice B validation](../product/kingdoms-shared-intelligence-slice-b-validation.md), [Slice B security review](../security/kingdoms-shared-intelligence-current-facts-security-review.md), [Security profile](../security/README.md), [Operations profile](../operations/README.md), [Interfaces](../interfaces/README.md), [testing/evidence standard](../../../product/testing-evidence-standard.md), [P5 testing/evidence coverage matrix](../../../product/testing-evidence-coverage-matrix.md).
