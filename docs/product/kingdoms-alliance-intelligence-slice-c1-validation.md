# KINGDOMS-003 Slice C1 validation

[← KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)

**Scope:** `KINGDOMS-003` Slice C1 / `K3-P3`  
**Status:** Candidate — protected validation pending

This record becomes validation evidence only after one exact runtime implementation SHA passes the complete protected repository gate.

## Candidate contract

Slice C1 adds explicit human-maintained diplomacy/NAP lifecycle and append-oriented transition history on top of validated Slice A tracking and Slice B observations.

Required runtime evidence includes:

- state vocabulary exactly `unknown`, `neutral`, `friendly`, `nap`, `ally`, `rival`;
- one current tenant/tracking-scoped relationship plus append-oriented transition history;
- explicit manager-only transition action with no observation/expiry/transfer inference;
- exact-current-meaning idempotency;
- same-state metadata change preserving prior history;
- advisory review/expiry with derived review-due state and no automatic transition;
- manager-private terms/rationale and actor/history detail;
- member-safe state/review indicator only;
- active-Alliance/object-ID/Kingdom-drift/archive fail-closed behavior;
- recent-password confirmation for mutation;
- private terms/rationale excluded from audit/outbox payloads;
- internal-only `kingdoms.diplomacy_transitioned` event;
- PostgreSQL migration rollback/reapply through the accepted K2/K1 chain;
- frontend accessibility/type/build validation; and
- explicit absence of contacts, scoring/ranking, ingestion, public API/webhook and automatic transfer behavior.

## Required protected checks

The exact validated runtime SHA must pass:

- Dependency Review;
- CodeQL;
- frontend dependency audit, ESLint, pinned Prettier, Vue/TypeScript and production build;
- Composer validation and dependency audit;
- PostgreSQL migrations;
- Pint;
- PHPStan/Larastan;
- full ParaTest/PHPUnit suite;
- immutable production image build;
- ephemeral staging deployment;
- backup/restore demonstration;
- image vulnerability scan; and
- cleanup.

Do not use diagnostic/formatter heads as the validation anchor. Later documentation-only status commits may sit above the validated runtime SHA without replacing it.

`KINGDOMS-003` remains **In progress** after Slice C1 validation; contacts remain `K3-P4`, descriptive intelligence remains `K3-P5`, and whole-increment acceptance remains `K3-P6`.
