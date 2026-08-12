# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P2 current-fact sharing validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned Kingdoms data, K4 source isolation, and K5 directional consent/explicit grants/current facts separated from private tenant data, public exposure and not-yet-approved shared history

## 1. Security purpose and scope

Kingdoms protects roster/history/import/intelligence, transfer/diplomacy/contact state, game-Alliance observations and K4 ingestion. K5 now includes explicit two-party consent, per-target grants and a bounded safe recipient current-fact projection.

P2 is the first cross-tenant observation read. It remains source-owned, recipient-first authorized, explicitly granted, same-Kingdom, non-copying and non-transitive. Bounded shared history is not yet implemented.

## 2. Assets and sensitive data

Tenant-private assets remain roster/player data, transfer state, tracking notes, diplomacy/contact data, correction rationale/actors, source tracking/stable game identifiers not approved for the recipient payload, K4 operational/source provenance and source secrets/raw responses.

K5 persists consent/grant metadata only. Source observations remain canonical in K3 tables; no recipient observation-history copy is stored.

## 3. Actors, authentication and authorization

Member-safe existing reads use `alliance.view`; Kingdoms management uses `kingdoms.manage`. K5 consent and target mutations require recent password confirmation plus domain-level `kingdoms.manage`.

Source invitation/revoke/target actions are source-tenant scoped. Recipient accept/decline/leave acts under the recipient active Alliance. Recipient current facts authorize from recipient Alliance → active agreement → active target grant → valid source tracking/context.

There is no anonymous/public K5 data interface.

## 4. Tenant and privacy boundaries

Agreement acceptance requires different source/recipient Alliances whose current Kingdoms equal the captured invitation Kingdom. Target grants require source-owned active tracking in that same Kingdom.

Global neutral `KingdomAlliance` identity remains reference data only and grants no K5 access.

Current-fact projection whitelists only source Alliance ID/name, neutral/current game-Alliance name/tag, latest accepted observed name/tag, optional power/member count, capture time and freshness.

Source tracking IDs, stable game IDs, manager notes, diplomacy/contact data, roster/transfer data, observation actor/invalidation reason, K4 provenance and private free text stay source-private.

Counterpart Audit records use null actors where necessary to prevent cross-tenant manager User-ID disclosure.

## 5. Trust boundaries and data flows

P1 flow remains source manager → one-time invitation → authorized recipient manager → active directional agreement.

P2 adds source manager → explicit source-owned target grant → recipient-first safe current-fact projection over accepted source observations.

An active agreement without a grant exposes nothing. A grant does not transfer ownership. The recipient does not receive source canonical rows or a reusable upstream sharing object.

K4 source/network trust boundaries remain unchanged; production adapters remain empty.

## 6. Threats, abuse cases and controls

Controls address tenant enumeration, token leakage/replay, self-share, different-Kingdom activation, cross-tenant share/tracking/target substitution, duplicate active agreement, wildcard sharing, source-model over-serialization, private/K4 field leakage, copied recipient history, reshare/confused-deputy use, stale access after remove/revoke/drift and implicit access resume after returning to a Kingdom.

Tests prove invitation creation alone creates zero target grants, explicit-target-only visibility, no recipient canonical copy, no reshare and no K5 current/history GET/public route.

See [P0 security review](kingdoms-shared-intelligence-p0-security-review.md), [Slice A security review](kingdoms-shared-intelligence-foundation-security-review.md), and [Slice B security review](kingdoms-shared-intelligence-current-facts-security-review.md).

## 7. Integrity, concurrency and idempotency

Successful consent redemption remains single-use. Target add is idempotent while active; a removed target requires deliberate re-grant.

Relevant mutation locking aligns to Alliance(s) → share → target where Kingdom drift can race with consent/grant changes. Source/recipient Alliances are locked in deterministic ID order for acceptance/grant operations.

Source invalidation remains canonical: invalidated observations stop participating immediately and the current projection may fall back only to an older still-accepted observation.

Supported Kingdom drift terminalizes affected agreements, preventing silent reactivation if the Alliance later returns.

## 8. Secrets and credential handling

K5 invitation plaintext is generated from 32 random bytes, shown once in the authenticated creation response and stored only as SHA-256 hash. The hash is hidden from normal serialization. Default expiry is 72 hours and repository-bounded to 1–168 hours.

Invitation plaintext is excluded from Audit/outbox/logging. K5 adds no external provider credential lifecycle.

P2 current facts carry no source credentials, raw external response or K4 source internals.

## 9. Destructive operations, retention and deletion

Revoke/decline/leave/target removal reduce authorization only; they do not delete or mutate source canonical observations.

Expired/used invitation cleanup and long-term consent/grant retention remain P5 work. Retained consent/grant metadata cannot authorize data when state/context checks fail.

Operators must not reactivate/retarget agreements or grants by database edit.

## 10. Auditability, observability and evidence

K5 events use safe share/target/source/recipient/Kingdom/state/timing/reason metadata. Invitation plaintext and private source observation content remain excluded from durable operational evidence.

P2 adds internal `kingdoms.shared_intelligence_target_shared`, `kingdoms.shared_intelligence_target_removed`, and `kingdoms.shared_intelligence_context_invalidated` evidence. All remain external-webhook ineligible.

P2 runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed Dependency Review `31562753429`, CodeQL `31562753422`, and CI `31562753430`: Pint 550 files, PHPStan/Larastan 390/390 zero errors, 440 tests / 10,025 assertions, clean migrations, frontend/build, immutable image, staging, backup/restore, scan and cleanup.

## 11. Residual risks and explicit non-capabilities

P2 does not yet expose bounded shared history or complete first-party K5 pages. P3 must independently prove bounded/paginated accepted-only history with the same authorization/privacy/non-copy/no-reshare rules.

Current runtime provides no roster/player sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, public API/webhook, tenant directory, scoring/ranking/recommendations or automatic decisions.

## 12. Focused reviews and related documentation

### `KINGDOMS-001`–`004`

Existing focused/whole-increment Kingdoms security reviews remain historical accepted evidence.

### `KINGDOMS-005`

- [K5-P0 security/privacy review](kingdoms-shared-intelligence-p0-security-review.md)
- [K5-P1 sharing foundation security review](kingdoms-shared-intelligence-foundation-security-review.md)
- [K5-P2 current-facts security review](kingdoms-shared-intelligence-current-facts-security-review.md)
- [K5-P0 decisions](../product/kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 exit report](../product/kingdoms-shared-intelligence-p0-exit-report.md)
- [K5-P1 validation](../product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5-P2 validation](../product/kingdoms-shared-intelligence-slice-b-validation.md)
- [Living shared-intelligence contract](../shared-intelligence.md)
- [K5 implementation plan](../product/kingdoms-shared-intelligence-implementation-plan.md)

- [Security baseline](../../../security/security-baseline.md)
