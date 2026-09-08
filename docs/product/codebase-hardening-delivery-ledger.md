# Codebase hardening delivery ledger

## Resume header

- Program state: In progress.
- Exact main baseline: `7e780521295e868005ecfee5bd38b33e8215ec49`.
- Working branch: `astra/codebase-hardening`.
- Latest pushed durable checkpoint: baseline only; initial program commit is being prepared.
- Draft PR: creation pending initial push.
- Current item/state: HARD-001 / In progress.
- Most recently verified gates: fresh remote baseline and clean isolated checkout confirmed; no implementation gates claimed.
- Active files: this ledger, [program](codebase-hardening-program.md), product documentation index.
- Remaining current work: documentation-link check, initial commit/push, draft PR creation and checkpoint update.
- Known failures: baseline CI run `34239160645` and Architecture V3 run `34239160675` failed; failure details pending. Baseline CodeQL run `34239160740` passed. Visual run `34239160758` was still running at initial inspection.
- Blockers: local PHP 8.5/Composer are not installed yet; preparing runtime before PHP verification.
- Exact next action: push HARD-001 and open the draft PR, then finish HARD-002 ownership inventory and remediate HARD-003 duplicate scheduled work.
- Remaining repository-wide gates: PHP syntax/Pint/PSR-4/PHPStan/PHPUnit/Architecture/capability suites; fresh schema/routes/commands/schedules/queues; full frontend checks/build; Playwright/visual; CodeQL/dependency review/advisories; container/staging/recovery.

Checkpoint SHAs are recorded by the following documentation commit; verify that the recorded checkpoint is an ancestor of current branch HEAD. No audit area is complete solely because its paths have been inventoried.

## Work queue

### HARD-001 — Durable program and integration record

- Area: Program governance.
- Finding: No repository-owned continuation state exists for the requested repository-wide hardening program.
- Current owner: attached execution instructions.
- Intended authoritative owner: this ledger, program, branch history and draft PR.
- Rationale: work and verification must survive session termination without conversation memory.
- Remediation: establish exact baseline/branch, program, ledger, index links and early draft PR; checkpoint each coherent slice.
- State: In progress.
- Verification required: documentation links pass; remote branch and draft PR resolve to the intended baseline/checkpoint.
- Verification result: baseline fetched and isolated branch created; remaining checks pending.
- Completion evidence: pending.
- Commit SHA: pending initial commit.

### HARD-002 — Repository ownership and audit coverage inventory

- Area: All production packages, integration surfaces and gates.
- Finding: The program needs an explicit cross-repository audit map, including contradictory read-only and orchestration ownership rules.
- Current owner: architecture docs, capability documents, implementation and CI, distributed across the repository.
- Intended authoritative owner: existing Context/Workflow/ReadModel owners, corrected where evidence justifies change.
- Rationale: remediation must not create parallel authorities or claim that one capability represents whole-repository coverage.
- Remediation: enumerate packages, authoritative concepts, entry points and gates; track inspected paths and open focused material findings before changing production ownership.
- State: Planned.
- Verification required: compare inventory to tracked production directories, routes, provider registration and current workflow definitions; record gaps without claiming behavioral completion.
- Verification result: initial reading confirms capability-first contexts and thin command ownership (ADR-0016); detailed inventory pending.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-003 — Duplicate scheduler authorities

- Area: Bootstrap, console routes, Operations reminders, Communications and Gift Codes.
- Finding: `bootstrap/app.php` schedules owner callbacks while `routes/console.php` also schedules commands for Event reminders, notification delivery, Gift Code source reconciliation and source backfill. Different scheduler event/mutex identities permit duplicate work despite per-event overlap protection.
- Current owner: split between bootstrap callbacks and console-route command schedules.
- Intended authoritative owner: one central schedule registry in `routes/console.php`; application behavior remains with its owning package.
- Rationale: one invocation path per scheduled workload, discoverable command adapters, consistent distributed coordination and no duplicate business orchestration in bootstrap.
- Remediation: consolidate scheduling, retain unique bounded workloads and cadence, expose missing thin owner commands as needed, add booted-schedule regression checks and update ADR/operations documentation.
- State: Planned.
- Verification required: actual booted scheduler contains one registration per workload and valid commands with expected cadence/limits/coordination; owner command behavior, architecture, syntax/style, routes and static analysis for changed code.
- Verification result: duplicate source registrations confirmed at baseline; runtime verification pending.
- Completion evidence: `bootstrap/app.php` and `routes/console.php` at baseline.
- Commit SHA: pending.

### HARD-004 — Baseline verification failures

- Area: CI, PHP/frontend/static analysis and visual behavior.
- Finding: Current main was merged with known failing checks; baseline CI and Architecture V3 runs fail.
- Current owner: repository CI workflows and respective production packages.
- Intended authoritative owner: existing gates and corrected package implementations.
- Rationale: a failing baseline cannot be treated as acceptable completion evidence or used to weaken tests.
- Remediation: retrieve failure logs, reproduce applicable gates, assign distinct material defects new IDs, resolve them and repeat milestone gates.
- State: Planned.
- Verification required: all applicable gates pass on the final containing commit.
- Verification result: CI run `34239160645` and Architecture V3 run `34239160675` failed; details pending.
- Completion evidence: pending.
- Commit SHA: pending.

## Repository audit coverage

All rows below remain Planned until actual production paths have been traced. This table tracks audit scope, not discovered defects.

| Area | Required authority/scalability review | State |
| --- | --- | --- |
| Accounts | Identity, authentication, credential linking, sessions, registration, profile/security, deletion | Planned |
| GameWorld | Governors, Kingdoms/transfers/governance, progression facts/calculators, Gift Code facts/trust/evidence/redemption, KingdomMaps | Planned |
| Alliance | Lifecycle, membership/rank/delegation, recruitment, content, territories/hive planning | Planned |
| Operations | Events, participation, rallies, King Perks, results/Bear Hunt and reminders | Planned |
| Intelligence | Observations, evidence, ingestion, contributions, projections/signals and retention | Planned |
| Communications | Preferences/recipients, inbox, delivery channels, digests, retry/idempotency and revocation | Planned |
| Platform | Administration, integrations/API credentials, webhooks, retention and operational controls | Planned |
| Workflows/ReadModels | All cross-context orchestration, authorized dashboards, Assistant/API/notification projections | Planned |
| Infrastructure/entry points | Shared mechanisms, routes, providers/DI, console/scheduler, queues/listeners/outbox, middleware | Planned |
| Frontend | Pages, components, composables/stores, server contracts, localization, receipts and accessibility | Planned |
| Schema/verification/operations/docs | Fresh schema/indexes, concurrency/query budgets, tests, CI, image/recovery, contracts/catalogues/ADRs/ledgers | Planned |
