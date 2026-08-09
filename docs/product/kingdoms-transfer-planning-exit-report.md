# KINGDOMS-002 exit report

[← Product and program documentation](README.md)

**Scope ID:** `KINGDOMS-002`  
**Increment:** Kingdoms transfer planning  
**Decision:** **Accepted**  
**Validated implementation SHA:** `64189559c66e15dc56ec31f9b340284c89c30e6c`  
**Baseline:** Accepted `KINGDOMS-001`  
**Production cutover:** Separate decision; not approved by this record

## Acceptance decision

`KINGDOMS-002` is Accepted in repository/product terms. The complete transfer-planning stack from `K2-P0` through `K2-P6` passed the defined domain, tenancy, security, accessibility, migration, query-shape, roster-integrity, operations and integration gates.

This acceptance does **not** authorize a real production cutover. Production deployment remains governed by the separate production-launch approval record and external operational evidence.

## Accepted capability

The accepted increment provides alliance-owned transfer planning with:

- one captured-home-Kingdom transfer cycle lifecycle (`draft`, `open`, `locked`, `closed`, `cancelled`);
- incoming, outgoing and staying participant intent under active-Alliance tenancy;
- outgoing destination planning without mutating neutral player identity;
- alliance-owned transfer groups with same-alliance coordinators;
- coordinator assignment as workflow responsibility only, never authorization;
- manual, explainable readiness and blocker workflows with preserved transition history;
- member-safe versus manager-only transfer presentation;
- explicit, per-participant completion only after a plan is locked and readiness is confirmed;
- incoming completion through accepted roster create/link behavior;
- outgoing completion through accepted mark-left behavior;
- staying completion with no roster lifecycle mutation;
- durable idempotent completion records;
- preserved player snapshot history with no fabricated snapshot at completion;
- attributable audit and internal transactional-outbox evidence; and
- no public Transfer/Kingdoms API or generic external webhook contract.

## Whole-increment hardening evidence

`K2-P6` added acceptance evidence rather than new product behavior.

The final hardening layer includes:

- an end-to-end transfer-cycle test spanning cycle creation, direction/destination, groups, coordinator, blockers/readiness, explicit completion, roster handoff, ordinary-member privacy, coordinator privilege regression, tenant isolation, audit and outbox evidence;
- a realistic-volume query-shape test with **150 transfer participants and 20 transfer groups**, including readiness history, blockers and completion projections, with a bounded SELECT-query budget;
- whole-increment schema/index guards across plan, participant, group, readiness/blocker and completion persistence;
- accessibility guards covering plan, group, readiness and completion surfaces;
- public API/scope exclusion checks;
- wildcard-webhook exclusion checks for representative `kingdoms.transfer_*` events; and
- explicit guards against eligibility scoring, transfer-resource/pass optimization, bulk completion and automatic transfer execution.

## Security and privacy review

The final review confirms:

- global `Kingdom` and `KingdomPlayer` identity remain neutral reference data, never an authorization boundary;
- all transfer plans, participants, groups, coordinator references, readiness, blockers, manager notes, completion records and handoff provenance remain alliance-owned;
- submitted plan/participant/group/roster/membership identifiers are re-resolved beneath the active Alliance and transfer-plan boundary;
- sharing a Kingdom, player identity or destination never grants cross-alliance visibility;
- ordinary members cannot access management/readiness/completion mutation surfaces;
- coordinator assignment does not grant `kingdoms.manage`;
- recent password confirmation remains required for privileged transfer mutations;
- display name alone never auto-merges incoming game identity;
- stable game-player identity must agree where an existing roster result is explicitly selected;
- home-Kingdom drift fails closed before transfer mutations/handoff;
- private blocker text, manager notes and richer completion provenance remain manager-only and are not copied into generic event metadata;
- completion retries are idempotent and cannot duplicate roster lifecycle changes; and
- transfer events remain internal and excluded from generic external webhook fan-out.

See [KINGDOMS-002 whole-increment security review](../security/kingdoms-transfer-planning-security-review.md).

## Accessibility review

The accepted first-party transfer workflow retains:

- semantic `main` landmarks and primary headings;
- native interactive controls instead of role-emulated buttons;
- explicit labels for management inputs;
- grouped readiness controls using `fieldset`/`legend`;
- programmatic participant-specific readiness, blocker and roster-result label associations; and
- horizontal overflow for narrow-screen tabular transfer views.

The repository/source-level acceptance evidence is recorded in [KINGDOMS-002 accessibility review](kingdoms-transfer-planning-accessibility.md). Manual browser/assistive-technology smoke testing remains release QA guidance and is not falsely represented as performed by this repository gate.

## Migration and data-integrity review

The complete Kingdoms migration round-trip validates the transfer dependency chain:

1. `2026_08_09_090000_create_transfer_plans.php`
2. `2026_08_09_100000_create_transfer_participants.php`
3. `2026_08_09_110000_create_transfer_groups.php`
4. `2026_08_09_120000_create_transfer_readiness_and_blockers.php`
5. `2026_08_09_130000_create_transfer_completions.php`

Rollback/reapply returns to and rebuilds from the accepted `KINGDOMS-001` baseline without compatibility shims or dormant future-schema placeholders.

Completion preserves accepted roster and snapshot invariants: incoming/outgoing handoff delegates to accepted roster actions, staying is a roster no-op, retries are idempotent and completion does not fabricate player observations.

## Query and index review

The whole-increment performance gate exercises 150 participants and 20 groups with eager-loaded completion, blocker, readiness-history and coordinator projections. The gate asserts a bounded SELECT count so query volume does not scale linearly with participant count.

Schema guards also retain tenant/plan-oriented indexes for participant, group, readiness/blocker and completion lookup plus the unique completion-per-participant idempotency boundary.

## Protected validation

The exact validated implementation SHA `64189559c66e15dc56ec31f9b340284c89c30e6c` passed:

- Dependency Review run `31337595942`: **success**;
- CodeQL run `31337595933`: **success**;
- CI run `31337595937`: **success**.

The successful CI attempt verified:

- frontend dependency audit, ESLint, pinned Prettier, Vue/TypeScript checks and production build;
- PHP dependency audit: no security vulnerability advisories found;
- PostgreSQL migrations through the complete `KINGDOMS-002` chain;
- Pint: **439 files passed**;
- PHPStan/Larastan: **314 files, 0 errors**;
- ParaTest/PHPUnit: **299 tests, 3414 assertions**;
- immutable production-image build;
- ephemeral staging deployment;
- backup/restore demonstration; and
- image vulnerability scan.

The first CI attempt encountered a transient Packagist advisory-endpoint HTTP/2 `502` before migrations/tests. No dependency, code or gate was changed. The failed CI jobs were rerun unchanged; the dependency audit then completed successfully and the entire protected gate passed. The external-service failure therefore did not alter acceptance criteria or implementation content.

## Explicitly deferred / absent

Acceptance does not add or approve:

- transfer marketplace or public player advertising;
- automated stay/leave decisions;
- destination/player rankings or recommendations;
- inferred readiness or transfer eligibility;
- transfer-pass/ticket/resource optimization;
- scraping, OCR, bots or undocumented/unapproved game APIs;
- bulk completion;
- automatic in-game transfer execution;
- diplomacy/NAP/ally/rival intelligence;
- cross-alliance transfer-plan visibility or rankings;
- AI/punitive player scoring; or
- public Kingdoms/transfer API or webhook contracts.

Those capabilities require separately approved increment records if pursued.

## Final state

`KINGDOMS-002` is **Accepted**. Its implementation is current repository/product capability once this acceptance stack is promoted to `main`.

A real production launch remains separately **not yet approved** and must not be inferred from this product acceptance record.
