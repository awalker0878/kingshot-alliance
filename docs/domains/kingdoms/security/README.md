# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P3 bounded shared history validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned Kingdoms data, K4 source isolation, and K5 directional consent/explicit grants/current+bounded-history reads separated from private tenant data, public exposure and not-yet-approved complete sharing UX

## 1. Security purpose and scope

Kingdoms protects roster/history/import/intelligence, transfer/diplomacy/contact state, game-Alliance observations and K4 ingestion. K5 now includes explicit two-party consent, per-target grants, bounded safe current facts and bounded accepted history.

P2/P3 are cross-tenant read paths only. They remain source-owned, recipient-first authorized, explicitly granted, same-Kingdom, non-copying and non-transitive. Complete first-party sharing pages remain P4 work.

## 2. Assets and sensitive data

Tenant-private assets remain roster/player data, transfer state, tracking notes, diplomacy/contact data, correction rationale/actors/linkage, source tracking/stable game identifiers, observation IDs, K4 operational/source provenance and source secrets/raw responses.

K5 persists consent/grant metadata only. Source observations remain canonical in K3 tables; no recipient observation-history copy is stored.

History continuation cursors are encrypted transient read state and are not persisted as business data.

## 3. Actors, authentication and authorization

Member-safe existing reads use `alliance.view`; Kingdoms management uses `kingdoms.manage`. K5 consent and target mutations require recent password confirmation plus domain-level `kingdoms.manage`.

Source invitation/revoke/target actions are source-tenant scoped. Recipient accept/decline/leave acts under the recipient active Alliance. Recipient current/history facts authorize from recipient Alliance → active agreement → active target grant → valid source tracking/context.

Every history page repeats live authorization; a cursor alone never grants access.

There is no anonymous/public K5 data interface.

## 4. Tenant and privacy boundaries

Agreement acceptance requires different source/recipient Alliances whose current Kingdoms equal the captured invitation Kingdom. Target grants require source-owned active tracking in that same Kingdom.

Global neutral `KingdomAlliance` identity remains reference data only and grants no K5 access.

Current/history projections whitelist source Alliance ID/name, neutral/current game-Alliance name/tag, accepted observed name/tag, optional power/member count, capture time and descriptive freshness only.

Source tracking IDs, stable game IDs, observation IDs, manager notes, diplomacy/contact data, roster/transfer data, actors/reasons/correction linkage, K4 provenance and private free text stay source-private.

Counterpart Audit records use null actors where necessary to prevent cross-tenant manager User-ID disclosure.

## 5. Trust boundaries and data flows

P1 flow remains source manager → one-time invitation → authorized recipient manager → active directional agreement.

P2 adds source manager → explicit source-owned target grant → recipient-first safe current-fact projection.

P3 adds recipient-safe accepted history for one active explicit target, using an encrypted target-bound continuation cursor and a fixed history snapshot.

An active agreement without a grant exposes nothing. A grant does not transfer ownership. Current/history reads do not create reusable recipient canonical data or an upstream reshare object.

K4 source/network trust boundaries remain unchanged; production adapters remain empty.

## 6. Threats, abuse cases and controls

Controls address tenant enumeration, token leakage/replay, self-share, different-Kingdom activation, cross-tenant share/tracking/target substitution, duplicate active agreement, wildcard sharing, source-model over-serialization, private/K4 field leakage, copied recipient history, reshare/confused-deputy use, stale access after remove/revoke/drift, implicit access resume after returning to a Kingdom, unbounded history extraction, cursor tampering and stale-cursor reuse after authorization loss.

History cursor state is encrypted/authenticated, target-bound and capped to one 250-record traversal. P4 must not expose arbitrary user-controlled `asOf` windows that can repeatedly reopen progressively older history.

Tests prove invitation creation alone creates zero grants, explicit-target-only visibility, no recipient canonical copy, no reshare, bounded accepted history and no K5 current/history public API route.

See [P0 security review](kingdoms-shared-intelligence-p0-security-review.md), [Slice A security review](kingdoms-shared-intelligence-foundation-security-review.md), [Slice B security review](kingdoms-shared-intelligence-current-facts-security-review.md), and [Slice C security review](kingdoms-shared-intelligence-history-security-review.md).

## 7. Integrity, concurrency and idempotency

Successful consent redemption remains single-use. Target add is idempotent while active; a removed target requires deliberate re-grant.

Relevant mutation locking aligns to Alliance(s) → share → target where Kingdom drift can race with consent/grant changes. Source/recipient Alliances are locked in deterministic ID order for acceptance/grant operations.

Source invalidation remains canonical: invalidated observations stop participating in current/history immediately; corrected replacements participate only as their own accepted observations.

History ordering is deterministic by `captured_at DESC, id DESC`; encrypted cursor state captures one target, fixed `asOf`, keyset position and accepted-record count.

Supported Kingdom drift terminalizes affected agreements, preventing silent reactivation if the Alliance later returns.

## 8. Secrets and credential handling

K5 invitation plaintext is generated from 32 random bytes, shown once in the authenticated creation response and stored only as SHA-256 hash. The hash is hidden from normal serialization. Default expiry is 72 hours and repository-bounded to 1–168 hours.

Invitation plaintext is excluded from Audit/outbox/logging. History cursors are encrypted/authenticated and must not be logged or exposed as public reusable credentials.

K5 adds no external provider credential lifecycle. Current/history facts carry no source credentials, raw external response or K4 source internals.

## 9. Destructive operations, retention and deletion

Revoke/decline/leave/target removal reduce authorization only; they do not delete or mutate source canonical observations.

Expired/used invitation cleanup and long-term consent/grant retention remain P5 work. Retained consent/grant metadata cannot authorize data when state/context checks fail.

Operators must not reactivate/retarget agreements or grants by database edit. Stale encrypted history cursors must be treated as unusable after authorization loss.

## 10. Auditability, observability and evidence

K5 events use safe share/target/source/recipient/Kingdom/state/timing/reason metadata. Invitation plaintext and private source observation content remain excluded from durable operational evidence.

P2 target/context events remain internal and external-webhook ineligible. P3 adds no mutation event because history is read-only; current/history payloads and cursors are not Audit/outbox payloads.

P3 runtime candidate `70739d320caab059d2102feda081be33754b77ec` passed Dependency Review `31564263865`, CodeQL `31564263863`, and CI `31564263891`: Pint 553 files, PHPStan/Larastan 392/392 zero errors, 443 tests / 10,086 assertions, clean migrations, frontend/build, immutable image, staging, backup/restore, scan and cleanup.

## 11. Residual risks and explicit non-capabilities

P3 does not yet expose complete first-party source/recipient sharing pages. P4 must independently prove safe page props, member/manager visibility, opaque cursor navigation, no arbitrary historical-window control, terminal-state presentation and accessibility.

Current runtime provides no roster/player sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, public API/webhook, tenant directory, scoring/ranking/recommendations or automatic decisions.

## 12. Focused reviews and related documentation

### `KINGDOMS-001`–`004`

Existing focused/whole-increment Kingdoms security reviews remain historical accepted evidence.

### `KINGDOMS-005`

- [K5-P0 security/privacy review](kingdoms-shared-intelligence-p0-security-review.md)
- [K5-P1 sharing foundation security review](kingdoms-shared-intelligence-foundation-security-review.md)
- [K5-P2 current-facts security review](kingdoms-shared-intelligence-current-facts-security-review.md)
- [K5-P3 shared-history security review](kingdoms-shared-intelligence-history-security-review.md)
- [K5-P0 decisions](../product/kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 exit report](../product/kingdoms-shared-intelligence-p0-exit-report.md)
- [K5-P1 validation](../product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5-P2 validation](../product/kingdoms-shared-intelligence-slice-b-validation.md)
- [K5-P3 validation](../product/kingdoms-shared-intelligence-slice-c-validation.md)
- [Living shared-intelligence contract](../shared-intelligence.md)
- [K5 implementation plan](../product/kingdoms-shared-intelligence-implementation-plan.md)

- [Security baseline](../../../security/security-baseline.md)
