# Codebase hardening delivery ledger

## Resume header

- Program state: In progress.
- Exact main baseline: `7e780521295e868005ecfee5bd38b33e8215ec49`.
- Working branch: `astra/codebase-hardening`.
- Latest pushed durable checkpoint: `d8f134da75d2485d7775c4e13712b1a8ff9b3620`.
- Draft PR: [#163](https://github.com/awalker0878/kingshot-alliance/pull/163).
- Current item/state: HARD-005 / In progress (CI behavior verification pending).
- Most recently verified gates: scheduler/command ownership (4 tests, 646 assertions); architecture verifier; syntax and Pint on 16 changed PHP files; documentation links (233 files); 35 scheduled commands and 490 routes boot. Full PHPStan finds one unrelated evidence-routing defect (HARD-008).
- Active files: HARD-007 release availability/integrity behavior tests and architectural boundary checks; ledger.
- Remaining current work: production move complete; verify migrated database-backed notification behavior in CI after HARD-006–008 unblock the baseline gates.
- Known failures at resumed HEAD `9b39d476`: CI `34242100412` and Intelligence `34242099956` fail formatting (HARD-006); Architecture `34242099981` fails two obsolete dataset diagnostic assertions (HARD-007). Full PHPStan remains blocked by HARD-008. Visual `34242100095`, CodeQL `34242100035`, Dependency Review `34242100043`, Gift Code `34242100200`, King Perks `34242100097`, and KingdomMaps `34242100142` pass.
- Blockers: local PostgreSQL/Redis services are unavailable for database/queue integration tests; This resumed workspace uses PHP 8.5.8 with locked Composer/frontend dependencies installed; local PostgreSQL/Redis remain unavailable. Use CI for remaining service-dependent gates. Checkpoints are published through the authorized GitHub connection; checkpoint commits are published through the GitHub connection with exact tree verification.
- Exact next action: publish HARD-007, then resolve HARD-008 routing plus HARD-010 structured-intake behavior and HARD-011 topology/prerequisite contracts. Return to HARD-005 verification after CI prerequisites pass.
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
- Commit SHA: `aa8928b0cbf67bddeb252f4692bf5c0b4c59b420`.

### HARD-003 — Duplicate scheduler authorities

- Area: Bootstrap, console routes, Operations reminders, Communications and Gift Codes.
- Finding: `bootstrap/app.php` schedules owner callbacks while `routes/console.php` also schedules commands for Event reminders, notification delivery, Gift Code source reconciliation and source backfill. Different scheduler event/mutex identities permit duplicate work despite per-event overlap protection.
- Current owner: split between bootstrap callbacks and console-route command schedules.
- Intended authoritative owner: one central schedule registry in `routes/console.php`; application behavior remains with its owning package.
- Rationale: one invocation path per scheduled workload, discoverable command adapters, consistent distributed coordination and no duplicate business orchestration in bootstrap.
- Remediation: consolidate scheduling, retain unique bounded workloads and cadence, expose missing thin owner commands as needed, add booted-schedule regression checks and update ADR/operations documentation.
- State: Complete.
- Verification required: actual booted scheduler contains one registration per workload and valid commands with expected cadence/limits/coordination; owner command behavior, architecture, syntax/style, routes and static analysis for changed code.
- Verification result: new regression failed both tests before remediation; booted scheduler and command ownership now pass (4 tests, 646 assertions), including actual Symfony argument binding/validation for all 35 workloads. 490 routes boot, architecture verifier passes, changed-file PHP syntax/Pint pass. Full PHPStan reports only unrelated HARD-008. No owner Action business behavior changed; service-dependent capability suites remain part of milestone verification.
- Completion evidence: `SchedulerOwnershipV3Test`, owner commands/providers, ADR-0017 and updated background/source-acquisition runbooks.
- Commit SHA: `d0bdb8d662aac5803a361003ee28f5f72e0e2dd2`.

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
- State: In progress.
- Verification required: Production notification behavior including duplicate runs, revoked authority, cross-Alliance isolation, command registration, architecture and static analysis.
- Verification result: architecture verifier passes, scheduler/provider tests pass (4 tests, 642 assertions), no obsolete writer classes/aliases remain. Migrated real notification test cannot run locally: PostgreSQL connection refused at 127.0.0.1:5432. CI behavior verification is required before completion.
- Completion evidence: ADR-0018, migrated Workflow classes/tests, owner semantic APIs and strengthened ReadModel write-path enforcement; runtime behavioral evidence pending.
- Commit SHA: `9b39d47609bbf9b7938d32b078106a5e243f394a`; item remains In progress.

### HARD-006 — Baseline formatting failures

- Area: Baseline formatting failures.
- Finding: Pint fails four progression PHP files; Prettier fails GovernorProgressionScreenshotIntake.vue. These stop CI before later checks.
- Current owner: Progression Queries, Roster validator, Progression ReadModel and screenshot component.
- Intended authoritative owner: Same owners.
- Rationale: Apply established formatter output without changing product behavior or weakening gates.
- Remediation: Run repository formatters on reported files and repeat style gates.
- State: Complete.
- Verification required: Pint and frontend formatting pass; diff contains formatting/import cleanup only.
- Verification result: all four reported PHP files pass Pint after formatter-only changes; full frontend `npm run format:check` passes; documentation links pass (234 files); whitespace diff passes. Full-repository `vendor/bin/pint --test` passes (1,829 PHP files).
- Completion evidence: formatter diff contains whitespace, PHPDoc alignment and one unused import removal only.
- Commit SHA: `d8f134da75d2485d7775c4e13712b1a8ff9b3620`.

### HARD-007 — Progression absence/integrity contract coverage

- Area: Progression absence/integrity contract coverage.
- Finding: Architecture V3 fails two source-string assertions after progression diagnostics changed; inspect production absence/corruption semantics before choosing remediation.
- Current owner: ProgressionDatasetAbsenceBoundaryV3Test and GameWorld/Progression.
- Intended authoritative owner: GameWorld/Progression factual boundary and authorized planner projection.
- Rationale: Absence and corrupt published facts must remain distinguishable; tests should validate behavior rather than exact incidental diagnostic text.
- Remediation: Trace no-release, unpublished-release, malformed-release and checksum failures; implement meaningful regression coverage and correct any production defect.
- State: Complete.
- Verification required: Architecture V3 and dataset/planner behavior pass with absent and invalid release fixtures.
- Verification result: reproduced both obsolete assertions before the repair. Twelve new filesystem-backed availability/controller cases pass (47 assertions): empty catalogue, every unpublished status, published version ordering, malformed JSON, invalid schema/manifest/status, changed checksum and missing pinned release. Combined Architecture/Progression run: 107/109 pass (67,124 assertions); all Architecture and availability cases pass. Two pre-existing topology/prerequisite expectation defects are separately tracked as HARD-011. Changed-test Pint passes.
- Completion evidence: ProgressionDatasetAvailabilityV3Test exercises real release loading and planner responses without mocking release behavior or mutating checked-in datasets; architecture enforcement retains the typed-absence and early-scope boundary.
- Commit SHA: recorded by the next checkpoint after publication.

### HARD-008 — Missing Governor progression evidence routing

- Area: Intelligence/Evidence extraction.
- Finding: PHPStan reports non-exhaustive match in `RoutedEvidenceExtractor` for `GovernorBuildings`, `GovernorAcademyResearch` and `GovernorWarAcademyResearch`; these evidence kinds can fail at extraction time.
- Current owner: Intelligence/Evidence routing.
- Intended authoritative owner: same router and the existing Governor progression extractor.
- Rationale: one complete evidence-kind dispatch contract; supported intake kinds must reach their authoritative extractor.
- Remediation: map all supported progression kinds, verify actual extraction behavior and keep unknown/unsupported input semantics explicit.
- State: Planned.
- Verification required: exhaustive enum/router regression, production progression extraction behavior and full PHPStan.
- Verification result: local full PHPStan reports exactly this one error; no suppression added.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-009 — Personal reminder stale-snapshot race

- Area: GameWorld/GiftCodes personal reminders.
- Finding: `QueueDueGiftCodeReminders` queues from an unlocked account-state snapshot, then clears any current past-due `remind_at` under lock without checking it still matches the reminder processed. Concurrent rescheduling can be cleared by the earlier sweep.
- Current owner: `QueueDueGiftCodeReminders`.
- Intended authoritative owner: same owner Action with an explicit reminder occurrence/claim boundary.
- Rationale: stale processing must not erase a newer user intention; retries and parallel runs need stable occurrence identity.
- Remediation: reproduce the reschedule race, revalidate/lock occurrence state at mutation and preserve new reminders; verify retry/idempotency semantics without network calls under a broad transaction.
- State: Planned.
- Verification required: race regression preserving a rescheduled reminder, duplicate sweep/retry behavior and owner authorization.
- Verification result: snapshot-to-lock path confirmed by source inspection; behavioral reproduction pending.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-010 — Structured progression screenshot pipeline is incomplete

- Area: Intelligence/Evidence and Roster, Governor screenshot UI and contracts.
- Finding: Three new building/Academy/War Academy kinds have schemas, review controls and destination dispatch, but no classifier scores or extractor branches; the intake path cannot produce reviewable evidence. The current screenshot contract still describes only six kinds.
- Current owner: GovernorProgressionEvidenceClassifier/Extractor and existing normalization/review pipeline.
- Intended authoritative owner: same Evidence pipeline; Roster owns accepted states and GameWorld owns pinned reference facts.
- Rationale: a registered kind and a passing enum match do not establish an executable capability; machine extraction must remain independent of the user-selected kind and preserve unknown fields.
- Remediation: complete explicit classifier/extractor contracts with fixtures, trace normalized review through typed destination validation, reconcile current documentation and test production routing/review behavior.
- State: Planned.
- Verification required: routed classification/extraction including mismatch/ambiguity/unknown fields; pinned-dataset review and destination behavior; full Intelligence, architecture, style and static analysis.
- Verification result: missing classifier/extractor branches confirmed during HARD-008 trace; behavioral remediation pending.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-011 — Progression topology and prerequisite contract drift

- Area: GameWorld/Progression topology and ReadModels/Progression planner verification.
- Finding: Broader checks expose two existing failing assertions: the planner family list predates Hero Widget, troop tiers and VIP; prerequisite expectations predate the structured observed/unknown prerequisite evaluator.
- Current owner: ProgressionTopologyV3Test and ProgressionPlannerQueryV3Test, current topology/evaluator implementations.
- Intended authoritative owner: GameWorld factual topology and authorized ReadModel prerequisite evaluation.
- Rationale: current features require evidence of factual ownership and unknown-state semantics; tests must protect behavior without retaining obsolete exact payloads.
- Remediation: verify new family/state sources and prerequisite evaluation against pinned observations, update valid contracts and add missing behavioral coverage for known/unknown/revoked or stale inputs where relevant.
- State: Planned.
- Verification required: complete GameWorld/Progression and ReadModels/Progression suites; architecture and static analysis.
- Verification result: two failures reproduced in 109-test combined run; other 107 tests pass.
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
| Infrastructure/entry points | Scheduler registration/commands verified by HARD-003; route authorization, shared mechanisms, queues/listeners/outbox and middleware audit remain | In progress |
| Frontend | Pages, components, composables/stores, server contracts, localization, receipts and accessibility | Planned |
| Schema/verification/operations/docs | Fresh schema/indexes, concurrency/query budgets, tests, CI, image/recovery, contracts/catalogues/ADRs/ledgers | Planned |

## Execution adjustments

- After HARD-003, HARD-005 implements the ownership move but remains In progress until database-backed behavior passes. Local PHP/architecture/command checks are available; local PostgreSQL is not. HARD-006–008 are processed next because baseline formatter/architecture/static-analysis failures prevent CI from reaching those behavior tests. No gate is skipped or weakened; return to HARD-005 verification after these prerequisite repairs.
