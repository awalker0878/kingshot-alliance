# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P4 first-party shared-intelligence presentation validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral identity, tenant-owned Kingdoms workflows, K4 ingestion isolation/idempotency, K5 consent/grant/current/history/presentation isolation and explicit public/decision-automation non-capabilities

**P5 evidence decision:** Current proof is mapped by executable suite/evidence class and immutable candidate identities; K5 adds focused cross-tenant evidence without replacing K1–K4 accepted evidence.

## 1. Critical claims and validation ownership

Kingdoms validation protects identity/tenant separation, append history, human-only governance, K4 source boundaries and K5's explicit cross-tenant consent/grant/read/presentation seam.

P4 proves accepted facts cross tenants only through live authorization, source canonical ownership remains intact, recipient reads do not copy/reshare facts, history extraction remains bounded, page props preserve privacy, one-time invitation plaintext is not persisted, terminal hashes are erased, and removal/revoke/drift immediately fail closed.

## 2. Executable suite mapping

All six PHPUnit groups remain material: Architecture, Feature, Integration, Performance, TenantIsolation and Unit. K5-P4 adds presentation/accessibility/migration/lifecycle evidence to accepted K1–K4 and P1–P3 suites.

Architecture protects ownership/non-capabilities/public exposure and source-level accessibility; Feature protects consent/grant/current/history/presentation workflows; Integration protects Audit/outbox/migrations; TenantIsolation protects source/recipient/grant boundaries; focused query assertions protect bounded current/history behavior.

## 3. Architecture and domain-boundary validation

Existing architecture guards continue to enforce no public Kingdoms API/wildcard webhook, stable-ID identity boundaries and documentation conventions.

K5 now has authenticated first-party GET presentation plus password-confirmed mutation routes. Architecture/accessibility tests cover the two sharing pages, semantic landmarks/headings, labels, captions, native controls, horizontal table overflow, status/alert semantics and absence of an `asOf` history control.

P1's historical no-sharing invariant remains protected: invitation creation alone creates zero target grants and no observation data is disclosed.

## 4. Authorization, tenancy, security and privacy validation

`KingdomIntelligenceSharingFoundationTest` proves password/permission, pending hash-only token handling, same-Kingdom consent, tenant scoping, single-use lifecycle, terminal hash erasure and fail-closed membership/Kingdom drift.

`KingdomSharedIntelligenceCurrentFactsTest` proves source-only target mutation, recipient-first visibility, explicit-target-only current facts, safe-field whitelisting, target removal/revocation/drift failure and no access resume after returning.

`KingdomSharedIntelligenceIsolationTest` proves recipient current reads create no local canonical copy, received tracking cannot be re-granted outbound, and missing remains distinct from zero.

`KingdomSharedIntelligenceHistoryTest` proves recipient/share/grant/source-context authorization on every page, safe accepted-only history, opaque target-bound cursor behavior, no recipient canonical copy and immediate fail-closed history after remove/revoke/drift including no resume after returning.

`KingdomSharedIntelligencePresentationTest` proves member-safe props, manager-only workspace access, safe manager props, invitation plaintext non-persistence and `canManage` behavior.

## 5. Feature, interface and integration validation

Focused P4 scenarios cover:

- deterministic accepted history ordered `captured_at DESC, id DESC`;
- source correction invalidating the original while the accepted replacement appears independently;
- private correction/invalidation metadata exclusion;
- encrypted target-bound cursor rejection when reused for another target;
- safe current/history projection shape with no observation/tracking/private/K4 identifiers;
- no recipient canonical copy or reshare;
- target removal/share revoke/Kingdom drift authorization loss;
- fixed continuation snapshot with no client-visible arbitrary `asOf` contract;
- 50-row page maximum and 250 accepted-observation traversal maximum;
- member/manager page-prop isolation and manager authorization;
- invitation token creation response as the only plaintext presentation seam;
- accept/decline/revoke terminal hash erasure;
- nullable-hash schema down→up recovery while preserving terminal state/recipient; and
- no public K5 API/webhook/directory surface.

P1–P3 evidence remains additive.

## 6. Idempotency, concurrency and asynchronous validation

P1 consent token/idempotency behavior and P2 target re-grant semantics remain protected.

Acceptance/grant locking aligns to deterministic Alliance(s) → share → target ordering where Kingdom drift can race. Supported Kingdom changes terminalize affected agreements inside the same transaction.

History is read-only and uses keyset continuation. The encrypted cursor fixes target/as-of/keyset/seen state but does not cache authorization; every page repeats live database/domain authorization.

K5 adds no asynchronous job/cache authorization path through P4.

## 7. Persistence, migration, rollback and recovery evidence

All three K5 migrations are included in clean PostgreSQL CI. P4's forward `030000` migration makes `invitation_token_hash` nullable without rewriting accepted P1 history.

The full Kingdoms round trip includes the new migration in correct dependency order. Focused recovery evidence proves a terminal null hash receives a deterministic retired placeholder on rollback to the historical non-null schema, then returns to null on reapply while terminal state/recipient binding remains unchanged.

Backup/restore passed with K5 consent/grant state and canonical K3 observations. Recipient history remains a live source projection, so there is no recipient observation copy to restore.

## 8. Performance, query and capacity evidence

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250; a focused 12-target fixture uses no more than two SELECTs.

`SharedKingdomIntelligenceHistoryQuery` caps page size at 50 and one traversal at 250 accepted observations. A fixture with 260 accepted observations proves exactly five 50-row pages, termination at 250, and no more than two SELECTs per page.

The manager query is bounded to 100 outbound agreements, 100 inbound agreements and 250 active source-owned trackable targets.

Keyset pagination prevents increasingly expensive offset scans. These are slice-level bounded-query gates, not production throughput SLOs; realistic-volume current/history capacity and authorization-safe caching/diagnostics remain P5 work.

## 9. Accessibility and frontend evidence

P4 adds the member-safe `Alliance/KingdomSharing` and manager-only `Alliance/KingdomSharingManage` pages.

The accepted P4 candidate passed frontend dependency audit, ESLint, locked Prettier/Tailwind formatting, Vue TypeScript validation and production frontend build. Architecture checks enforce semantic/label/table/status controls and absence of an arbitrary `asOf` UI.

## 10. Historical accepted evidence

K1–K4 historical accepted SHAs/run IDs remain immutable evidence.

K5-P0 candidate `d9e05fd06bd08050e5489598406cfb556d5bc0ac` passed DR `31557697685`, CodeQL `31557697793`, CI `31557697725` with 429 tests / 9,809 assertions.

K5-P1 candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed DR `31559012856`, CodeQL `31559012854`, CI `31559012861` with 434 tests / 9,911 assertions.

K5-P2 candidate `1a022e909cd246197510449a761a4856ce12b118` passed DR `31562753429`, CodeQL `31562753422`, CI `31562753430` with 440 tests / 10,025 assertions.

K5-P3 candidate `70739d320caab059d2102feda081be33754b77ec` passed DR `31564263865`, CodeQL `31564263863`, CI `31564263891` with 443 tests / 10,086 assertions.

K5-P4 runtime candidate `9a095ae62e9b913ece6d619c3744574f0b91fd6f` passed:

- Dependency Review `31569202741`;
- CodeQL `31569202422`;
- CI `31569202418`;
- Pint — 556 files;
- PHPStan/Larastan — 393/393, zero errors;
- ParaTest/PHPUnit — 448 tests / 10,160 assertions;
- frontend lint/locked-format/type/build and clean migrations — success; and
- immutable image, staging, backup/restore, scan and cleanup — success.

## 11. Evidence identity, retention and supersession

Historical accepted evidence remains immutable. Current behavior follows current code/tests/living contracts.

K5 P4 runtime acceptance is attached to the exact implementation candidate above. The exact containing evidence/status head that records P4 Complete / P5 Current must independently pass protected gates before P5 implementation begins.

Future agreement/grant retention behavior belongs to P5 and must not erase acceptance evidence or canonical K3 observations. Immediate consumed/terminal invitation-hash erasure is already accepted P4 behavior. Encrypted history cursors are transient continuation state and are not acceptance evidence.

## 12. Gaps, non-capabilities and related documentation

P5 still must validate bounded retention operations, active-record preservation, realistic-volume capacity, authorization-safe caching/diagnostics if introduced, and operational/recovery evidence.

Current runtime provides no arbitrary historical-window selection, player/roster sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, public APIs/webhooks, tenant directory, P5 retention/capacity runtime, scoring/ranking/recommendations or automatic decisions.

Related: [Shared intelligence](../shared-intelligence.md), [Slice D validation](../product/kingdoms-shared-intelligence-slice-d-validation.md), [Slice D security review](../security/kingdoms-shared-intelligence-presentation-security-review.md), [Security profile](../security/README.md), [Operations profile](../operations/README.md), [Interfaces](../interfaces/README.md), [testing/evidence standard](../../../product/testing-evidence-standard.md), [P5 testing/evidence coverage matrix](../../../product/testing-evidence-coverage-matrix.md).
