# KINGDOMS-004 K4-P0 exit report

[← KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)

**Scope ID:** `KINGDOMS-004`  
**Gate:** `K4-P0` — source, tenancy, stable-ID, provenance, quarantine and automation contract lock  
**Status:** Candidate acceptance complete — final evidence/index head validation pending  
**Runtime impact:** None  
**Validated P0 candidate SHA:** `89a045758c449613df9d2ebbdcb0d8e0c29e3d4c`

## 1. Decision

The `KINGDOMS-004` planning/P0 contract is accepted at the candidate level.

The product scope, implementation plan, P0 decisions, and P0 security/privacy review agree on the future ingestion boundary without introducing any K4 runtime schema, routes, source adapter, worker, scheduler, credentials, automated observation promotion, or public machine contract.

P0 becomes finally **Complete** only when the exact evidence/index head containing this exit record independently passes protected Dependency Review, CodeQL, and complete CI. When that head is green, `K4-P1` / Slice A is authorized without another P0-only transition commit.

## 2. Locked source boundary

P0 accepts only a code/configuration-allowlisted adapter model.

It does not authorize:

- arbitrary Alliance-manager supplied URLs, hosts, methods, headers, or network destinations;
- generic HTTP/HTML scraping;
- browser or game-client automation;
- OCR/screenshot ingestion;
- Discord/data-collection bots;
- undocumented/unapproved Kingshot APIs;
- credential/session-cookie harvesting; or
- reverse-engineered/private source access without explicit source approval.

No concrete production source or authentication secret is approved by this gate.

## 3. Locked identity and tenant boundary

P0 preserves the accepted K1–K3 model:

- every future subscription/batch/candidate is Alliance-owned;
- subscription Kingdom context is captured and revalidated;
- Alliance-Kingdom drift fails closed without silent retargeting;
- approved stable game-player/game-Alliance IDs are the only automatic target keys;
- names, tags, handles, row positions, and unproven source-local IDs never auto-match;
- automatic promotion requires an existing roster entry or active tracked game-Alliance relationship; and
- missing/ambiguous/out-of-context targets quarantine rather than being guessed or auto-created.

## 4. Locked automation boundary

Initial K4 automation is observations-only.

Future promotion may create factual history only through the existing K1/K3 recording contracts for:

- `PlayerSnapshot`; and
- `KingdomAllianceObservation`.

P0 explicitly rejects automatic roster enrollment/tracking creation, membership linking, transfer state changes, diplomacy mutation/inference, contact mutation, scoring/ranking, recommendations, negotiation, or other automated player/alliance decisions.

Promotion must delegate through accepted Kingdoms business actions rather than direct table/model writes.

## 5. Locked provenance, retry and quarantine boundary

P0 requires:

- distinct machine/source provenance rather than fake User actors;
- deterministic candidate identity across at-least-once acquisition/queue/replay;
- existing K1/K3 observation idempotency on promotion;
- later genuinely distinct capture times to remain append-only history;
- bounded normalized candidate fields;
- quarantine/rejection before unsafe mutation; and
- explicit, attributable manager replay/reject/configuration actions where later slices implement them.

## 6. Locked secret, network and external-contract boundary

Initial K4 does not persist source API keys/tokens, cookies, authorization headers, passwords/recovery material, arbitrary tenant-entered secrets, or canonical raw external response bodies in Kingdoms state, logs, audit, or outbox payloads.

Concrete networked adapters remain responsible for explicit redirect/DNS/private-address/metadata/management/TLS/timeout/rate/egress review before approval.

All future K4 durable events remain internal `kingdoms.*` events. P0 adds no public Kingdoms API scope, inbound ingestion endpoint, or webhook schema and does not weaken the existing Integrations Kingdoms exclusion.

## 7. Candidate validation

Exact candidate head:

`89a045758c449613df9d2ebbdcb0d8e0c29e3d4c`

Protected workflows:

- Dependency Review `31523541124` — **success**;
- CodeQL `31523541089` — **success**;
- CI `31523541048` — **success**.

CI evidence includes:

- frontend dependency/quality/build — success;
- PostgreSQL migrations — success;
- Pint — **488 files**;
- PHPStan/Larastan — **345/345, 0 errors**;
- ParaTest/PHPUnit — **401 tests / 9,320 assertions**;
- repository P1–P7 documentation architecture/maintenance checks — success;
- local Markdown/link/navigation checks — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success; and
- image vulnerability scan — success.

## 8. Candidate-scope verification

The accepted candidate changes only Kingdoms product/security planning documentation and indexes.

It contains no K4 runtime migration, model, action, query, controller, route, frontend runtime surface, job, scheduler registration, queue partition, source adapter, external endpoint configuration, credential storage, observation promotion path, or public API/webhook change.

The living Kingdoms runtime contract therefore remains accurately limited to accepted K1–K3 behavior until Slice A actually introduces runtime foundation.

## 9. Final P0 gate

The exact branch head containing this exit report and its navigation entry must independently pass:

1. Dependency Review;
2. CodeQL; and
3. complete CI, including documentation/architecture/maintenance checks plus immutable image, staging, backup/restore, and scan.

If that head fails, P0 remains at candidate acceptance and only the evidence/index defect may be repaired.

If it passes, **`K4-P0` is Complete** and `K4-P1` / Slice A becomes the current implementation gate.

Completion of P0 still does not approve a concrete production source, source credentials, real-production source enablement, or real production cutover.