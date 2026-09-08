# Codebase hardening delivery ledger

## Resume header

- Program state: In progress.
- Exact main baseline: `7e780521295e868005ecfee5bd38b33e8215ec49`.
- Working branch: `astra/codebase-hardening`.
- Latest pushed durable checkpoint: `1591cb5a6c592788f2fafc225ad453f58cfb9d5e`.
- Draft PR: [#163](https://github.com/awalker0878/kingshot-alliance/pull/163).
- Current item/state: HARD-009 / In progress (implementation ready for CI behavior verification). HARD-004 integration gates and HARD-010 wider intake verification remain open.
- Most recently verified gates: local Architecture/Progression 113 tests (67,161 assertions); full PHPStan zero errors; changed PHP Pint; full frontend `npm run check` including build/budgets; documentation links (234 files). CI on `57f383ab`: fresh PostgreSQL, style, PHPStan and frontend checks pass; 703/705 PHP tests pass, with only HARD-011 failures now repaired locally. HARD-005 behavior verified in that full run.
- Active files: HARD-009 reminder occurrence transaction, PostgreSQL regressions and reminder product contract; ledger.
- Remaining current work: publish HARD-009 checkpoint and verify its PostgreSQL race/retry cases in CI. Finish HARD-010 structured-intake integration/documentation and remaining repository audit.
- Known failures: none in the latest completed PHP/frontend runs on `1591cb5a`. CI `34249019123` backend and frontend pass; container/staging/recovery is building. HARD-009 PostgreSQL regressions have not yet executed.
- Blockers: local PostgreSQL/Redis services unavailable; service-backed verification uses GitHub CI. Local PHP 8.5.8 and locked Composer/npm dependencies available. Checkpoints publish via the authorized GitHub connection with exact staged-tree verification and non-forced branch updates.
- Exact next action: publish HARD-009 checkpoint, inspect CI behavior, then finish HARD-010 full intake/review/commit verification and related documentation.
- Remaining repository-wide gates: final full PHP/architecture/capability and frontend gates on one containing commit; production image/staging/recovery; final security/dependency/visual checks; remaining capability-by-capability audit coverage below.

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
- State: Complete.
- Verification required: Production notification behavior including duplicate runs, revoked authority, cross-Alliance isolation, command registration, architecture and static analysis.
- Verification result: CI backend and Intelligence runs on `57f383abc0fb67787724f9cce4f18cdf71cd34de` execute the full 705-test PostgreSQL suite (72,887 assertions); only the two unrelated HARD-011 expectation cases fail. All migrated NotificationDelivery Workflow behavior, duplicate/revoked/cross-Alliance/recipient/queue tests pass. Pint and PHPStan pass in both runs; fresh PostgreSQL schema passes. Architecture/scheduler enforcement passed locally and in the architecture gate.
- Completion evidence: ADR-0018 and migrated tests; CI job `102129065161` and Intelligence job `102129065536`, with exact failure lists confined to HARD-011.
- Commit SHA: `9b39d47609bbf9b7938d32b078106a5e243f394a`; behavior verified on `57f383abc0fb67787724f9cce4f18cdf71cd34de`.

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
- Commit SHA: `2bb70908fea818f704d403614c92617b1ec057fa`.

### HARD-008 — Missing Governor progression evidence routing

- Area: Intelligence/Evidence extraction.
- Finding: PHPStan reports non-exhaustive match in `RoutedEvidenceExtractor` for `GovernorBuildings`, `GovernorAcademyResearch` and `GovernorWarAcademyResearch`; these evidence kinds can fail at extraction time.
- Current owner: Intelligence/Evidence routing.
- Intended authoritative owner: same router and the existing Governor progression extractor.
- Rationale: one complete evidence-kind dispatch contract; supported intake kinds must reach their authoritative extractor.
- Remediation: map all supported progression kinds, verify actual extraction behavior and keep unknown/unsupported input semantics explicit.
- State: Complete.
- Verification required: exhaustive enum/router regression, production progression extraction behavior and full PHPStan.
- Verification result: reproduced an UnhandledMatchError for GovernorBuildings plus three failed routed classifications before remediation. Complete enum routing and real classifier/extractor/schema corpora now pass (22 tests, 1,017 assertions). Full PHPStan passes with zero errors; changed PHP Pint, architecture verifier and documentation links pass.
- Completion evidence: RoutedEvidenceExtractorV3Test covers every EvidenceKind, actual extraction, explicit unsupported failures and structured classification independent of expected kind; HARD-010 tracks wider destination/UX verification.
- Commit SHA: `57f383abc0fb67787724f9cce4f18cdf71cd34de`.

### HARD-009 — Personal reminder stale-snapshot race

- Area: GameWorld/GiftCodes personal reminders.
- Finding: `QueueDueGiftCodeReminders` queues from an unlocked account-state snapshot, then clears any current past-due `remind_at` under lock without checking it still matches the reminder processed. Concurrent rescheduling can be cleared by the earlier sweep.
- Current owner: `QueueDueGiftCodeReminders`.
- Intended authoritative owner: same owner Action with an explicit reminder occurrence/claim boundary.
- Rationale: stale processing must not erase a newer user intention; retries and parallel runs need stable occurrence identity.
- Remediation: reproduce the reschedule race, revalidate/lock occurrence state at mutation and preserve new reminders; verify retry/idempotency semantics without network calls under a broad transaction.
- State: In progress.
- Verification required: race regression preserving a rescheduled reminder, duplicate sweep/retry behavior and owner authorization.
- Verification result: per-occurrence transaction, locked timestamp comparison and second-precision stable idempotency implemented. Six PostgreSQL regression cases cover changed/cancelled snapshots, same-minute separate occurrences, rollback/retry and the exact due/no-owner boundary. Changed-file Pint, PHP syntax and full PHPStan pass; documentation links pass (234 files). Local service-backed execution is unavailable; CI verification is required before completion.
- Completion evidence: QueueDueGiftCodeReminders and GiftCodeReminderOccurrenceV3Test; product reminder contract reconciled. No external network calls occur in Communications intent persistence.
- Commit SHA: checkpoint recorded after publication; runtime verification pending.

### HARD-010 — Structured progression screenshot pipeline is incomplete

- Area: Intelligence/Evidence and Roster, Governor screenshot UI and contracts.
- Finding: Three new building/Academy/War Academy kinds have schemas, review controls and destination dispatch, but no classifier scores or extractor branches; the intake path cannot produce reviewable evidence. The current screenshot contract still describes only six kinds.
- Current owner: GovernorProgressionEvidenceClassifier/Extractor and existing normalization/review pipeline.
- Intended authoritative owner: same Evidence pipeline; Roster owns accepted states and GameWorld owns pinned reference facts.
- Rationale: a registered kind and a passing enum match do not establish an executable capability; machine extraction must remain independent of the user-selected kind and preserve unknown fields.
- Remediation: complete explicit classifier/extractor contracts with fixtures, trace normalized review through typed destination validation, reconcile current documentation and test production routing/review behavior.
- State: In progress.
- Verification required: routed classification/extraction including mismatch/ambiguity/unknown fields; pinned-dataset review and destination behavior; full Intelligence, architecture, style and static analysis.
- Verification result: narrow English heading/label extraction implemented with three 12-case synthetic OCR corpora. Classifier/extractor versions advanced for provenance. All 22 routing/schema/corpus tests pass (1,017 assertions); three real pinned-state validator cases pass (18 assertions), covering valid names and rejected missing/invalid/duplicate/mismatched states. Roster replay behavior expanded across all three kinds; database-backed verification pending in CI. Full PHPStan passes, changed PHP Pint and architecture verifier pass.
- Completion evidence: routing/extraction, synthetic fixture corpora, structured pinned-state validation and expanded owner replay tests; screenshot contract now marks this extension In progress. Remaining: database-backed review/normalization/destination/authorization checks and reconcile related reference/architecture/operations contracts.
- Commit SHA: `57f383abc0fb67787724f9cce4f18cdf71cd34de`.

### HARD-011 — Progression topology and prerequisite contract drift

- Area: GameWorld/Progression topology and ReadModels/Progression planner verification.
- Finding: Broader checks expose two existing failing assertions: the planner family list predates Hero Widget, troop tiers and VIP; prerequisite expectations predate the structured observed/unknown prerequisite evaluator.
- Current owner: ProgressionTopologyV3Test and ProgressionPlannerQueryV3Test, current topology/evaluator implementations.
- Intended authoritative owner: GameWorld factual topology and authorized ReadModel prerequisite evaluation.
- Rationale: current features require evidence of factual ownership and unknown-state semantics; tests must protect behavior without retaining obsolete exact payloads.
- Remediation: verify new family/state sources and prerequisite evaluation against pinned observations, update valid contracts and add missing behavioral coverage for known/unknown/revoked or stale inputs where relevant.
- State: Complete.
- Verification required: complete GameWorld/Progression and ReadModels/Progression suites; architecture and static analysis.
- Verification result: both expectations also fail in the full CI 705-test run. Updated current-family list and unknown-prerequisite semantics now pass with all local Architecture/Progression checks: 113 tests, 67,161 assertions. Full PHPStan passes; changed-file Pint and documentation links pass.
- Completion evidence: current family catalogue checked against pinned topology sources; planner assertions retain sourced labels and require unknown observed levels with resolved subject identities. HARD-012 separately fixes the material pin defect revealed by this trace.
- Commit SHA: `1591cb5a6c592788f2fafc225ad453f58cfb9d5e`.

### HARD-012 — Prerequisite evaluation borrows another field's dataset pin

- Area: ReadModels/Progression prerequisite evaluation.
- Finding: The evaluator takes dataset identity/checksum from the first populated fact in a subject and can evaluate a different level fact under that pin. Completely unlabelled numeric levels can also become satisfied prerequisites.
- Current owner: ProgressionPrerequisiteEvaluator.
- Intended authoritative owner: same authorized ReadModel projection using the level observation's own provenance.
- Rationale: one fact's pin cannot authorize another fact's interpretation; missing metadata must not silently become current dataset truth.
- Remediation: evaluate only an exact pinned level fact; reject missing/mixed pins and invalid numeric level forms; remove the superseded first-fact scan and add behavioral regression coverage.
- State: Complete.
- Verification required: mixed/missing pin, matching known/unknown level and satisfied/not-satisfied behavior; Progression/architecture suites and PHPStan.
- Verification result: three new regressions fail before remediation (borrowed pin, missing pin, unsafe numeric coercion). All four evaluator cases and the complete local Architecture/Progression selection now pass: 113 tests, 67,161 assertions. Full PHPStan passes with zero errors; changed-file Pint and documentation links pass.
- Completion evidence: ProgressionPrerequisiteEvaluatorV3Test; exact level-owned provenance, strict integer semantics and superseded helper removal; current prerequisite contract reconciled.
- Commit SHA: `1591cb5a6c592788f2fafc225ad453f58cfb9d5e`.

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
