# KINGDOMS-004 Slice C game-Alliance-promotion security review

[← Kingdoms security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Kingdoms  
**Capability:** Automated game-Alliance observation promotion (`K4-P3`)  
**Code owner:** `app/Domain/Kingdoms`

## 1. Scope and security objective

This review covers promotion of normalized `alliance_observation` candidates into canonical K3 factual observation history for an existing active tracking relationship. It does not approve tracking creation, machine correction/invalidation, diplomacy/contact mutation, a concrete source/network, credentials, scheduler/worker, or production cutover.

## 2. Assets and sensitive data

Protected assets include Alliance tracking/history, neutral stable game-Alliance identity, current-Kingdom context, candidate/batch/subscription state, factual name/tag/power/member observations, bounded machine provenance, and internal audit/outbox evidence.

Diplomacy terms, contacts/private notes, source credentials, and arbitrary raw source responses are outside the machine promotion payload and remain protected from this path.

## 3. Trust boundaries

Promotion crosses operational K4 candidate state into accepted K3 observation history. The boundary is guarded by current-Alliance/current-Kingdom/source-version checks, stable-ID neutral-reference resolution, active owning-Alliance tracking resolution, and delegation through `RecordKingdomAllianceObservation`.

Neutral `KingdomAlliance` identity is reference data only and never tenant authority.

## 4. Threats and controls

### Cross-tenant tracking confusion
A globally shared neutral reference could be used to write another tenant's intelligence. **Control:** tracking lookup is constrained to subscription Alliance/current Kingdom; missing relation quarantines.

### Name/tag identity guessing
Display data could be treated as identity. **Control:** only stable `game_alliance_id` participates in automatic target resolution.

### Automatic tracking creation or reactivation
Promotion could silently create/reactivate tracking. **Control:** P3 never calls tracking creation/lifecycle actions; missing or inactive tracking quarantines.

### Machine correction of accepted history
Source data could invalidate prior K3 evidence. **Control:** machine candidate schema excludes correction fields and the shared recorder rejects machine correction/invalidation requests. Correction remains manager-only.

### Fake human attribution
Machine observations could be attributed to a fabricated service User. **Control:** `source=ingestion` uses a null actor plus explicit bounded machine provenance.

### Replay multiplication
At-least-once processing could duplicate observation history. **Control:** promoted-record reference, candidate identity, observation idempotency including source identity hash, and Alliance/source-identity uniqueness return existing state on exact retry.

### Operational-retention deletion of business history
Future candidate pruning could erase promoted observations. **Control:** canonical observations copy bounded provenance and have no FK to candidate/subscription/batch rows.

### Stale or revoked context
A candidate could promote after Kingdom drift, source revocation, neutral deactivation, or tracking archival. **Control:** all are rechecked at promotion time before business mutation.

## 5. Authorization, tenancy and privacy

Human K3/K4 management retains `kingdoms.manage` plus recent password confirmation. Machine promotion authority derives only from validated tenant-owned K4 state, never neutral/source identity.

Member observation history remains free of actor/machine provenance. Manager history may expose bounded source provenance. Candidate normalized bodies, source secrets, diplomacy/contact private data, and raw responses remain excluded.

## 6. Integrity, replay and concurrency

Subscription/candidate/batch/alliance/tracking context is resolved under transactional locks. Successful candidate state records only safe promoted-record identity/time. Accepted K3 observation validation remains authoritative for factual field/capture bounds and neutral current-name/tag synchronization.

Later capture remains distinct history; exact retry remains one observation/one promotion result.

## 7. Secret and data lifecycle

No source secret is introduced. Operational candidate/batch retention remains K4-P5, while canonical promoted K3 observation history and bounded provenance are independent from operational rows.

## 8. Abuse limits and failure behavior

Unknown, ambiguous, inactive, out-of-context, revoked-source, and invalid-payload cases quarantine before K3 history mutation. Quarantine is not permission to guess identity, create/reactivate tracking, or alter diplomacy.

## 9. Verification and evidence

Runtime candidate `8186af9fd7276a20889ca3a25b80172c6fe824d9` passed DR `31541291512`, CodeQL `31541291470`, and CI `31541291501`: Pint 515, PHPStan 365/365 zero errors, 417 tests / 9,628 assertions, migrations, immutable image, staging, backup/restore, and scan.

Focused tests cover exact retry, later append-history capture, null machine actor/provenance, manager provenance disclosure, cross-tenant/no-auto-tracking, inactive-tracking quarantine, unknown-reference quarantine, source revocation, and migration round-trip.

## 10. Residual risks and external controls

A real source can still be wrong, malicious, unavailable, or contractually unauthorized. Source permission/network/secret/rate/schema controls remain separate approval requirements. K4-P4 must independently prove scheduler/cursor/retry/concurrency behavior around both accepted promotion paths.

Repository acceptance does not approve real production source enablement or production cutover.
