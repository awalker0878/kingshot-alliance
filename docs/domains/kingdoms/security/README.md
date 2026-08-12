# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P1 consent foundation validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned Kingdoms data, K4 source isolation, and K5 directional consent state separated from public exposure, private tenant data and not-yet-approved shared observation reads

## 1. Security purpose and scope

Kingdoms protects roster/history/import/intelligence, transfer/diplomacy/contact state, game-Alliance observations and K4 ingestion. K5-P1 introduces the first cross-tenant **consent metadata** boundary but still exposes no shared observation data.

P1 exists so later sharing reads can be authorized through an explicit two-party agreement rather than neutral identity, tenant enumeration or implicit trust.

## 2. Assets and sensitive data

Tenant-private assets remain roster/player data, transfer state, tracking notes, diplomacy/contact data, correction rationale/actors, K4 operational/source provenance and source secrets/raw responses.

K5-P1 persists only consent/grant metadata and a hash-only invitation token. It stores no selected target or shared observation payload/history.

## 3. Actors, authentication and authorization

Member-safe existing reads use `alliance.view`; Kingdoms management uses `kingdoms.manage`. Every K5-P1 HTTP consent mutation also requires recent password confirmation.

Source invitation/revoke actions are source-tenant scoped. Recipient accept/decline/leave acts under the recipient's active Alliance. There is no anonymous or public invitation-redemption surface.

## 4. Tenant and privacy boundaries

Acceptance requires source and recipient to be different Alliances and both current Kingdoms to equal the captured invitation Kingdom. Source/recipient rows are re-resolved and locked before activation.

Global neutral `KingdomAlliance` identity remains reference data only and grants no K5 access.

Source-side Audit intentionally records acceptance with null actor rather than disclose the recipient manager's global User ID; recipient-side acceptance remains attributable within its own tenant.

## 5. Trust boundaries and data flows

P1 flow is source manager → one-time invitation secret → authorized recipient manager → persisted active directional agreement.

The invitation secret is not a public API credential. An active P1 agreement still exposes no tracked-target/observation data. The first actual cross-tenant data projection remains a P2 gate.

K4's source/network trust boundary remains unchanged and production adapters remain empty.

## 6. Threats, abuse cases and controls

P1 controls tenant enumeration, invitation-secret leakage, self-share, different-Kingdom activation, exact-token replay, duplicate active directional agreement, cross-tenant share-ID substitution, terminal-state reactivation and cross-tenant actor leakage.

Tests also assert no K5 target-sharing table or shared-observation GET route exists in P1.

See [P0 security review](kingdoms-shared-intelligence-p0-security-review.md) and [Slice A security review](kingdoms-shared-intelligence-foundation-security-review.md).

## 7. Integrity, concurrency and idempotency

Acceptance locks the pending share, then source/recipient Alliance rows in deterministic ID order. Successful acceptance consumes the token once. Failed acceptance remains transactional and does not consume it.

Revoke/leave are tenant-scoped row-locked transitions. Declined/revoked terminal state does not reactivate through P1 actions. Access-reducing transitions intentionally remain available after Kingdom drift.

## 8. Secrets and credential handling

K5 invitation plaintext is generated from 32 random bytes, shown once in the authenticated creation response and stored only as SHA-256 hash. The hash is hidden from normal model serialization. Default expiry is 72 hours and repository-bounded to 1–168 hours.

Invitation plaintext is excluded from Audit/outbox payloads and must not be logged. K5 adds no external provider credential lifecycle.

## 9. Destructive operations, retention and deletion

P1 revoke/decline/leave change consent authorization only; they do not delete or mutate source canonical observations.

Expired/used invitation cleanup and long-term consent retention policy remain P5 work. Until then, retained token hashes are unusable after state/use/expiry checks and grant no observation access because no P1 data path exists.

Operators must not reactivate/retarget agreements by database edit.

## 10. Auditability, observability and evidence

Consent events use safe share/source/recipient/Kingdom/state/timing metadata. Invitation plaintext and private source data are excluded.

P1 runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and CI `31559012861`: Pint 541 files, PHPStan/Larastan 384/384 zero errors, 434 tests / 9,911 assertions, clean migrations, frontend/build, immutable image, staging, backup/restore and scan.

## 11. Residual risks and explicit non-capabilities

P1 still provides no shared target selection or recipient current/history read, so it does not yet validate safe-field data projection, recipient read-query performance, correction/invalidation propagation or data-path reshare prevention.

Current runtime provides no roster/player sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, public API/webhook, tenant directory, scoring/ranking/recommendations or automatic decisions.

## 12. Focused reviews and related documentation

### `KINGDOMS-001`–`004`

Existing focused/whole-increment Kingdoms security reviews remain historical accepted evidence.

### `KINGDOMS-005`

- [K5-P0 security/privacy review](kingdoms-shared-intelligence-p0-security-review.md)
- [K5-P1 sharing foundation security review](kingdoms-shared-intelligence-foundation-security-review.md)
- [K5-P0 decisions](../product/kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 exit report](../product/kingdoms-shared-intelligence-p0-exit-report.md)
- [K5-P1 validation](../product/kingdoms-shared-intelligence-slice-a-validation.md)
- [Living shared-intelligence contract](../shared-intelligence.md)
- [K5 implementation plan](../product/kingdoms-shared-intelligence-implementation-plan.md)

- [Security baseline](../../../security/security-baseline.md)