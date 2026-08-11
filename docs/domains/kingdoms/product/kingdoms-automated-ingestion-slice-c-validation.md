# KINGDOMS-004 Slice C validation

[← KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)

**Scope:** `KINGDOMS-004` Slice C / `K4-P3`  
**Status:** **Complete for continuation when the containing evidence head is protected-green**  
**Validated runtime SHA:** `8186af9fd7276a20889ca3a25b80172c6fe824d9`  
**Baseline:** completed K4-P2 evidence head `3ed9b442c72d386d35f7be146f90e75f4bea9856`

## Validated capability

Slice C adds one governed promotion path: a pending `alliance_observation` candidate may become a canonical `KingdomAllianceObservation` only when its active subscription/batch/candidate context still matches the owning Alliance/current Kingdom and approved adapter version, its stable game-Alliance ID resolves to one active neutral `KingdomAlliance` in that Kingdom, and that reference already has exactly one active `TrackedKingdomAlliance` relationship in the owning Alliance.

The promotion delegates to the accepted `RecordKingdomAllianceObservation` action. It does not create tracking, infer identity from names/tags/handles, fabricate a User actor, mutate diplomacy/contacts/transfers, score/rank/recommend, or introduce scheduler/network acquisition.

Machine promotion is append-only. K3 correction/invalidation remains a human governance action: ingestion candidates cannot carry correction linkage/reason and the shared recorder rejects machine correction attempts.

Canonical observations carry bounded machine provenance without foreign-key dependence on operational candidate rows. Exact retry returns the same promoted observation; a later distinct capture remains append-oriented history and continues to drive the accepted neutral-current-name/tag projection.

## Failure and quarantine evidence

The implemented path quarantines before business mutation for candidate/batch/subscription context mismatch, Alliance-Kingdom drift, missing or revoked adapter/version, missing/unknown/ambiguous stable game-Alliance identity, inactive neutral reference, missing/inactive/ambiguous owning-Alliance tracking target, and shared observation validation failure.

Cross-tenant testing proves that a neutral game Alliance tracked by another tenant does not authorize promotion in an Alliance that has no tracking relationship, and no tracking row is auto-created to make the candidate succeed.

## Protected runtime validation

Exact runtime SHA `8186af9fd7276a20889ca3a25b80172c6fe824d9` passed:

- Dependency Review `31541291512` — **success**;
- CodeQL `31541291470` — **success**;
- CI `31541291501` — **success**;
- Pint — **515 files**;
- PHPStan/Larastan — **365/365, 0 errors**;
- ParaTest/PHPUnit — **417 tests / 9,628 assertions**;
- frontend checks/build — success;
- PostgreSQL migrations including K4-P3 observation-provenance migration — success;
- immutable production image — success;
- ephemeral staging — success;
- backup/restore — success;
- image scan and cleanup — success.

## Evidence-head gate and continuation

This validation record and the updated living capability/security/operations/interface/testing documentation form the K4-P3 evidence head. P3 is authoritative as Complete only when that exact containing head independently passes Dependency Review, CodeQL, and full CI.

Once green, `K4-P4` / Slice D is authorized and remains limited to scheduler/cursor/retry/replay/concurrency behavior around the two already accepted promotion paths. A networked concrete adapter remains separately gated by source authorization, DNS/redirect/private-address/TLS/egress/secret/rate/schema controls.

No concrete production source, source credentials, real-source enablement, scheduler/worker, or production cutover is approved by this validation.
