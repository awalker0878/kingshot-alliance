# KINGDOMS-004 Slice B player-promotion security review

[← Kingdoms security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Kingdoms  
**Capability:** Automated player-snapshot promotion (`K4-P2`)  
**Code owner:** `app/Domain/Kingdoms`

## 1. Scope and security objective

This review covers promotion of normalized `player_snapshot` candidates into canonical snapshot history. It does not approve a concrete source/network, credentials, scheduler/worker, game-Alliance promotion, or production cutover.

The objective is to prevent machine ingestion from becoming an identity-guessing, cross-tenant, fake-user, destructive-history, or decision-automation path.

## 2. Assets and sensitive data

Protected assets include Alliance roster/history, candidate/batch/subscription context, stable game-player identity, canonical snapshot values/capture time, bounded machine provenance, and internal audit/outbox evidence.

Source credentials/raw responses remain outside Kingdoms state. Machine provenance is limited to subscription/batch IDs, adapter key/version, optional source record ID, and SHA-256 identity/payload hashes.

## 3. Trust boundaries

Promotion crosses operational K4 state into accepted K1 snapshot history. That boundary is guarded by current-Alliance/current-Kingdom/source-version checks, stable-ID target resolution, owning-Alliance roster resolution, and delegation through `RecordPlayerSnapshot`.

Neutral `Player` identity is reference data only and never tenant authority.

## 4. Threats and controls

### Cross-tenant target confusion
A shared neutral player could be used to write another tenant's history. **Control:** roster lookup is constrained to `subscription.alliance_id`; no roster relation means quarantine.

### Name/tag identity guessing
A source label could be treated as identity. **Control:** only stable game-player ID participates in target resolution; names/tags/handles never match or enroll.

### Automatic enrollment
Promotion could create a roster entry to force success. **Control:** Slice B never calls roster creation; missing target quarantines.

### Fake human attribution
Machine observations could be attributed to a fabricated service User. **Control:** snapshot actor is nullable for `source=ingestion`; machine provenance is explicit and Audit accepts null actor.

### Replay multiplication
At-least-once processing could duplicate history/evidence. **Control:** candidate promoted-record reference, deterministic candidate identity, snapshot idempotency including source identity hash, and Alliance/source-identity uniqueness return the existing result.

### Operational-retention deletion of business history
Pruning candidates could cascade into canonical snapshots. **Control:** snapshots store copied bounded provenance and have no FK to candidate/subscription/batch rows.

### Stale/revoked context
A candidate could promote after Kingdom drift or adapter revocation. **Control:** promotion rechecks current Kingdom and currently registered adapter/version/target support before mutation.

## 5. Authorization, tenancy and privacy

Human management remains `kingdoms.manage` plus recent password confirmation. Promotion authority comes only from validated tenant-owned K4 state, never neutral/source identity.

Members retain the K1 disclosure boundary. Manager history may expose bounded machine provenance; normalized candidate bodies and secrets remain excluded.

## 6. Integrity, replay and concurrency

Subscription/candidate/batch/alliance rows are locked through the promotion transaction. Successful candidate state records only safe promoted record identity/time. Existing snapshot validation remains the authority for value/capture bounds.

Later capture time remains a distinct observation; exact retry remains one snapshot/one promotion event.

## 7. Secret and data lifecycle

No source secret is introduced. Operational candidate/batch retention remains a later K4-P5 policy, but canonical promoted history and bounded provenance are deliberately independent from those operational rows.

## 8. Abuse limits and failure behavior

Unknown, ambiguous, out-of-context, revoked-source, and invalid-payload cases quarantine before snapshot mutation. Quarantine reason codes are bounded operational metadata and are not permission to repair identity by guesswork.

## 9. Verification and evidence

Runtime candidate `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f` passed DR `31538958810`, CodeQL `31538958745`, and CI `31538958920`: Pint 512, PHPStan 364/364 zero errors, 412 tests / 9,564 assertions, migrations, image, staging, backup/restore, and scan.

Focused tests cover exact retry, later append-history capture, null machine actor/provenance, cross-tenant/no-auto-enrollment, unknown-player quarantine, Kingdom drift, source revocation, and migration round-trip.

## 10. Residual risks and external controls

A real source can still be wrong, malicious, unavailable, or contractually unauthorized. Source permission/network/secret/rate/schema controls remain separate approval requirements. K4-P3 must independently review game-Alliance observation promotion, and K4-P4 must review autonomous scheduling/retry/cursor behavior.

Repository acceptance does not approve real production source enablement or production cutover.
