# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P3 bounded shared history validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral identity, tenant-owned Kingdoms workflows, K4 ingestion isolation/idempotency, K5 consent/grant/current/history isolation and explicit public/decision-automation non-capabilities

**P5 evidence decision:** Current proof is mapped by executable suite/evidence class and immutable candidate identities; K5 adds focused cross-tenant evidence without replacing K1–K4 accepted evidence.

## 1. Critical claims and validation ownership

Kingdoms validation protects identity/tenant separation, append history, human-only governance, K4 source boundaries and K5's explicit cross-tenant consent/grant/read seam.

P3 proves explicitly granted safe accepted history crosses tenants only through live authorization, source canonical ownership remains intact, recipient reads do not copy/reshare facts, history extraction is bounded and removal/revoke/drift immediately fail closed. Complete source/recipient sharing UX remains outside current evidence.

## 2. Executable suite mapping

All six PHPUnit groups remain material: Architecture, Feature, Integration, Performance, TenantIsolation and Unit. K5-P3 adds feature/tenant/query/cursor evidence to accepted K1–K4 and P1/P2 suites.

Architecture protects ownership/non-capabilities/public exposure; Feature protects consent/grant/current/history workflows; Integration protects Audit/outbox/migrations; TenantIsolation protects source/recipient/grant boundaries; focused query assertions protect bounded current/history behavior.

## 3. Architecture and domain-boundary validation

Existing architecture guards continue to enforce no public Kingdoms API/wildcard webhook, stable-ID identity boundaries and documentation conventions.

K5 adds password-confirmed mutation routes only. Focused tests continue to assert there is no K5 current/history public GET route. P2/P3 recipient projections remain internal query contracts pending P4 first-party presentation.

P1's historical no-sharing invariant remains protected: invitation creation alone creates zero target grants and no observation data is disclosed.

## 4. Authorization, tenancy, security and privacy validation

`KingdomIntelligenceSharingFoundationTest` proves password/permission, hash-only token, same-Kingdom consent, tenant scoping and token secrecy.

`KingdomSharedIntelligenceCurrentFactsTest` proves source-only target mutation, recipient-first visibility, explicit-target-only current facts, safe-field whitelisting, target removal/revocation/drift failure and no access resume after returning.

`KingdomSharedIntelligenceIsolationTest` proves recipient current reads create no local canonical copy, received tracking cannot be re-granted outbound, and missing remains distinct from zero.

`KingdomSharedIntelligenceHistoryTest` proves recipient/share/grant/source-context authorization on every page, safe accepted-only history, opaque target-bound cursor behavior, no recipient canonical copy and immediate fail-closed history after remove/revoke/drift including no resume after returning.

## 5. Feature, interface and integration validation

Focused P3 scenarios cover:

- deterministic accepted history ordered `captured_at DESC, id DESC`;
- source correction invalidating the original while the accepted replacement appears independently;
- private correction/invalidation metadata exclusion;
- encrypted target-bound cursor rejection when reused for another target;
- safe history projection shape with no observation/tracking/private/K4 identifiers;
- no recipient canonical copy;
- target removal/share revoke/Kingdom drift authorization loss on subsequent pages;
- fixed continuation snapshot with no client-visible arbitrary `asOf` contract;
- 50-row page maximum and 250 accepted-observation traversal maximum; and
- no current/history public API surface.

P2 current-fact and P1 consent/grant evidence remains additive.

## 6. Idempotency, concurrency and asynchronous validation

P1 consent token/idempotency behavior and P2 target re-grant semantics remain protected.

Acceptance/grant locking aligns to deterministic Alliance(s) → share → target ordering where Kingdom drift can race. Supported Kingdom changes terminalize affected agreements inside the same transaction.

P3 history is read-only and uses keyset continuation. The encrypted cursor fixes target/as-of/keyset/seen state but does not cache authorization; every page repeats live database/domain authorization.

K5 adds no asynchronous job/cache authorization path through P3.

## 7. Persistence, migration, rollback and recovery evidence

Both K5 persistence migrations remain included in clean PostgreSQL CI. P3 adds no database migration; history cursor state is transient/encrypted request state.

The full Kingdoms round trip and focused K3 rollback continue to prove correct FK dependency ordering for K5 target grants.

Backup/restore passed with K5 consent/grant state and canonical K3 observations. Recipient history remains a live source projection, so there is no recipient observation copy to restore.

## 8. Performance, query and capacity evidence

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250; a focused 12-target fixture uses no more than two SELECTs.

`SharedKingdomIntelligenceHistoryQuery` caps page size at 50 and one traversal at 250 accepted observations. A fixture with 260 accepted observations proves exactly five 50-row pages, termination at 250, and no more than two SELECTs per page.

Keyset pagination prevents increasingly expensive offset scans. These are slice-level bounded-query gates, not production throughput SLOs; realistic-volume current/history capacity remains P5 work.

## 9. Accessibility and frontend evidence

P3 adds no new K5 Vue/page surface. Target routes remain mutation interfaces and current/history projections remain internal.

The P3 candidate passed frontend dependency audit, ESLint/Prettier/Vue-TypeScript checks and production frontend build. Full source/recipient first-party UX/accessibility remains P4 and must use opaque cursors without arbitrary historical-window controls.

## 10. Historical accepted evidence

K1–K4 historical accepted SHAs/run IDs remain immutable evidence.

K5-P0 candidate `d9e05fd06bd08050e5489598406cfb556d5bc0ac` passed DR `31557697685`, CodeQL `31557697793`, CI `31557697725` with 429 tests / 9,809 assertions.

K5-P1 candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed DR `31559012856`, CodeQL `31559012854`, CI `31559012861` with 434 tests / 9,911 assertions.

K5-P2 candidate `1a022e909cd246197510449a761a4856ce12b118` passed DR `31562753429`, CodeQL `31562753422`, CI `31562753430` with 440 tests / 10,025 assertions.

K5-P3 runtime candidate `70739d320caab059d2102feda081be33754b77ec` passed:

- Dependency Review `31564263865`;
- CodeQL `31564263863`;
- CI `31564263891`;
- Pint — 553 files;
- PHPStan/Larastan — 392/392, zero errors;
- ParaTest/PHPUnit — 443 tests / 10,086 assertions;
- frontend/build and clean migrations — success; and
- immutable image, staging, backup/restore, scan and cleanup — success.

## 11. Evidence identity, retention and supersession

Historical accepted evidence remains immutable. Current behavior follows current code/tests/living contracts.

K5 P3 runtime acceptance is attached to the exact implementation candidate above. The exact containing evidence/status head that records P3 Complete / P4 Current must independently pass protected gates before P4 implementation begins.

Future invitation/grant retention behavior belongs to P5 and must not erase acceptance evidence or canonical K3 observations. Encrypted history cursors are transient continuation state and are not acceptance evidence.

## 12. Gaps, non-capabilities and related documentation

P3 does not validate complete recipient/source sharing pages, page-prop privacy/accessibility, invitation one-time display behavior in UI or retention cleanup. Those remain P4–P5 work.

Current runtime still provides no arbitrary historical-window selection, player/roster sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, public APIs/webhooks, tenant directory, scoring/ranking/recommendations or automatic decisions.

Related: [Shared intelligence](../shared-intelligence.md), [Slice C validation](../product/kingdoms-shared-intelligence-slice-c-validation.md), [Slice C security review](../security/kingdoms-shared-intelligence-history-security-review.md), [Security profile](../security/README.md), [Operations profile](../operations/README.md), [Interfaces](../interfaces/README.md), [testing/evidence standard](../../../product/testing-evidence-standard.md), [P5 testing/evidence coverage matrix](../../../product/testing-evidence-coverage-matrix.md).
