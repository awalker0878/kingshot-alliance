# Kingdoms player snapshots

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — accepted K1 history contract extended by governed `KINGDOMS-004` K4-P2 machine provenance  
**Owning domain:** `Kingdoms`

## 1. Purpose

Player snapshots are Alliance-owned append-oriented observations used for current/stale/missing projection and roster trends. Accepted sources are manual, controlled CSV, and the governed K4 ingestion promotion path.

## 2. Scope and non-scope

In scope: persisted observation fields/provenance, append history, exact retry, latest projection, freshness, disclosure, and audit/outbox behavior. Out of scope: destructive normal edit/delete, automatic roster enrollment, name-based identity matching, game-Alliance promotion, or public Kingdoms API/webhook exposure.

## 3. Model and state

A snapshot references one Alliance roster entry and its neutral `KingdomPlayer`. Manual/CSV snapshots retain User/import provenance. `source=ingestion` uses a null User actor plus bounded machine provenance: subscription/batch IDs, adapter key/version, optional source record ID, source identity hash, and payload hash.

Machine provenance is copied into canonical snapshot history without a foreign key to operational candidate rows so later candidate retention cannot erase accepted history.

## 4. Invariants

1. Snapshot history is append-oriented.
2. Normal roster edits and other workflows never rewrite existing observations.
3. Exact retry returns the existing snapshot without duplicate audit/outbox evidence.
4. Later capture time is a distinct observation even when values match.
5. Neutral player identity never authorizes another Alliance's history.
6. K4 promotion must resolve an existing owning-Alliance roster entry by stable game ID; it never creates one.
7. Manual/CSV idempotency identity remains unchanged by K4.
8. Missing snapshot remains distinct from zero power.

## 5. Workflows

Manual and CSV workflows continue to delegate to `RecordPlayerSnapshot` with human/import provenance. K4-P2 delegates to the same recorder with `source=ingestion`, null actor, and validated machine provenance after stable-ID/tenant/current-Kingdom checks.

Exact K4 candidate retry resolves the previously promoted snapshot. A distinct later candidate capture appends history.

## 6. Authorization, tenancy and privacy

History reads require active-Alliance context plus `alliance.view`. Manual/CSV mutation requires `kingdoms.manage` and recent password confirmation. Machine promotion operates only from already tenant-owned K4 state after its source/Kingdom/stable-ID checks.

Members do not receive actor/import/machine provenance. Managers may receive bounded actor/import/source provenance; candidate normalized bodies and secrets remain excluded.

## 7. Persistence and query semantics

`actor_user_id` is nullable only to represent legitimate machine-origin observations. Machine provenance fields are nullable for legacy/manual/CSV rows. `source_identity_hash` is unique within Alliance snapshot history for machine promotions.

Latest/current/stale/missing projection remains selected by capture time and existing deterministic tie-breaking; no mutable current-power table is introduced.

## 8. Events/integrations/background processing

A newly accepted observation emits internal `kingdoms.player_snapshot_recorded` audit/outbox evidence. Machine observations use null actor and bounded source identifiers/hashes. Exact retry emits no second observation event.

No snapshot scheduler or public webhook contract exists.

## 9. Failure, idempotency and concurrency

Power/timestamp/text bounds remain enforced by the shared recorder. K4 adds failure-before-mutation for unknown/ambiguous/out-of-context stable IDs, missing/ambiguous Alliance roster targets, revoked adapter versions, and invalid normalized payloads.

## 10. Operations and observability

Managers/operators can distinguish manual/CSV/ingestion source and, where authorized, correlate safe source provenance to K4 candidate/batch state. Canonical snapshot history must not be deleted merely because operational ingestion records are later pruned.

## 11. Tests and validation

K1 snapshot tests remain authoritative for append history, latest ordering, precision, disclosure, and human-source idempotency. K4-P2 adds exact candidate→snapshot retry, later-capture append history, no fake User actor, bounded machine provenance, cross-tenant/no-auto-enrollment, source-revocation, Kingdom-drift, and migration round-trip coverage.

Validated K4-P2 runtime candidate: `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f`; DR `31538958810`, CodeQL `31538958745`, CI `31538958920`.

## 12. Related documentation

- [Roster](roster.md)
- [Roster intelligence](intelligence.md)
- [Controlled CSV migration](csv-migration.md)
- [Automated ingestion](automated-ingestion.md)
- [K4 Slice B validation](product/kingdoms-automated-ingestion-slice-b-validation.md)
- [K4 Slice B security review](security/kingdoms-automated-ingestion-player-promotion-security-review.md)
