# Codebase hardening delivery ledger

## Resume header

- Program state: In progress.
- Exact main baseline: `7e780521295e868005ecfee5bd38b33e8215ec49`.
- Working branch: `astra/codebase-hardening`.
- Latest pushed durable checkpoint: `93782c8d5710b7d6786646679060d7244ddf6e24`.
- Draft PR: [#163](https://github.com/awalker0878/kingshot-alliance/pull/163).
- Current item/state: HARD-003 / In progress.
- Most recently verified gates: documentation links (231 files) and diff whitespace pass; remote checkpoint and draft PR match baseline.
- Active files: scheduler/command/provider ownership and regression coverage; [inventory](../architecture/codebase-hardening-inventory.md).
- Remaining current work: consolidate all three schedule sites, implement missing owner command adapters and verify the booted schedule.
- Known failures: baseline CI run `34239160645` and Architecture V3 run `34239160675` failed; failure details pending. Baseline CodeQL run `34239160740` passed. Visual run `34239160758` was still running at initial inspection.
- Blockers: local PHP 8.5/Composer are not installed yet; preparing runtime before PHP verification.
- Exact next action: finish HARD-003 and its narrow gates, push a coherent slice, then address HARD-005 notification workflow ownership.
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
- State: Complete.
- Verification required: documentation links pass; remote branch and draft PR resolve to the intended baseline/checkpoint.
- Verification result: documentation links pass (231 files), diff whitespace passes, remote branch and draft PR match exact baseline and checkpoint.
- Completion evidence: draft PR #163; initial program and ledger are remote and readable.
- Commit SHA: `93782c8d5710b7d6786646679060d7244ddf6e24`.

### HARD-002 — Repository ownership and audit coverage inventory

- Area: All production packages, integration surfaces and gates.
- Finding: The program needs an explicit cross-repository audit map, including contradictory read-only and orchestration ownership rules.
- Current owner: architecture docs, capability documents, implementation and CI, distributed across the repository.
- Intended authoritative owner: existing Context/Workflow/ReadModel owners, corrected where evidence justifies change.
- Rationale: remediation must not create parallel authorities or claim that one capability represents whole-repository coverage.
- Remediation: enumerate packages, authoritative concepts, entry points and gates; track inspected paths and open focused material findings before changing production ownership.
- State: Complete.
- Verification required: compare inventory to tracked production directories, routes, provider registration and current workflow definitions; record gaps without claiming behavioral completion.
- Verification result: tracked directory/provider/route/workflow inventory confirms seven contexts, 43 capabilities, 29 ReadModel packages, three Workflows, 35 command classes, 20 route files and 14 workflow files. All audit areas are retained in the coverage table; concrete defects are HARD-003 and HARD-005–007.
- Completion evidence: [ownership inventory](../architecture/codebase-hardening-inventory.md). This completes inventory only, not the repository-wide behavioral audit.
- Commit SHA: recorded at next checkpoint.

### HARD-003 — Duplicate scheduler authorities

- Area: Bootstrap, console routes, Operations reminders, Communications and Gift Codes.
- Finding: `bootstrap/app.php` schedules owner callbacks while `routes/console.php` also schedules commands for Event reminders, notification delivery, Gift Code source reconciliation and source backfill. Different scheduler event/mutex identities permit duplicate work despite per-event overlap protection.
- Current owner: split between bootstrap callbacks and console-route command schedules.
- Intended authoritative owner: one central schedule registry in `routes/console.php`; application behavior remains with its owning package.
- Rationale: one invocation path per scheduled workload, discoverable command adapters, consistent distributed coordination and no duplicate business orchestration in bootstrap.
- Remediation: consolidate scheduling, retain unique bounded workloads and cadence, expose missing thin owner commands as needed, add booted-schedule regression checks and update ADR/operations documentation.
- State: In progress.
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

### HARD-005 — ReadModel notification orchestration

- Area: ReadModel notification orchestration.
- Finding: CommandOverview and IntelligenceSignals ReadModels contain queue Actions, publishers and command adapters that create Communications delivery state. ADR-0016 permits this while core architecture requires read-only projections.
- Current owner: ReadModels/CommandOverview and ReadModels/IntelligenceSignals.
- Intended authoritative owner: Workflows/NotificationDelivery orchestration; Communications persistence; ReadModel projections.
- Rationale: Enforce read-only composition without moving source semantics into generic Communications.
- Remediation: Move orchestration, publishers and CLI adapters/providers; migrate callers/tests/docs and strengthen no-write dependency checks.
- State: Planned.
- Verification required: Production notification behavior including duplicate runs, revoked authority, cross-Alliance isolation, command registration, architecture and static analysis.
- Verification result: baseline source/CI inspection confirms finding; remediation pending.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-006 — Baseline formatting failures

- Area: Baseline formatting failures.
- Finding: Pint fails four progression PHP files; Prettier fails GovernorProgressionScreenshotIntake.vue. These stop CI before later checks.
- Current owner: Progression Queries, Roster validator, Progression ReadModel and screenshot component.
- Intended authoritative owner: Same owners.
- Rationale: Apply established formatter output without changing product behavior or weakening gates.
- Remediation: Run repository formatters on reported files and repeat style gates.
- State: Planned.
- Verification required: Pint and frontend formatting pass; diff contains formatting/import cleanup only.
- Verification result: baseline source/CI inspection confirms finding; remediation pending.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-007 — Progression absence/integrity contract coverage

- Area: Progression absence/integrity contract coverage.
- Finding: Architecture V3 fails two source-string assertions after progression diagnostics changed; inspect production absence/corruption semantics before choosing remediation.
- Current owner: ProgressionDatasetAbsenceBoundaryV3Test and GameWorld/Progression.
- Intended authoritative owner: GameWorld/Progression factual boundary and authorized planner projection.
- Rationale: Absence and corrupt published facts must remain distinguishable; tests should validate behavior rather than exact incidental diagnostic text.
- Remediation: Trace no-release, unpublished-release, malformed-release and checksum failures; implement meaningful regression coverage and correct any production defect.
- State: Planned.
- Verification required: Architecture V3 and dataset/planner behavior pass with absent and invalid release fixtures.
- Verification result: baseline source/CI inspection confirms finding; remediation pending.
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
