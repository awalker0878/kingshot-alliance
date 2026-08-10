# KINGDOMS-003 Slice D validation

[← KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)

**Scope:** `KINGDOMS-003` Slice D / `K3-P5`  
**Status:** Candidate — protected validation pending

This record becomes validation evidence only after one exact runtime implementation SHA passes the complete protected repository gate.

## Candidate contract

Slice D adds a read-only Alliance intelligence dashboard over the validated tracking, observation, diplomacy and contact slices.

Required runtime evidence includes:

- active-Alliance tenant isolation for every aggregate/projection query;
- latest accepted observation and deterministic immediately-prior accepted observation;
- invalidated/future observation exclusion;
- missing-versus-zero preservation;
- bounded non-interpolating 7-day baseline selection from 7–14 days before `asOf`;
- bounded non-interpolating 30-day baseline selection from 30–60 days before `asOf`;
- factual power/member deltas only where both endpoint values exist;
- active tracked-alliance observation-quality and diplomacy-state headline counts;
- advisory relationship-review counts without automatic diplomacy transition;
- member-safe current/freshness/trend/diplomacy detail;
- manager-only aggregate contact availability/verification diagnostics without contact text;
- validated fixed-vocabulary filters and factual navigation sorting;
- neutral default ordering by alliance name;
- no composite/threat/desirability/target score or best/worst ranking;
- no automated recommendation, negotiation, diplomacy or transfer behavior;
- no new persistence/migration/audit/outbox side effect;
- no public Kingdoms API/webhook contract;
- accessible filters/table/status language; and
- realistic-volume batched query behavior at 120 tracked alliances / 600 observations / 120 diplomacy relationships / 60 contacts with no more than 10 SELECTs for the manager projection.

## Required protected checks

The exact validated runtime SHA must pass:

- Dependency Review;
- CodeQL;
- frontend dependency audit, ESLint, pinned Prettier, Vue/TypeScript and production build;
- Composer validation and dependency audit;
- PostgreSQL migrations;
- Pint;
- PHPStan/Larastan;
- full ParaTest/PHPUnit suite including the Slice D performance gate;
- immutable production image build;
- ephemeral staging deployment;
- backup/restore demonstration;
- image vulnerability scan; and
- cleanup.

A later documentation-only evidence commit may sit above the exact validated runtime SHA without replacing it.

`KINGDOMS-003` remains **In progress** after Slice D validation. Whole-increment hardening and acceptance remains `K3-P6`.
