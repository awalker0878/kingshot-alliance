# KINGDOMS-004 Slice B validation

[← KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)

**Scope:** `KINGDOMS-004` Slice B / `K4-P2`  
**Status:** **Complete for continuation when the containing evidence head is protected-green**  
**Validated runtime SHA:** `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f`  
**Baseline:** completed K4-P1 evidence head `115fa47fc709a5769acc54a0971f3702e9894b71`

## Validated capability

Slice B adds one governed promotion path: a pending `player_snapshot` candidate may become a canonical `PlayerSnapshot` only when its active subscription/batch/candidate context still matches the owning Alliance/current Kingdom and approved adapter version, its stable game-player ID resolves to one neutral player in that Kingdom, and that player already has exactly one roster entry in the owning Alliance.

The promotion delegates to the accepted `RecordPlayerSnapshot` action. It does not call roster creation, infer identity from names/tags/handles, fabricate a User actor, create membership/tracking/transfer/diplomacy state, or introduce scheduler/network acquisition.

Canonical snapshots carry bounded machine provenance without foreign-key dependence on operational candidate rows. Candidates retain only safe promoted-record type/ID/time. Exact retry returns the same promoted snapshot; a later distinct capture remains append-oriented history.

## Failure and quarantine evidence

The implemented path quarantines before business mutation for candidate/batch/subscription context mismatch, Alliance-Kingdom drift, missing or revoked adapter/version, missing/unknown/ambiguous stable player identity, missing/ambiguous owning-Alliance roster target, and shared snapshot validation failure.

Cross-tenant testing proves that a neutral player shared by two Alliances does not authorize promotion into an Alliance that has no roster relationship, and no roster entry is auto-created to make the candidate succeed.

## Protected runtime validation

Exact runtime SHA `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f` passed:

- Dependency Review `31538958810` — **success**;
- CodeQL `31538958745` — **success**;
- CI `31538958920` — **success**;
- Pint — **512 files**;
- PHPStan/Larastan — **364/364, 0 errors**;
- ParaTest/PHPUnit — **412 tests / 9,564 assertions**;
- frontend checks/build — success;
- PostgreSQL migrations including K4-P2 provenance migration — success;
- immutable production image — success;
- ephemeral staging — success;
- backup/restore — success;
- image scan and cleanup — success.

The first P2 runtime attempt `ee3d9377e8d823688ce446d51a38d2863a0eb95e` was rejected only because PHPStan identified two redundant always-true `instanceof` guards in the new promotion action. Commit `37a7df3e...` removed only those dead guards; the final runtime behavior otherwise remained unchanged.

## Evidence-head gate and continuation

This validation record and the updated living capability/security/operations/interface/testing documentation form the K4-P2 evidence head. P2 is authoritative as Complete only when that exact containing head independently passes Dependency Review, CodeQL, and full CI.

Once green, `K4-P3` / Slice C is authorized and remains limited to stable-game-Alliance-ID promotion for an existing active `TrackedKingdomAlliance`, delegated through accepted K3 observation semantics. `K4-P4` and later phases remain blocked.

No concrete production source, source credentials, real-source enablement, scheduler/worker, or production cutover is approved by this validation.
