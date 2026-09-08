# Codebase hardening delivery ledger

## Resume header

- Program state: In progress.
- Exact main baseline: `7e780521295e868005ecfee5bd38b33e8215ec49`.
- Working branch: `astra/codebase-hardening`.
- Latest pushed durable checkpoint: `96e79f18ea2914eb645b8d91f49a1eb1ff32c4e3`.
- Draft PR: [#163](https://github.com/awalker0878/kingshot-alliance/pull/163).
- Current item/state: HARD-013 / In progress (implementation ready for CI). HARD-010 full pipeline checks now pass in PostgreSQL CI; its remaining candidate gates are running. HARD-004 baseline and HARD-009 reminder verification are Complete on `2bc1b498`.
- Most recently verified gates: all nine pull-request workflows pass on `2bc1b49829b8dafc2bb93ad37f39636ef88efef3`: CI (715 PHP tests, 72,953 assertions; frontend; production image, staging and recovery), Architecture V3, Intelligence, Gift Codes, King Perks, KingdomMaps, Visual Regression, CodeQL and Dependency Review. New HARD-010 test passes syntax/Pint/discovery; documentation links pass (234 files).
- Active files: Governor normalization/review lifecycle guards, pipeline regressions and retry/lifecycle documentation; ledger.
- Remaining current work: verify HARD-013 lifecycle regressions and containing-commit gates. HARD-010 eight upload-to-commit cases pass within the 723-test CI run (73,095 assertions), PHP job `102144249268`. Audit discovered HARD-014 retention family/batch defects and HARD-015 incomplete redaction next.
- Known failures: none in completed PHP/frontend gates on `96e79f18`; container/staging/recovery is running. HARD-013–015 remain tracked production defects until verification.
- Blockers: local PostgreSQL/Redis services unavailable; service-backed verification uses GitHub CI. Local PHP 8.5.8 and locked Composer/npm dependencies available. Checkpoints publish via the authorized GitHub connection with exact staged-tree verification and non-forced branch updates.
- Exact next action: publish HARD-013 checkpoint, implement HARD-014 family-aware bounded retention and HARD-015 raw-provenance redaction, then reconcile CI and continue capability audit.
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
- State: Complete.
- Verification required: all applicable gates pass on the final containing commit.
- Verification result: baseline failures isolated and repaired through HARD-006–008 and HARD-011–012. All nine pull-request workflows pass on `2bc1b49829b8dafc2bb93ad37f39636ef88efef3`, including full PHP (715 tests, 72,953 assertions), fresh PostgreSQL, frontend, image/staging/recovery, architecture, all capability gates, visual, CodeQL and dependency review. This closes baseline reconciliation; final program verification must repeat on its final containing commit.
- Completion evidence: CI `34249549762`, Architecture `34249549785`, Intelligence `34249549808`, Gift Codes `34249549756`, King Perks `34249549812`, KingdomMaps `34249549776`, Visual `34249549791`, CodeQL `34249549871`, Dependency Review `34249549769`; all success.
- Commit SHA: verified candidate `2bc1b49829b8dafc2bb93ad37f39636ef88efef3`.

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
- State: Complete.
- Verification required: race regression preserving a rescheduled reminder, duplicate sweep/retry behavior and owner authorization.
- Verification result: per-occurrence transaction, locked timestamp comparison and second-precision stable idempotency implemented. Six PostgreSQL regression cases cover changed/cancelled snapshots, same-minute separate occurrences, rollback/retry and the exact due/no-owner boundary. Changed-file Pint, PHP syntax and full PHPStan pass; documentation links pass (234 files). Full PostgreSQL CI now passes all 715 tests (72,953 assertions), including all six reminder regressions; Gift Code Verification and all other candidate workflows pass.
- Completion evidence: QueueDueGiftCodeReminders and GiftCodeReminderOccurrenceV3Test; product reminder contract reconciled. No external network calls occur in Communications intent persistence.
- Commit SHA: `2bc1b49829b8dafc2bb93ad37f39636ef88efef3`; CI `34249549762`, PHP job `102140300263`, Gift Code run `34249549756` pass.

### HARD-010 — Structured progression screenshot pipeline is incomplete

- Area: Intelligence/Evidence and Roster, Governor screenshot UI and contracts.
- Finding: Three new building/Academy/War Academy kinds have schemas, review controls and destination dispatch, but no classifier scores or extractor branches; the intake path cannot produce reviewable evidence. The current screenshot contract still describes only six kinds.
- Current owner: GovernorProgressionEvidenceClassifier/Extractor and existing normalization/review pipeline.
- Intended authoritative owner: same Evidence pipeline; Roster owns accepted states and GameWorld owns pinned reference facts.
- Rationale: a registered kind and a passing enum match do not establish an executable capability; machine extraction must remain independent of the user-selected kind and preserve unknown fields.
- Remediation: complete explicit classifier/extractor contracts with fixtures, trace normalized review through typed destination validation, reconcile current documentation and test production routing/review behavior.
- State: In progress.
- Verification required: routed classification/extraction including mismatch/ambiguity/unknown fields; pinned-dataset review and destination behavior; full Intelligence, architecture, style and static analysis.
- Verification result: narrow English heading/label extraction implemented with three 12-case synthetic OCR corpora. Classifier/extractor versions advanced for provenance. All 22 routing/schema/corpus tests pass (1,017 assertions); three real pinned-state validator cases pass (18 assertions), covering valid names and rejected missing/invalid/duplicate/mismatched states. Roster replay behavior across all three kinds passes PostgreSQL CI on `2bc1b498`. Eight new full upload/classification/extraction/normalization/review/commit cases use real provenance, replacing only external OCR; they cover missing levels, mismatch, cross-Alliance authority/scope and receipt replay. All eight pipeline cases pass in PostgreSQL CI on `96e79f18ea2914eb645b8d91f49a1eb1ff32c4e3`: full suite 723 tests, 73,095 assertions (PHP job `102144249268`). Full PHPStan passes, changed PHP Pint and architecture verifier pass.
- Completion evidence: routing/extraction, synthetic fixture corpora, structured pinned-state validation and expanded owner replay tests; screenshot contract now marks this extension In progress. Current reference/architecture/operations contracts now describe all nine schemas, exact reviewed structured-state validation and synthetic OCR limitations. Remaining: containing-commit container/security/visual gates and HARD-013 lifecycle repair; retain In progress until those pass.
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

### HARD-013 — Governor evidence terminal lifecycle can be overwritten

- Area: Intelligence/Evidence normalization, review and deletion.
- Finding: normalization replay unconditionally restores `needs_review`, including when Evidence is already approved, committed or deleted. Review saving does not reject deleted/redacted Evidence. Normalization shares the existing `extracting` lifecycle, so the deletion guard already protects the normal handoff; automatic retries from `failed` must reacquire that active state.
- Current owner: NormalizeGovernorProgressionEvidence and SaveGovernorProgressionEvidenceReview; the existing deletion guard is retained.
- Intended authoritative owner: the same explicit owner Actions with terminal and active lifecycle invariants checked under lock.
- Rationale: queue redelivery or stale review requests must not revive deleted provenance, reopen committed Evidence or race active normalization.
- Remediation: protect terminal states, make completed normalization redelivery inert, reject stale reviews after deletion, and verify deletion/commit history remains intact.
- State: In progress.
- Verification required: real pipeline lifecycle regressions for deleted/approved/committed Evidence, stale review rejection and active normalization deletion; Intelligence and architecture checks.
- Verification result: production guards implemented under the Evidence lock; normalization pins are selected inside the same lock and retries reacquire the existing active state. Four real-pipeline lifecycle cases added for redelivery, stale review, terminal states, retained owner history and the existing deletion guard. Full PHPStan passes (zero errors); changed-file Pint passes. PostgreSQL behavior execution awaits the containing checkpoint.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-014 — Evidence retention omits later commit families and due filtering

- Area: Intelligence/Evidence scheduled retention.
- Finding: EnforceEvidenceRetention recognizes only Bear Hunt commit attempts. Transfer, Governor and spatial committed Evidence can be purged as uncommitted, cascading away reviewed provenance and commit receipts. Candidate selection limits oldest rows before testing expiration/active status, allowing old ineligible rows to starve later eligible work.
- Current owner: EnforceEvidenceRetention.
- Intended authoritative owner: same Evidence retention Action across its explicit family commit ledgers.
- Rationale: retention must preserve completed handoffs and make bounded progress on eligible work for every supported family.
- Remediation: use one family-complete committed predicate in candidate selection and locked revalidation; filter eligible expiration/status before the batch limit; retain tombstones and receipts.
- State: Planned.
- Verification required: all family commit provenance survives binary retention; deleted committed tombstones persist; active/not-yet-due rows do not starve expired records; retry and existing retention suites pass.
- Verification result: source trace and foreign-key cascade definitions confirm the defect; implementation pending.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-015 — Evidence redaction leaves copied raw normalization content

- Area: Intelligence/Evidence raw provenance retention.
- Finding: EvidenceRedactor removes classification OCR and extracted raw text/bounds but leaves raw text, bounds and candidates copied into Governor normalization payloads, which the authorized summary still returns after deletion.
- Current owner: EvidenceRedactor and Governor normalization persistence.
- Intended authoritative owner: same Evidence redaction service across all copies of machine provenance.
- Rationale: deletion must purge the promised raw source data while preserving minimum reviewed handoff/receipt provenance and independent accepted owner history.
- Remediation: clear raw machine payload copies consistently; preserve dataset/attempt/review/receipt identity and accepted observations; verify repeated redaction and summary behavior.
- State: Planned.
- Verification required: real normalized evidence loses raw/candidate payload on deletion/retention while pinned identity and committed history remain.
- Verification result: copied `raw_text` and `bounding_box` fields remain in `normalized_payload`; redactor has no normalization update.
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
| Intelligence | Evidence/Roster structured pipeline traced under HARD-008/010/013; observations, other evidence families, ingestion, contributions, projections/signals and retention remain | In progress |
| Communications | Preferences/recipients, inbox, delivery channels, digests, retry/idempotency and revocation | Planned |
| Platform | Administration, integrations/API credentials, webhooks, retention and operational controls | Planned |
| Workflows/ReadModels | All cross-context orchestration, authorized dashboards, Assistant/API/notification projections | Planned |
| Infrastructure/entry points | Scheduler registration/commands verified by HARD-003; route authorization, shared mechanisms, queues/listeners/outbox and middleware audit remain | In progress |
| Frontend | Pages, components, composables/stores, server contracts, localization, receipts and accessibility | Planned |
| Schema/verification/operations/docs | Fresh schema/indexes, concurrency/query budgets, tests, CI, image/recovery, contracts/catalogues/ADRs/ledgers | Planned |

## Execution adjustments

- After HARD-003, HARD-005 implements the ownership move but remains In progress until database-backed behavior passes. Local PHP/architecture/command checks are available; local PostgreSQL is not. HARD-006–008 are processed next because baseline formatter/architecture/static-analysis failures prevent CI from reaching those behavior tests. No gate is skipped or weakened; return to HARD-005 verification after these prerequisite repairs.
