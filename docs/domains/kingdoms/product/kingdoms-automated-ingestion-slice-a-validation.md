# KINGDOMS-004 Slice A validation

[← KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)

**Scope:** `KINGDOMS-004` Slice A / `K4-P1`  
**Status:** **Validated runtime candidate** — authoritative when the evidence head containing this record is protected-green  
**Validated runtime SHA:** `5a37731374e9fa7aef591b7b1badd9cc13603e2c`  
**Baseline:** completed `K4-P0` evidence head `ff41a7519acad7d7365669188f7e717462639367`

## Validated capability

Slice A validates the generic tenant-scoped ingestion control plane:

- repository/operator code/config adapter registry and allowlist;
- intentionally empty production adapter allowlist;
- Alliance-owned subscription with captured Kingdom context and `active`/`paused`/`disabled` lifecycle;
- Alliance/subscription-owned batch with deterministic source-window identity, bounded counters/status/timing/failure code;
- Alliance/subscription/batch-owned normalized candidate with exactly `player_snapshot` / `alliance_observation` target kinds;
- bounded normalized factual payload and SHA-256 payload/identity hashes;
- deterministic exact-retry behavior for source windows/candidates;
- quarantine for missing stable game identity rather than name/tag/handle guesswork;
- explicit manager rejection of quarantined candidates;
- manager-only status/control surface with no URL/secret editor and no normalized-payload disclosure;
- `kingdoms.manage` plus recent password confirmation for human mutations;
- cross-tenant submitted-ID re-resolution and Alliance-Kingdom drift failure;
- attributable audit/internal outbox evidence with bounded safe metadata; and
- explicit proof that Slice A creates no `PlayerSnapshot` or `KingdomAllianceObservation` business history.

Slice A contains no real source adapter, external acquisition, scheduler/worker, cursor retry loop, replay/promotion action, automatic roster/tracking creation, transfer/diplomacy/scoring automation, cross-Alliance sharing, public Kingdoms API, or public webhook.

## Protected validation

Exact runtime SHA `5a37731374e9fa7aef591b7b1badd9cc13603e2c` passed:

- Dependency Review `31533284318`: **success**;
- CodeQL `31533284195`: **success**; and
- CI `31533284398`: **success**.

CI evidence on that exact SHA:

- frontend dependency audit, ESLint, pinned Prettier, Vue/TypeScript checks and production build: success;
- PHP dependency audit: success / no security vulnerability advisories;
- PostgreSQL migrations, including `2026_08_11_190000_create_kingdom_ingestion_foundation.php`: success;
- Pint: **509 files passed**;
- PHPStan/Larastan: **363/363 files / 0 errors**;
- ParaTest/PHPUnit: **407 tests / 9,466 assertions**;
- immutable production image build/identification: success;
- ephemeral staging deployment: success;
- backup/restore demonstration: success;
- image vulnerability scan: success; and
- staging cleanup: success.

## Security and architecture acceptance notes

Feature/architecture evidence covers adapter allowlist enforcement, absence of URL/credential/raw-response persistence columns, bounded target/payload validation, stable-ID-only automation boundary, current-Kingdom drift, member/manager/password boundaries, cross-tenant ID tampering, audit/outbox safety, accessibility, K3 no-automation non-regression, and public API/webhook exclusion.

The historical Kingdom migration round-trip was extended in dependency order so K4 tables roll down before older Kingdoms tables and reapply after them; the runtime FKs remain restrictive rather than weakened for the test.

The production adapter list is empty, so network/DNS/redirect/TLS/rate/secret-manager behavior is intentionally not claimed by this validation. Those are source-approval and later K4 slice requirements.

## Diagnostic/repair history

During candidate validation, temporary CI diagnostics were used only to expose the repository-pinned Prettier/Pint changes. Diagnostic PR #55 was closed without merge. Standard `package.json` / `composer.json` checks are restored on the validated runtime SHA; no diagnostic command or sentinel file remains in the feature diff.

The final runtime fixes were limited to formatter/static-analysis conformance and test-contract updates. The last candidate commit changed only tests to include K4 migration dependency order, explicitly expire password confirmation in the relevant test, and scope the historical K3 no-ingestion route guard to the K3 route surface.

## Evidence-head gate and next slice

This validation record and the updated living Kingdoms contracts form a second evidence/status head. That containing head must independently pass Dependency Review, CodeQL, and complete CI before `K4-P1` is treated as finally complete for continuation control.

When the evidence head is protected-green, `K4-P2` / Slice B is authorized. Slice B is limited to stable-ID resolution of an existing Alliance roster target and delegated player-snapshot promotion with machine/source provenance. `K4-P3` and later slices remain blocked by their predecessor gates.

Repository validation still does not approve a concrete production source, source credentials, real-production source enablement, or real production cutover.
