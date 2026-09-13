# Codebase hardening program

Status: In progress — repository-wide implementation program

Baseline: `main` at `7e780521295e868005ecfee5bd38b33e8215ec49` (2026-09-08).
Working branch: `astra/hardening-followup`.
Continuation base: `main` merge `044a6be16e54b3bc2ee5ae9ca9adf6a9c9c5923c`. GitHub records PR #163 merged on 2026-09-12 at 23:40:58 UTC while the full ledger/final gates remained incomplete. The follow-up draft retains those completion requirements and the original baseline history.
Continuation authority: [delivery ledger](codebase-hardening-delivery-ledger.md), branch history, architectural decisions and draft PR checks.

## Purpose and deployment assumptions

Reconcile every production path, verification gate and current document into one secure, maintainable, scalable modular monolith. Existing architecture explains current ownership; it may be improved when a concrete correctness, security, resilience, scalability or maintenance problem justifies a stronger boundary. This is an empty-database, not-yet-deployed application. Remove superseded implementations after migrating current callers; do not add historical backfills, aliases, fallback implementations, dual writes, transition flags, comparison modes or upgrade shims without a present architectural requirement.

This is implementation work, not an audit-only report. A passed capability suite does not establish whole-application completion. The initial inventory is not exhaustive and must be repeated after structural changes.

## Architectural target

- Context capabilities own business facts, persistence, invariants and protected writes. Each concept and business rule has one authoritative implementation.
- Workflows coordinate multiple owners through explicit Actions, Queries, immutable references and scalar identities; they do not acquire competing domain persistence or permissions.
- ReadModels compose authorized projections. They do not own business mutations or background orchestration that writes to other owners.
- Controllers, routes, console commands, jobs and listeners adapt entry points to owner behavior. Frontend components present server-authorized state and never become authorization or factual authorities.
- Shared infrastructure supplies business-neutral mechanisms, not shortcuts around context boundaries. Preserve useful abstractions and remove duplicate wrappers, dead interfaces and obsolete adapters.
- Validate current account, Governor, Alliance, membership, rank, delegation and permissions at authoritative boundaries. Reauthorize delayed work when revocation can invalidate the operation. Cross-tenant reads and writes must be difficult to express accidentally.
- Keep transactions owned and bounded. Use explicit locking/versioning for races, retry-safe operations, stable idempotency identifiers and durable outbox delivery where needed.
- External systems remain behind adapter contracts; caches, indexes, Assistant projections and notifications remain derived views of owner facts.

Material architectural changes require an ADR covering the previous design, defect, alternatives, chosen owner, rationale, security, scaling and operations implications, and superseded implementation. Update current documentation instead of preserving contradictory guidance.

## Scope and execution order

1. Establish baseline, branch, program, resumable ledger and early draft PR.
2. Inventory all Contexts, Workflows, ReadModels, infrastructure, frontend, routes, commands, schedulers, providers, policies, API/webhook surfaces, jobs, listeners, events, notifications, schema, tests, CI and documentation. Map authoritative facts and evaluate whether the boundaries themselves are appropriate.
3. Identify duplicate authorities, misplaced responsibilities and entry-point inconsistencies; implement the correct owner and remove superseded callers and implementations.
4. Reconcile tenancy/authorization and cross-context contracts, including execution-time checks in asynchronous paths.
5. Remove unsupported compatibility paths, dead code and redundant abstractions. Reconcile Actions, Queries, Services, DTOs, projectors, policies and read projections.
6. Audit every major capability for boundedness, performance, concurrency, idempotency, retry safety, failure recovery and operational diagnostics.
7. Reconcile frontend/server contracts, behavioral coverage, static analysis and type safety, CI and documentation.
8. Repeat repository-wide dependency, authority, dead-code and scalability sweeps; run all applicable gates and resolve remaining failures before closing the program.

Dependency-driven changes to this order must be recorded in the ledger. New material findings receive new stable `HARD-*` identifiers; never silently absorb them into unrelated items.

## Enterprise scalability and security review

For each major capability, trace HTTP/API/Assistant reads and writes through authoritative owners, projections and asynchronous execution. Assess query cardinality, N+1 behavior, indexes, pagination, batch/cursor progression and starvation, memory use, payload size, synchronous fan-out, queue limits, retry safety, locking, stale authority, network calls inside transactions, rate limits, backpressure, noisy neighbors, cache stampedes and failure diagnostics. Record the actual inspected path and evidence; a keyword scan is a discovery aid, not proof of safety.

Prefer bounded, resumable units, controlled concurrency and stateless services. Tests should exercise production boundaries with revoked authority, cross-Alliance/cross-account attempts, duplicate runs, retries, changing fingerprints, partial failures and concurrent execution where relevant. Do not introduce speculative distributed infrastructure or collapse useful module boundaries for file-count reduction.

## Verification requirements

Run narrow applicable checks for each coherent remediation and broad gates at milestone boundaries. Required gates are discovered from the repository's current CI and scripts, including:

- Composer manifest/lock validation, optimized strict PSR-4 loading, PHP syntax and Pint.
- PHPStan/Larastan and full PHPUnit/parallel PHP tests, Architecture V3 and all applicable capability behavior suites, including Intelligence, Gift Codes, KingdomMaps and King Perks.
- Fresh PostgreSQL schema installation, route boot/cache, command registration, scheduler uniqueness and queue behavior.
- npm lock installation, ESLint, formatting, Vue/TypeScript, accessibility, documentation, localization, action receipts, product language, geometry/export contracts, production build and asset budgets.
- Playwright/visual behavior, CodeQL, dependency advisories/review, production image build/scan, staging boot and backup/recovery, and applicable integration/query-budget checks.

Never weaken a meaningful gate to obtain green output. Remove obsolete tests only when the obsolete behavior has intentionally been removed and current behavior remains covered. Environment limitations are blockers or unverified results, never passing evidence. Record exact commands, results and containing commit SHA. If later verification invalidates a completed item, reopen it.

## Durable execution

After every independently coherent slice: verify narrowly; update the ledger and affected current architecture/product/reference/operations/frontend documentation; commit implementation, tests and documentation together using the `HARD-*` identifier; push to `astra/hardening-followup`; record the durable checkpoint before starting another slice. If interrupted, push a clearly labelled checkpoint with incomplete work and exact next action recorded.

The ledger header must record baseline, working branch, latest pushed durable checkpoint, current item/state, latest gates, active files, remaining work, known failures, blockers, exact next action and remaining repository-wide gates. Its checkpoint SHA identifies the preceding pushed implementation commit; a subsequent documentation-only checkpoint records that SHA without a circular self-reference. Confirm ancestry against branch HEAD when resuming.

Use only Planned, In progress, Blocked and Complete states for ledger items. Complete requires passed verification and recorded evidence, not merely written code. Keep the draft PR open as the integration record throughout this program.

## Acceptance and completion

Completion requires every discovered item Complete; no known material architecture, duplicate authority, compatibility, dead-code, tenancy, retry, concurrency or scalability finding; coherent current documentation; automated architecture protection where practical; and all applicable gates passing on the final containing commit. Record the final verified candidate SHA and full evidence before setting `Complete — current capability`, marking the PR ready and merging to `main` with checks and protections satisfied.

Until then, report this program as In progress. On resume, fetch the branch, read this program and ledger, inspect applicable ADRs and CI, confirm checkpoint ancestry, and continue the first non-Complete item without redoing verified work.
