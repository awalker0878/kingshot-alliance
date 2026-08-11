# KINGDOMS-004 K4-P0 exit report

[← KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)

**Scope ID:** `KINGDOMS-004`  
**Gate:** `K4-P0` — source, tenancy, stable-ID, provenance, quarantine and automation contract lock  
**Status:** **Complete**  
**Runtime impact:** None  
**Validated P0 candidate SHA:** `89a045758c449613df9d2ebbdcb0d8e0c29e3d4c`  
**Final P0 evidence/index head:** `ff41a7519acad7d7365669188f7e717462639367`

## 1. Decision

`K4-P0` is Complete. The product scope, implementation plan, P0 decisions, and P0 security/privacy review locked the source/acquisition, tenancy, identity, provenance, quarantine, secret, and non-automation boundaries without introducing runtime code.

Completion authorized `K4-P1` / Slice A only. It did not approve any concrete production source, credential, network endpoint, observation promotion, or production cutover.

## 2. Locked source and secret boundary

Only code/configuration-allowlisted adapters are permitted. P0 does not authorize arbitrary Alliance-manager URLs/hosts/headers, generic scraping, browser/game-client automation, OCR, bots, undocumented/unapproved APIs, credential/session harvesting, or canonical raw-response archives.

Initial K4 must not persist source credentials, cookies, authorization headers, passwords/recovery material, arbitrary tenant-entered secrets, or unbounded raw external responses in Kingdoms persistence/logs/audit/outbox.

## 3. Locked identity and tenant boundary

Every subscription/batch/candidate is Alliance-owned with captured Kingdom context. Alliance-Kingdom drift fails closed without silent retargeting. Approved stable game-player/game-Alliance IDs are the only automatic target keys; names, tags, handles, source row positions, and unproven source-local IDs never auto-match.

Automatic promotion requires an existing roster entry or active tracked game-Alliance relationship. Missing/ambiguous/out-of-context targets quarantine rather than being guessed or auto-created.

## 4. Locked automation boundary

Initial K4 automation may create factual `PlayerSnapshot` / `KingdomAllianceObservation` history only through accepted K1/K3 business actions. It may not automatically create roster/tracking/membership state, mutate transfer/diplomacy/contact state, infer identity, rank/score/recommend, or expose cross-Alliance/public machine contracts.

## 5. Locked retry/provenance boundary

P0 requires distinct machine/source provenance, deterministic candidate identity across at-least-once acquisition/queue/replay, K1/K3 promotion idempotency, later distinct captures to remain append-oriented history, bounded normalized candidate fields, and quarantine/rejection before unsafe mutation.

All K4 durable events remain internal `kingdoms.*`; no public Kingdoms API scope, inbound ingestion endpoint, or webhook schema is created.

## 6. Candidate validation

Candidate `89a045758c449613df9d2ebbdcb0d8e0c29e3d4c` passed Dependency Review `31523541124`, CodeQL `31523541089`, and CI `31523541048`.

CI included frontend quality/build, PostgreSQL migrations, Pint 488 files, PHPStan/Larastan 345/345 with zero errors, 401 tests / 9,320 assertions, documentation architecture/maintenance checks, immutable production image, ephemeral staging, backup/restore, and image scan.

## 7. Final evidence-head validation

Final P0 evidence/index head `ff41a7519acad7d7365669188f7e717462639367` independently passed:

- Dependency Review `31524097319`: **success**;
- CodeQL `31524097356`: **success**; and
- CI `31524097325`: **success**.

That second protected gate made P0 Complete and authorized Slice A without changing the locked no-runtime meaning of P0 itself.

## 8. Continuing boundary

P0 evidence remains historical contract evidence. Current runtime truth is now recorded in the [Automated ingestion capability](../automated-ingestion.md) and later slice validation records.

P0 completion did not approve a concrete production source, source credentials, real-production source enablement, or real production cutover.
