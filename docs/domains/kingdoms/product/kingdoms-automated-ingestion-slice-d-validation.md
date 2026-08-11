# KINGDOMS-004 Slice D validation

[← KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)

**Status:** Complete  
**Scope:** `K4-P4` / Slice D — scheduler, cursor, retry, replay and concurrency  
**Runtime candidate:** `27855f79ba128b35edea7f82b2f6381fbf810363`  
**Accepted evidence head:** `3bf795e12a99a98c5ad71e570744743056cedd14`

## Delivered behavior

Slice D adds the generic background-processing layer around the already accepted K4-P1 staging and K4-P2/P3 promotion contracts. It does **not** add a concrete production source.

- `KingdomIngestionAcquisitionAdapter` is an optional approved-source contract; existing normalization-only adapters remain compatible.
- acquisition pages are bounded to 250 records, stable source-window IDs and an adapter-owned opaque cursor;
- adapter poll intervals are repository-defined and bounded from 60 to 86,400 seconds;
- `kingdoms:queue-ingestion` runs every minute through Laravel Scheduler with `onOneServer()` and `withoutOverlapping(10)`;
- due subscriptions are claimed transactionally before dispatch so duplicate scheduler ticks do not fan out duplicate work;
- work runs on dedicated Horizon queue `kingdoms-ingestion` with a production default of two processes and staging default of one;
- jobs are unique/overlap-protected per subscription, timeout after 120 seconds, try at most five times, and use bounded 60/300/900/3,600-second queue backoff;
- durable subscription state records next-run/claim/success/failure/circuit timing plus bounded failure codes;
- completed/partial source windows advance the cursor under row locks; failed/blocked work does not advance it;
- exact completed-window replay verifies the stored next cursor and resolves idempotently;
- acquisition/process failures use bounded failure codes and exponential backoff/circuit state rather than raw source exception text;
- retry exhaustion finalizes a still-pending batch as `failed/retry_exhausted`;
- stale Kingdom/source-version context fails closed; a pending batch is blocked rather than retargeted;
- manager replay is password-confirmed, Alliance-scoped and limited to quarantined candidates, then delegates through the accepted P2/P3 promotion actions.

Production `config/kingdoms.php` continues to have an empty adapter allowlist. There is no approved production endpoint, credential, API, scraper, OCR/browser/game-client automation or source network dependency.

## Identity, tenancy and canonical-history protection

Scheduler/worker identifiers are not authority. Every run re-resolves the tenant-owned subscription, owning Alliance, captured/current Kingdom, approved adapter key/version and existing target relationships.

Records still pass through `StageKingdomIngestionCandidate`, `PromoteKingdomIngestionPlayerSnapshot`, and `PromoteKingdomIngestionAllianceObservation`. Slice D adds no direct canonical write path, name/tag identity matching, roster/tracking creation/reactivation, correction/invalidation, diplomacy/transfer/contact mutation or recommendation/scoring automation.

At-least-once execution remains bounded by source-window uniqueness, deterministic candidate identity, promoted-record identity and the accepted K1/K3 idempotency keys.

## Security and privacy

The scheduler persists only bounded cursor/scheduling/failure state. Raw exception/source bodies, endpoints, credentials, authorization headers, cookies and private source material are not persisted to the K4 operational model or exposed by the manager page.

Manager replay requires `kingdoms.manage`, active Alliance/current-Kingdom context, recent password confirmation, current adapter approval and a quarantined candidate. Replay cannot rewrite already promoted or rejected candidates.

See [Slice D security review](../security/kingdoms-automated-ingestion-scheduler-security-review.md).

## Executable validation

Focused Slice D tests prove:

- scheduling migration down/up and the full Kingdoms migration dependency order;
- due-subscription claim happens once and dispatches the isolated job;
- one acquired page may safely stage/promote both accepted target types;
- exact completed source-window replay does not duplicate batches, candidates, player snapshots or game-Alliance observations;
- repeated source acquisition failures create bounded subscription failure/circuit state without persisting raw exception text;
- exhausted queue retries finalize a pending batch with bounded `retry_exhausted` state;
- manager replay redirects to password confirmation when assurance is stale and, after confirmation plus legitimate target repair, re-drives the existing promotion action;
- frontend presentation exposes scheduling/health/replay controls without normalized payload/source-secret disclosure.

The existing K1–K3/K4-P1–P3 architecture, tenancy, migration, accessibility, integration-exclusion and idempotency suites remain additive.

## Candidate history

Initial implementation head `becf10656aecf4071976813eabb3cc535439a9f3` had a **frontend formatting-only** failure: PHP quality/tests and the new Slice D runtime tests were green, but Prettier required one attribute-layout change in `KingdomIngestionManage.vue`.

Disposable diagnostic PR #56 was opened against the feature branch only to print Prettier's exact mutation and was closed unmerged. No diagnostic package script entered PR #54's feature history.

Final runtime candidate `27855f79ba128b35edea7f82b2f6381fbf810363` passed:

- Dependency Review `31545866277` — success;
- CodeQL `31545866288` — success;
- CI `31545866249` — success;
- Pint — 523 files;
- PHPStan/Larastan — 371/371, 0 errors;
- ParaTest/PHPUnit — 423 tests / 9,697 assertions;
- frontend ESLint/Prettier/Vue-TypeScript/build — success;
- clean PostgreSQL migrations — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image scan — success.

The first containing evidence head `fa6bb4683b8183440acc6da271873c16d8e90dc5` correctly failed the documentation architecture gate because the rewritten Kingdoms interface/testing profiles omitted frozen DCP navigation anchors. Runtime evidence remained unchanged. The docs-only repair restored those anchors at `3bf795e12a99a98c5ad71e570744743056cedd14`.

Accepted evidence head `3bf795e12a99a98c5ad71e570744743056cedd14` independently passed:

- Dependency Review `31547224197` — success;
- CodeQL `31547224301` — success;
- CI `31547224414` — success, including frontend, PHP/documentation architecture, immutable image, staging, backup/restore and image scan.

## Gate decision

`K4-P4` is **Complete**. Both the runtime candidate and the repaired containing evidence head passed the full protected gate.

`K4-P5` / Slice E is now selected, subject to the exact transition/status head that records this decision also passing Dependency Review, CodeQL and full CI. P5 may harden operations/review/retention/source-revocation, but a concrete source/network/credential approval remains a separate decision.
