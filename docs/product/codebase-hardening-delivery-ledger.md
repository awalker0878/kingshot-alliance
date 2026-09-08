# Codebase hardening delivery ledger

## Resume header

- Program state: In progress.
- Exact main baseline: `7e780521295e868005ecfee5bd38b33e8215ec49`.
- Working branch: `astra/codebase-hardening`.
- Latest pushed durable checkpoint: `f606b14f39347563b289e5a575ec467be720720c`.
- Draft PR: [#163](https://github.com/awalker0878/kingshot-alliance/pull/163).
- Current item/state: HARD-034 / In progress (email-change throttling). HARD-032/033 containing gates are running; HARD-031 is Complete.
- Most recently verified gates: all nine PR workflows pass on `8c6b8a7a29a7d0bc1ecba9a1aa579bc88e828d5e`, including 876 PHP tests / 75,032 assertions, fresh PostgreSQL, frontend, image/staging/recovery, architecture/capabilities, visual and security.
- Active files: account-email-change named limiter/provider/route, actual HTTP budget regression, corrected competing-connection names in three fixtures, email contract and ledger.
- Remaining current work: verify HARD-032/033/034 containing gates; HARD-036 password-reset issuance and HARD-037 login proof freshness remain; continue repository audit coverage.
- Known failures: containing terminal-state runs stalled in a new competing-connection fixture: copying getConfig retained the primary name, so hydrated competing models saved through the primary connection while the competitor held its lock. Explicit distinct connection names now preserve true isolation; containing verification pending. Last fully green checkpoint remains 8c6b8a7a.
- Blockers: local PostgreSQL/Redis services unavailable; service-backed verification uses GitHub CI. Local PHP 8.5.8 and locked Composer/npm dependencies available. Checkpoints publish via the authorized GitHub connection with exact staged-tree verification and non-forced branch updates.
- Exact next action: verify HARD-032/033/034, then implement current-account reset issuance under HARD-036 and login proof freshness under HARD-037.
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
- State: Complete.
- Verification required: routed classification/extraction including mismatch/ambiguity/unknown fields; pinned-dataset review and destination behavior; full Intelligence, architecture, style and static analysis.
- Verification result: narrow English heading/label extraction implemented with three 12-case synthetic OCR corpora. Classifier/extractor versions advanced for provenance. All 22 routing/schema/corpus tests pass (1,017 assertions); three real pinned-state validator cases pass (18 assertions), covering valid names and rejected missing/invalid/duplicate/mismatched states. Roster replay behavior across all three kinds passes PostgreSQL CI on `2bc1b498`. Eight new full upload/classification/extraction/normalization/review/commit cases use real provenance, replacing only external OCR; they cover missing levels, mismatch, cross-Alliance authority/scope and receipt replay. All eight pipeline cases pass in PostgreSQL CI on `96e79f18ea2914eb645b8d91f49a1eb1ff32c4e3`: full suite 723 tests, 73,095 assertions (PHP job `102144249268`). Full PHPStan passes, changed PHP Pint and architecture verifier pass.
- Completion evidence: routing/extraction, synthetic fixture corpora, structured pinned-state validation and expanded owner replay tests; screenshot contract now marks this extension In progress. Current reference/architecture/operations contracts now describe all nine schemas, exact reviewed structured-state validation and synthetic OCR limitations. All nine workflows pass on containing candidate `5a01bf68930c52cfac3ee77564e2f5a1570ae1ad`, including 727 PHP tests / 73,144 assertions, all pipeline/lifecycle cases, frontend, fresh database, image/staging/recovery, architecture, visual and security checks. Retention defects are tracked independently under HARD-014–015.
- Commit SHA: implementation `57f383abc0fb67787724f9cce4f18cdf71cd34de`, pipeline `96e79f18ea2914eb645b8d91f49a1eb1ff32c4e3`, verified containing candidate `5a01bf68930c52cfac3ee77564e2f5a1570ae1ad`.

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
- State: Complete.
- Verification required: real pipeline lifecycle regressions for deleted/approved/committed Evidence, stale review rejection and active normalization deletion; Intelligence and architecture checks.
- Verification result: production guards implemented under the Evidence lock; normalization pins are selected inside the same lock and retries reacquire the existing active state. Four real-pipeline lifecycle cases added for redelivery, stale review, terminal states, retained owner history and the existing deletion guard. Full PHPStan passes (zero errors); changed-file Pint passes. All four new cases pass with the full PostgreSQL suite: 727 tests, 73,144 assertions (PHP job `102146638235`). All nine workflows pass on the same candidate.
- Completion evidence: CI `34251453726`, Intelligence `34251453623`, Architecture `34251453691`, Visual `34251453670`, CodeQL `34251453731` and remaining capability/dependency checks; all success.
- Commit SHA: `5a01bf68930c52cfac3ee77564e2f5a1570ae1ad`.

### HARD-014 — Evidence retention omits later commit families and due filtering

- Area: Intelligence/Evidence scheduled retention.
- Finding: EnforceEvidenceRetention recognizes only Bear Hunt commit attempts. Transfer, Governor and spatial committed Evidence can be purged as uncommitted, cascading away reviewed provenance and commit receipts. Candidate selection limits oldest rows before testing expiration/active status, allowing old ineligible rows to starve later eligible work.
- Current owner: EnforceEvidenceRetention.
- Intended authoritative owner: same Evidence retention Action across its explicit family commit ledgers.
- Rationale: retention must preserve completed handoffs and make bounded progress on eligible work for every supported family.
- Remediation: use one family-complete committed predicate in candidate selection and locked revalidation; filter eligible expiration/status before the batch limit; retain tombstones and receipts.
- State: Complete.
- Verification required: all family commit provenance survives binary retention; deleted committed tombstones persist; active/not-yet-due rows do not starve expired records; retry and existing retention suites pass.
- Verification result: all four GameEvidence family commit ledgers now share one success predicate in candidate filtering and locked revalidation; due/active filtering precedes the batch limit. Five regressions cover real Governor/Transfer commits, spatial handoff tombstones, ineligible-head starvation and a newly committed candidate. Existing Bear Hunt retention remains covered. Full PHPStan zero errors and changed-file Pint pass. Fresh-schema indexes support global age ordering and evidence/status commit lookups. All five cases and existing retention tests pass in full PostgreSQL CI on `cf25fba0` (735 tests, 73,265 assertions), with all nine workflows successful. Alliance roster intake uses separate persistence and is outside this four-family worker.
- Completion evidence: EnforceEvidenceRetention, five regression cases and empty-database indexes; CI `34253535376`, PHP job `102153521950`, Intelligence `34253535359` and all other PR workflows pass.
- Commit SHA: `f6e19117d0dc1de99fe2a158309455e54e281c51`; verified containing commit `cf25fba039e0c123cc30546d9300db31f44a5eca`.

### HARD-015 — Evidence redaction leaves copied raw normalization content

- Area: Intelligence/Evidence raw provenance retention.
- Finding: EvidenceRedactor removes classification OCR and extracted raw text/bounds but leaves raw text, bounds and candidates copied into Governor normalization payloads, which the authorized summary still returns after deletion.
- Current owner: EvidenceRedactor and Governor normalization persistence.
- Intended authoritative owner: same Evidence redaction service across all copies of machine provenance.
- Rationale: deletion must purge the promised raw source data while preserving minimum reviewed handoff/receipt provenance and independent accepted owner history.
- Remediation: clear raw machine payload copies consistently; preserve dataset/attempt/review/receipt identity and accepted observations; verify repeated redaction and summary behavior.
- State: Complete.
- Verification required: real normalized evidence loses raw/candidate payload on deletion/retention while pinned identity and committed history remain.
- Verification result: redaction now clears classification OCR, extracted raw/candidate text, bounds/warnings and all normalization payload copies. Attempt/dataset/review/receipt identities and accepted owner facts remain. Two real-pipeline cases cover user deletion and scheduled retention, repeated execution and the actual summary projection. CI on `d006843e` catches null-versus-required-string redaction; follow-up clears the candidate to an empty string, matching its persisted contract. Four errors and three downstream failures share that cause; the gate remains unchanged. Full PHPStan and Pint pass; both real-pipeline redaction cases pass in full PostgreSQL CI on `cf25fba0` (735 tests, 73,265 assertions), with all nine workflows successful.
- Completion evidence: EvidenceRedactor and real pipeline deletion/retention/retry regressions; CI `34253535376`, PHP job `102153521950` and all other PR workflows pass.
- Commit SHA: `d006843e46e04f85a30afc578ff49651b5328581`; correction and verified containing commit `cf25fba039e0c123cc30546d9300db31f44a5eca`.

### HARD-016 — Failed source deletion is acknowledged as redaction

- Area: Intelligence/Evidence private storage deletion.
- Finding: EvidenceRedactor ignores the filesystem adapter's false result and clears the source path/marks redaction successful even when the binary remains in storage.
- Current owner: EvidenceRedactor.
- Intended authoritative owner: same service with an explicit storage success boundary.
- Rationale: failed deletion must remain retryable and must not claim privacy cleanup succeeded.
- Remediation: fail before database redaction if storage reports failure; preserve path/provenance and verify retry through the authorized deletion Action.
- State: Complete.
- Verification required: failed adapter result retains the source path, machine provenance and lifecycle; retry deletes successfully without extra owner history.
- Verification result: explicit failure handling and a real-pipeline regression implemented; only the filesystem delete result is substituted. Full PHPStan and Pint pass; the failed-delete/retry case passes in full PostgreSQL CI on `cf25fba0` (735 tests, 73,265 assertions), with all nine workflows successful.
- Completion evidence: EvidenceRedactor and real pipeline deletion/retention/retry regressions; CI `34253535376`, PHP job `102153521950` and all other PR workflows pass.
- Commit SHA: `d006843e46e04f85a30afc578ff49651b5328581`; correction and verified containing commit `cf25fba039e0c123cc30546d9300db31f44a5eca`.

### HARD-017 — Governor screenshot workspace repeats queries per item

- Area: Intelligence/Evidence GovernorProgressionEvidenceSummaryQuery.
- Finding: a 30-item workspace fetch executes five separate latest-attempt/review/commit reads and one extracted-field query for every Evidence record: up to 181 database queries for one bounded list.
- Current owner: GovernorProgressionEvidenceSummaryQuery.
- Intended authoritative owner: same owner query with bounded latest-record loading.
- Rationale: list cardinality should not multiply database round trips or load unbounded attempt history.
- Remediation: load only the latest related records and fields in batches, preserve current scope/order/provenance semantics, and enforce a meaningful query budget with multiple attempts and Evidence records.
- State: Complete.
- Verification required: query count remains bounded as the list grows; latest revision and deterministic attempt ordering, cross-scope filtering and existing pipeline summaries remain correct.
- Verification result: PostgreSQL DISTINCT ON batches now load one latest record per Evidence ID for classification, extraction, normalization, review and commit, plus fields for only the selected extractions. The 30-item parent list is ordered deterministically and remains scope-filtered. New query-budget coverage compares one item with a full page backed by three historical attempts/revisions each, checks foreign scope exclusion, latest identities/field ordering and the empty-list path. Full PHPStan passes (zero errors); fresh-schema history indexes support scoped latest reads. Both query-budget tests pass in PostgreSQL CI on `51416d5d`, including latest revision/tied-time ordering, a constant seven queries for one or 30 items, and one query for an empty list. Full suite: 737 tests, 73,514 assertions; all nine workflows pass.
- Completion evidence: GovernorProgressionEvidenceSummaryBudgetV3Test and owner projection/index changes; CI `34254364446`, PHP job `102156318191`, Intelligence `34254364540`, Architecture `34254364513` and all other PR workflows pass.
- Commit SHA: `51416d5d70c93393a657b232e488788072d63a00`.

### HARD-018 — Superseded recent-authentication timestamps remain an authority

- Area: Accounts/Authentication and sensitive HTTP operations.
- Finding: RecentAuthentication accepts two transitional password/Google timestamps after the canonical proof expires, and password confirmation still writes a second timestamp authority. Three test fixtures depend on the superseded contract.
- Current owner: RecentAuthentication and ConfirmPasswordController.
- Intended authoritative owner: the same canonical Accounts proof used by password, Google and passkey confirmation.
- Rationale: the fresh deployment has no compatibility requirement; sensitive operations need one proof and expiry boundary.
- Remediation: remove legacy fallback/dual writes, migrate current route fixtures, and verify old timestamps cannot authorize a sensitive change while real password confirmation can.
- State: Complete.
- Verification required: obsolete proof rejection, real confirmation and protected operation, existing Google/passkey and credential mutation behavior, full static/style/CI gates.
- Verification result: canonical-only proof implemented; two obsolete-key rejection cases and a real password-confirmation-to-MFA-setup case added. Full PHPStan passes with zero errors, changed-file Pint and documentation links pass. All three new regressions pass on `b1ee5a40`: full PostgreSQL suite 740 tests / 73,530 assertions; all nine workflows pass.
- Completion evidence: RecentAuthenticationV3Test and reconciled Accounts sign-in-method contract; CI `34255437384`, PHP job `102159935512`, Architecture `34255437378` and all other PR workflows pass.
- Commit SHA: `b1ee5a40f39261a81e51e7a395f40c9da1838d00`.

### HARD-019 — Request tracking can revive revoked account sessions

- Area: Accounts session registration, revocation and middleware.
- Finding: RecordAccountSession clears revoked_at on every authenticated request. An in-flight request or a failed raw-session delete can therefore restore revoked metadata, and TrackAccountSession does not reject a retained revoked marker. Unrotated remember-me tokens can recreate deleted sessions; completed login is not registered until the next request. Anonymization deletes the registry, leaving stale session payloads without a terminal lifecycle check. A retired session route still returns an unconditional 404 and an unused password-only revocation Action remains.
- Current owner: Accounts session Actions and TrackAccountSession; configured session handler stores credentials.
- Intended authoritative owner: Accounts owns revocation decisions; the session handler continues to own session credentials.
- Rationale: a stale request must never reactivate revoked access, and storage deletion alone cannot enforce revocation against a concurrent session write.
- Remediation: trace revocation and session rotation, preserve terminal revoked markers, enforce them before continuing an authenticated request, and cover storage failure/in-flight replay and current/foreign-session isolation.
- State: Complete.
- Verification required: revoked sessions remain denied after stale tracking/storage writes; current-session and other-account boundaries; normal registration and credential hardening still pass.
- Verification result: conditional tracking preserves terminal markers; middleware rejects revoked/anonymized access before game context and registers newly rotated login sessions before returning the response. Revocation decisions, remember-token rotation and audit commit before raw storage cleanup. All-other revocation loads a bounded registered-ID snapshot in batches. Removed the unused password-only Action and retired 404 route/controller stub. Ten regressions cover failed deletion, the read/write race, >100-record batches, password/Google remembered replay, current/foreign isolation, immediate login registration and anonymized stale access. Full PHPStan passes with zero errors; Pint and all 62 Architecture tests pass (66,587 assertions); documentation links pass. PostgreSQL CI executes all 750 tests: eight of the ten new session cases pass, including all remembered-sign-in and race cases. Two HTTP fixtures omit the session cookie and acquire different session IDs, so they do not exercise the original/current session. The correction explicitly replays the original browser cookie; all security assertions remain. The corrected browser-cookie cases and all ten regressions now pass on `94c95511` (752 tests, 73,772 assertions), with all nine PR workflows successful.
- Completion evidence: CI `34257205591`, PHP job `102165911270`, Architecture `34257205716`, Intelligence `34257205609` and all other PR workflows pass.
- Commit SHA: `9f67277d8203075155bcb8a5d35d9e683c2f138c`; fixture correction and containing verification `94c95511cbf55abc911abd5590c8f75debb553f5`.

### HARD-020 — MFA setup and recovery values never reach the profile

- Area: Accounts MFA controllers and profile response.
- Finding: TwoFactorController flashes camel-case setup/recovery keys while ProfileController reads different snake-case keys. The real profile therefore receives neither the authenticator setup secret nor newly generated recovery codes.
- Current owner: Accounts MFA mutation controllers and ProfileController projection.
- Intended authoritative owner: one ephemeral MFA response contract consumed by the existing profile UI.
- Rationale: successful persistence does not establish a usable enrollment/recovery flow; secret material must be displayed only to the authenticated account and consumed once.
- Remediation: reconcile the flash/read contract and verify real enrollment, confirmation and recovery-code regeneration through the rendered profile, including single-use delivery.
- State: Complete.
- Verification required: enrollment setup and plain recovery codes appear once on the authorized profile; confirmation stores only recovery hashes; later profile responses omit secret material.
- Verification result: profile now consumes the exact camel-case flash keys written by the MFA controller. Two real enrollment/profile cases cover authenticator setup, valid TOTP confirmation, recovery regeneration, single-use display, encrypted secret storage and recovery hash replacement. Full PHPStan, changed-file Pint and documentation links pass; Both real profile/enrollment tests pass on `94c95511` (752 tests, 73,772 assertions), with all nine PR workflows successful.
- Completion evidence: CI `34257205591`, PHP job `102165911270`, Architecture `34257205716`, Intelligence `34257205609` and all other PR workflows pass.
- Commit SHA: `94c95511cbf55abc911abd5590c8f75debb553f5`.

### HARD-021 — Pending MFA login has no proof expiry or credential binding

- Area: Accounts password/Google login and MFA challenge.
- Finding: the pending MFA login stores only a User ID, remember flag, invitation and method. The challenge has no independent timeout or binding to the primary credential, so a previously accepted primary factor can survive credential removal/change while the browser session persists.
- Current owner: password and Google login writers, TwoFactorChallengeController.
- Intended authoritative owner: one Accounts-owned bounded MFA login challenge.
- Rationale: second-factor completion must correspond to a current, recent primary-factor proof and must not restore a superseded credential.
- Remediation: trace all challenge writers/consumers and lifecycle mutations, establish a short-lived single-use proof tied to its primary credential, and reject expired/stale challenges.
- State: Complete.
- Verification required: normal password/Google plus TOTP/recovery login, expired/replayed challenges, credential changes/removal and account finalization.
- Verification result: one Accounts-owned challenge replaces the four loose session keys with a ten-minute, opaque credential/MFA fingerprint contract. Completion locks the User, revalidates lifecycle/credentials, consumes successful proof, registers the rotated session before releasing the lock and records method provenance. The POST route serializes requests for one session; failed OTP input can retry within the original lifetime/rate limit. Normal password/Google/passkey success clears pending proof. Thirteen regressions cover all primary/second-factor pairs, credential removal/change, MFA replacement, anonymization, expiry without consuming recovery codes, retry and proof replay. Full PHPStan passes; Pint and 62 Architecture tests pass (66,675 assertions). PostgreSQL CI on `68f1cb4c` passes 12 of 13 new cases; one Google callback fixture hits the shared CI IP rate limit before reaching the handler, also leaving its unused mock expectation. The correction gives each test case a distinct client address without changing production throttles; a fourteenth case verifies the actual five-attempt limit. All 14 MFA cases now pass in PostgreSQL CI on `b3b8daf4` (769 total tests); only the two new passkey HTTP cases fail under HARD-023. The route-boundary repair is now fully verified on `6fb42491`, and all nine workflows pass.
- Completion evidence: CI `34260552271`, PHP job `102177121125`, Architecture `34260552412`, Intelligence `34260552267` and all other workflows pass; 770 tests, 74,107 assertions.
- Commit SHA: `68f1cb4c38244394be5e2d641a568a849e3b9a40`; fixture correction `b3b8daf4867f4f1fd07b56d277a3116ce3e01320`; containing verification `6fb42491d5f998bb57de1ec1c007a36c3c789f61`.

### HARD-022 — Passkey last-method check is outside the account lock

- Area: Accounts sign-in-method deletion.
- Finding: AccountPasskey performs a policy check in a model deleting callback, while password/Google removals serialize their checks under the User lock. The passkey callback alone does not make check-and-delete atomic against another method removal.
- Current owner: AccountPasskey and the package's deletion route.
- Intended authoritative owner: Accounts credential mutation under the same User lock as other method changes, retaining maintained package verification.
- Rationale: concurrent removals must not each observe another usable method and leave the account without any.
- Remediation: inspect the installed package deletion transaction and route boundary, reproduce any missing serialization, then centralize mutation policy/locking without duplicating WebAuthn cryptography.
- State: Complete.
- Verification required: current package delete authorization and last-method behavior under concurrent method removal; existing passkey security/credential suites.
- Verification result: the installed package DeletePasskey Action performs delete/event dispatch without a transaction; its controller checks ownership but adds no User lock. Accounts now binds that package Action to DeleteAccountPasskey, which locks User then current owned passkey and applies the central last-method policy before deletion. Removed the unlocked model callback. Existing removal tests invoke the package binding; two new HTTP cases preserve ownership, final-method denial, audit, security notifications and response behavior. A separate committed-fixture test invokes RemovePassword over a second PostgreSQL connection while passkey deletion holds the User lock, expects bounded lock contention, then verifies the remaining password cannot be removed. Full PHPStan and Pint pass; 62 Architecture tests pass. The competing-connection regression passes in PostgreSQL CI on `b3b8daf4`: RemovePassword receives SQLSTATE 55P03 while the User lock is held, and the final remaining method stays protected. Existing binding/policy cases pass. Two new HTTP cases receive game-context 409 before Accounts; HARD-023 records and repairs this separate production defect. All HTTP, policy and concurrency cases now pass on `6fb42491`, with all nine workflows green.
- Completion evidence: CI `34260552271`, PHP job `102177121125`, Architecture `34260552412`, Intelligence `34260552267` and all other workflows pass; 770 tests, 74,107 assertions.
- Commit SHA: `b3b8daf4867f4f1fd07b56d277a3116ce3e01320`; containing verification `6fb42491d5f998bb57de1ec1c007a36c3c789f61`.

### HARD-023 — Account passkey writes require game authority context

- Area: GameWorld/Players web precondition and Accounts passkey routes.
- Finding: RequireCurrentPlayerContextVersion exempts account/profile/password routes but omits the maintained package's passkey route prefix. Registration, confirmation and deletion are rejected with game-context 409 for an authenticated account without an active Governor, before their account security checks can run.
- Current owner: GameWorld/Players route precondition and package account routes.
- Intended authoritative owner: Accounts authenticates and authorizes passkey operations; game mutations retain the current Player authority precondition.
- Rationale: account security must be usable independently of game identity, without requiring or granting game authority.
- Remediation: add the explicit account passkey prefix to the existing exemption registry; retain authentication/recent-proof/ownership/WebAuthn/rate-limit checks and game mutation tests.
- State: Complete.
- Verification required: real package deletion, foreign/final-method denial, registration/confirmation input validation without any Governor; unchanged stale/missing/current game-context behavior.
- Verification result: the two HARD-022 HTTP cases reproduce the misplaced 409 gate. The account prefix is now exempt; an additional real HTTP case covers registration and confirmation reaching their own validation without a Governor. Full PHPStan passes with zero errors; changed-file Pint, diff checks and documentation links pass. All package HTTP regressions, existing game-context preconditions and the full PostgreSQL suite pass on `6fb42491` (770 tests, 74,107 assertions); PHP job `102177121125`. All nine containing workflows pass.
- Completion evidence: CI `34260552271`, PHP job `102177121125`, Architecture `34260552412`, Intelligence `34260552267` and all other workflows pass; 770 tests, 74,107 assertions.
- Commit SHA: `6fb42491d5f998bb57de1ec1c007a36c3c789f61`.

### HARD-024 — Profile passkey list repeats removal-policy queries

- Area: Accounts ProfileController and AccountSignInMethodPolicy projection.
- Finding: the profile computes a sign-in-method summary, then performs passkey ownership/count/provider queries again for every already account-scoped listed passkey. Other removal flags also repeat the same counts.
- Current owner: ProfileController projection and central Accounts policy.
- Intended authoritative owner: one read-time Accounts method summary with derived removal eligibility; owner Actions retain locked policy revalidation.
- Rationale: the number of displayed credentials must not multiply database round trips or introduce a second UI permission authority.
- Remediation: derive projection eligibility once through the policy, preserve locked mutation checks, and verify a constant query budget with multiple own/foreign credentials and final-method states.
- State: Complete.
- Verification required: one versus many passkeys use a constant query count; correct removal flags and account isolation; mutation concurrency/last-method tests remain intact.
- Verification result: The policy summary now derives all removal flags from one current credential count. Profile uses that summary for its already account-scoped passkey list; mutation Actions retain owner locks and current ownership/policy revalidation. Three projection cases cover one versus 40 passkeys, foreign credential isolation, each final-method kind and combined password/Google removal. Full PHPStan and changed-file Pint pass. All three projection cases pass on `b4bdd283`: one and 40 passkeys use the same six queries, with correct scope and removal flags. All nine workflows pass.
- Completion evidence: CI `34261161981`, PHP job `102179161063`, Architecture `34261161938`, Intelligence `34261161939` and all other workflows pass; 773 tests, 74,178 assertions.
- Commit SHA: `b4bdd283a775549afd85fd97041d5e958cd13bc1`.

### HARD-025 — Failed invitation onboarding leaves Player ownership or registration committed

- Area: AccountOnboarding workflow, Accounts registration and Alliance invitation acceptance.
- Finding: AcceptInvitationForAccount commits ClaimPlayerAccount before AcceptInvitation checks the account email and current Alliance/roster state. A denied invitation can leave the Player claimed by the rejected account. RegisterAccount separately commits the new account and claim before invitation acceptance, and RegisterUser sends verification mail before any enclosing onboarding operation could finish.
- Current owner: AccountOnboarding sequences independently transactional Accounts/GameWorld/Alliance Actions.
- Intended authoritative owner: owner Actions retain all validation, locking and persistence; the onboarding command must have an explicit atomic composition boundary for its dependent changes and after-commit external effects.
- Rationale: a failed invitation must not grant Player ownership or leave an unusable partial registration; preflight snapshot checks alone cannot close state-change races. The current blanket prohibition on Workflow transactions conflicts with this dependent command invariant and requires an explicit architecture decision, not a hidden transaction wrapper.
- Remediation: define and document the narrowly scoped atomic composition rule, implement consistent current-account validation and owner-action rollback, defer verification delivery until commit, and exercise real wrong-email/stale/inactive/claimed invitation failures and success.
- State: Complete.
- Verification required: rejected existing-account acceptance leaves ownership/history/audit unchanged; failed registration leaves no account/identity/claim/outbox/mail; successful onboarding commits all owner effects once; current owner checks and architecture remain enforced.
- Verification result: RegisterAccount and AcceptInvitationForAccount now compose the existing owner Actions atomically under ADR-0019. Registration reuses the existing-account acceptance sequence; acceptance obtains a locked current account snapshot and rejects finalization. Verification mail waits for the outermost commit. Twelve real HTTP/owner cases cover wrong email, inactive Alliance, password/Google rollback after roster/lifecycle/ownership rejection, actual second-connection invitation revocation, successful commit/mail timing, replay and finalized accounts. Full PHPStan, changed-file Pint and all 62 Architecture tests pass (66,719 assertions). All 12 new regressions pass in PostgreSQL CI on `685ee1c6`, including the competing connection and mail timing: 785 tests, 74,246 assertions; PHP job `102182181259`. All nine workflows pass, including image/staging/recovery. The competing-connection fixture now also has an explicit one-second lock timeout so unexpected contention fails promptly.
- Completion evidence: CI `34262055468`, PHP job `102182181259`, Architecture `34262055478`, Intelligence `34262055528` and all other PR workflows pass.
- Commit SHA: `685ee1c65fdb6b5283dcc8d1deedff37b2a7469a`.

### HARD-026 — Account deletion request and account lifecycle can diverge

- Area: Platform/DataGovernance and Accounts lifecycle handoff.
- Finding: RequestAccountDeletion and CancelAccountDeletion commit the Platform transition before invoking the Accounts lifecycle writer. A competing transition or later failure can leave deletion_requested_at/audit inconsistent with the durable request. Repeated requests reset the cooling-off deadline, processed requests still call the lifecycle writer, and re-requesting after cancellation reuses an already-consumed notification key.
- Current owner: Platform deletion request/process Actions and Accounts lifecycle.
- Intended authoritative owner: the same owner APIs with consistent account-first serialization and transactional lifecycle intent.
- Rationale: request/cancel/process must agree on one current lifecycle, preserve replay semantics and prevent a late transition from restoring account metadata after completion.
- Remediation: acquire Accounts then the request in every transition; compose owner lifecycle/audit/Communications intent in one transaction; preserve pending/blocked/processed replays and give genuinely new request cycles new notification intent.
- State: Complete.
- Verification required: request/cancel rollback preserves both owners, repeat/processed transitions do not emit contradictory effects, competing transitions share the account lock, and normal release/security notification behavior remains intact.
- Verification result: all three Platform transitions acquire the current Accounts lock before request state. Request/cancel invoke Accounts inside their transaction; Accounts publishes only transactional Communications intent, with no network delivery under the lock. Replays preserve deadlines and do not duplicate audit/notifications; cancellation followed by a new request produces a new intent. Six real regressions cover injected notification persistence failure on request/cancel, complete lifecycle replay, processed-state protection and cancellation over a second PostgreSQL connection during request/process. Full PHPStan, Pint and all 62 Architecture tests pass (66,719 assertions). PostgreSQL CI on `26012474` passes five of six new cases, including both failure rollbacks and competing-connection checks. The exact-deadline case compares a microsecond-bearing frozen clock to second-precision persisted timestamps; its fixture now freezes a whole second while retaining the exact seven-day/account-date assertions. Full run: 791 tests / 74,280 assertions, one failure; PHP job `102185431754`. The corrected case and all six deletion regressions pass on `3139868a` (799 tests, 74,337 assertions), PHP job `102188173159`. All nine containing workflows pass. Player ownership writers are separately tracked under HARD-027.
- Completion evidence: CI `34263846408`, PHP job `102188173159`, Architecture `34263846352`, Intelligence `34263846441` and all other workflows pass; 799 tests, 74,337 assertions.
- Commit SHA: `26012474ba14f442590bc7bdfa6572309ffaad22`; containing verification `3139868a09edde1700bd7ebfe2913f1a43d8e608`.

### HARD-027 — Player claims and reconciliation do not coordinate with account finalization

- Area: GameWorld/Players ownership and Platform account processing.
- Finding: ClaimPlayerAccount locks only the Player; CreatePlayerForAccount can lock/persist the Player before checking the account; ReconcilePlayers transfers account ownership directly between locked Player rows. These paths do not acquire the Accounts lifecycle lock or reject a finalized account, while finalization must enumerate and release the complete ownership set.
- Current owner: GameWorld claim/create/reconcile Actions and Platform finalization.
- Intended authoritative owner: Accounts supplies current lifecycle serialization; GameWorld retains all ownership/history writes; Platform coordinates release through those owner APIs.
- Rationale: finalization must not miss a concurrent new ownership assignment or leave game identities attached to an anonymized account. Lock order must remain consistent when an operation touches account and Player rows.
- Remediation: trace all ownership writers and release/reconciliation entry points, require current account lifecycle before assigning/transferring ownership, revalidate any routing snapshot after locks and verify actual competing-connection finalization/claim behavior.
- State: Complete.
- Verification required: finalized accounts cannot claim/create/receive reconciled ownership; concurrent claims and finalization serialize without missing Players; normal reconciliation and identity provenance remain correct.
- Verification result: All assignment/release writers and callers are traced. Accounts lockActive owns the finalized-account rejection; claim/create/voluntary release acquire it before Player writes, and bulk release retains the current account lock. Reconciliation locks discovered accounts then Players in deterministic order and rejects changed owner snapshots. Eight real database cases cover finalized assignment kinds, both finalization/assignment lock orders, complete released history after claim/create/reconciliation, and a second-connection claim changing the routing snapshot. Full PHPStan, changed-file Pint and all 62 Architecture tests pass (66,719 assertions). All eight ownership/finalization cases pass on `3139868a`, including actual competing connections in both lock orders and changed-snapshot rejection; full suite 799 tests, 74,337 assertions. All nine containing workflows pass.
- Completion evidence: CI `34263846408`, PHP job `102188173159`, Architecture `34263846352`, Intelligence `34263846441` and all other workflows pass; 799 tests, 74,337 assertions.
- Commit SHA: `3139868a09edde1700bd7ebfe2913f1a43d8e608`; containing verification `3139868a09edde1700bd7ebfe2913f1a43d8e608`.

### HARD-028 — Blocked deletion requests can starve later eligible requests

- Area: Platform/DataGovernance bounded deletion worker.
- Finding: ProcessAccountDeletionRequests selects the oldest pending/blocked eligible rows before applying the batch limit, but blocked rows remain immediately eligible with their original ordering. A full batch of persistent legal holds/admin/leadership blockers prevents later valid requests from ever being selected.
- Current owner: ProcessAccountDeletionRequests.
- Intended authoritative owner: the same bounded worker with durable retry scheduling for blocked records.
- Rationale: a persistent blocker for one account must not halt unrelated deletion work; retry timing must survive worker restart and remain auditable.
- Remediation: add explicit due-time/retry state and query filtering before the limit, preserve the original cooling-off deadline, and verify bounded progress past a full blocked batch plus eventual retry after a blocker is removed.
- State: Complete.
- Verification required: later eligible records progress despite a full blocked batch; blocked requests retry at the defined time; cancellation/re-request/processing correctly reset retry state.
- Verification result: Blocked requests now persist a one-hour next_attempt_at while retaining eligible_at. The worker filters and orders by the effective due time before the batch limit and revalidates under lock; fresh schema adds a partial due-time index. Cancellation/re-request/processing clear retry state. Four database cases cover progress past a full blocked batch even when retries are due, exact retry timing after removing a blocker, new-cycle cooling-off and deferral after initial selection. Full PHPStan and changed-file Pint pass; All four cases and fresh PostgreSQL schema pass on `3e4a2dae`; full suite 803 tests / 74,375 assertions, all nine workflows green.
- Completion evidence: CI `34264785844`, PHP job `102191314036`, Architecture `34264785862`, Intelligence `34264785852` and all other PR workflows pass.
- Commit SHA: `3e4a2dae4a61c88fcdb4fd2eb481d4ce3da09a02`.

### HARD-029 — Credential security effects are committed separately from credential changes

- Area: Accounts credential mutations, security notification intent and passkey event adapters.
- Finding: AddPassword, RemovePassword and ChangePassword commit credential/audit state before persisting Communications security intent. RemovePassword deletes reset tokens after its transaction. Passkey event adapters similarly perform session/audit/notification work after package mutation. A later persistence failure can leave a successful credential change without its promised security effects or a retryable success response.
- Current owner: Accounts credential Actions and package event adapters, Communications intent owner.
- Intended authoritative owner: Accounts atomically coordinates credential state and required durable security effects through owner APIs; raw session cleanup and remote delivery run after commit.
- Rationale: security-effect persistence failures must not silently separate credential changes from their durable revocation/audit/notification contract. Moving external cleanup into a database transaction would create a different failure mode.
- Remediation: trace all password, MFA, passkey, Google and email effect writers; include required database effects in the owner transaction and preserve after-commit external cleanup; verify real failure/rollback and response behavior.
- State: Complete.
- Verification required: failures while recording security intent preserve credential/reset-token/audit consistency, successful changes retain session/proof behavior, package verification remains maintained, and no storage/network calls run under widened transactions.
- Verification result: First slice moves AddPassword/RemovePassword and MFA confirm/regenerate/disable security intent into their owner transactions, including password reset-token deletion. Ten database cases exercise real intent INSERT failures and successful credential/audit/message contracts. First-slice PostgreSQL CI passes all ten cases on `bd5c8640` (813 tests / 74,413 assertions), PHP job `102196149819`. Second slice composes session revocation into all three password Actions, removes the forced second rehash, refreshes current HTTP authentication and clears recent proof. Both revocation Actions defer raw cleanup until the outermost commit, discard cleanup on rollback and report storage exceptions without turning committed revocation into a failed request. All seven commit/rollback/storage/HTTP regressions and two expanded password-change intent cases pass on `79978c04` (822 tests / 74,471 assertions), PHP job `102198738895`. All nine workflows pass: CI `34266990229`, Architecture `34266990141`, Intelligence `34266990055` and remaining capability/frontend/image/security/visual gates. Full PHPStan and changed-file Pint pass. Third slice adds account-locked maintained registration and moves deletion events inside the owner transaction; proof clearing waits for commit. Rename replays are no-ops and genuine later renames have fresh occurrence keys. Eight database cases cover real maintained registration, origin/challenge/user-verification rejection, duplicate credentials, event rollback and rename replay. The synthetic browser fixture passes the installed maintained validator locally, including all three invalid cases; no package crypto is mocked. Full PHPStan and Pint pass. Seven of eight passkey cases pass on `cb1e0048` (830 tests / 74,537 assertions, one fixture failure), PHP job `102201769980`. The deletion rollback fixture compared a prior browser to a JSON request that omitted cookies; withCredentials now preserves the intended session while keeping every rollback/proof assertion. Final slice moves Google connect into one Accounts owner Action, removes the superseded CreateAccountIdentity primitive and controller-side security writes, commits disconnect/session/notification effects together, and gives provider use the same account-first lock order. Eight new Google/provider cases cover injected persistence failures, replay, global ownership, real HTTP binding/disconnect and a competing PostgreSQL removal blocked at the account. Email request/promotion now save security intent inside the owner transaction and defer old-address/verification mail until outermost commit, with fresh intent per real cycle. Seven email cases cover intent failure, outer rollback, correct recipient after commit and repeated-address cycles. All final-slice cases and the corrected passkey rollback fixture pass on `e689bf7a` (845 tests / 74,611 assertions), PHP job `102204303212`. All nine workflows pass, including Architecture. HARD-030 owns recoverable mail delivery after commit. The first-slice full CI run was superseded during its container job by the next push; its PHP/frontend and eight other workflows passed, so final containing verification remains required.
- Completion evidence: all credential/session/passkey/Google/provider/email regressions pass in CI `34268634557`, PHP job `102204303212`; Architecture `34268634560`, Intelligence `34268634585` and every other containing workflow pass.
- Commit SHA: `bd5c86401a432fc698c6f6976c1a329f84b23ee0`, `79978c0433ba6ebca3b162dbdc2cb510779deeff`, `cb1e0048bf8d12b166fb6b5042ce4efdc2fe8d82`; verified containing final slice `e689bf7a5f829d12e008bf63eb0a5277f2601324`.

### HARD-030 — Account verification and email-change delivery are synchronous with no durable retry

- Area: Accounts registration/resend, pending email verification, old-address notices and outbox consumption.
- Finding: RegisterUser sends verification mail in an after-commit callback. SMTP failure or process loss can therefore leave a committed registration with a failed request or no delivery. The existing user.registered outbox record has no Accounts verification consumer; current consumers cover recruitment and Platform webhooks only. Email-change verification and old-address notices have the same after-commit process-loss/SMTP-failure gap.
- Current owner: RegisterUser, branded verification notification, email-verification resend adapter and shared outbox transport.
- Intended authoritative owner: Accounts owns verification delivery intent and current-recipient validation; existing durable transport owns retries after commit.
- Rationale: after-commit ordering prevents mail for rolled-back users, but does not establish recoverable delivery or keep mail latency/failure out of the registration response.
- Remediation: reuse the durable owner/outbox contract for registration verification, preserve branded/signature/expiry behavior and current account checks, cover resend, pending-email verification and old-address notices, and test retry after transport failure plus stale/verified/finalized account suppression.
- State: Complete.
- Verification required: registration returns after durable intent commits without SMTP, rollback creates no intent, delivery failure remains retryable, and current recipient/verification state controls later delivery.
- Verification result: First slice introduces explicit private verification-request intent for registration/resend, independently of the user.registered business event. The existing outbox publisher invokes an Accounts consumer after claim commit; it rechecks lifecycle, verification and an address fingerprint before calling the maintained branded notification. User notification hook and registration now save intent without SMTP. Nine database cases cover real registration/resend, post-insert rollback, durable SMTP backoff/retry, stale recipients and fresh delayed signatures; the onboarding regression retains all committed-owner assertions and explicitly runs the worker. First-slice CI `34269731391`, PHP job `102207964217`, passes eight of nine new cases; full suite 854 tests / 74,797 assertions with one delayed-mail fixture failure. Actual MailMessage uses HTML/text views and the existing route is /verify-email; corrected assertions retain fresh expiry and real signature verification, now outside publisher-caught callbacks. The final slice uses a typed account/pending verification target, queues pending verification and encrypted historical old-address notices atomically, and purges the latter on account finalization. Eight additional database cases cover intent failure, both SMTP retry paths, replaced/promoted pending targets, encrypted recipient scope/finalization and historical delivery. Prior email rollback/onboarding cases now explicitly run the worker and retain their commit assertions outside error-catching callbacks. Full PHPStan, Pint and all 62 Architecture tests pass (66,985 assertions before final mail rendering changes); all cases pass in PostgreSQL CI on `594f1ea7` (866 tests / 74,985 assertions), including the corrected delayed signature and complete email delivery behavior. All nine workflows pass. HARD-035 covers the real plain-text link defect found during this validation.
- Completion evidence: CI `34271255114`, PHP job `102213121398`, Architecture `34271255180`, Intelligence `34271255349` and all other containing workflows pass.
- Commit SHA: first slice `891f97a3c6cdd2953a0009d36590a2228a1dd741`; verified final slice `594f1ea73cb7fa711ab4251e92e51aacb52a418b`.

### HARD-031 — Password reset tokens are checked and consumed outside the credential lock

- Area: Accounts password reset and maintained broker integration.
- Finding: Laravel's broker validates a reset token before invoking ResetPassword's callback and deletes it after the callback transaction. Two requests can both validate the same token before serializing their password writes. The callback can also decline a stale password-less account while the broker still reports success.
- Current owner: ResetPassword and the maintained password broker/token repository.
- Intended authoritative owner: Accounts serializes current-account/token revalidation and consumption with the password mutation; the maintained broker owns token hashing, expiry and throttling.
- Rationale: reset tokens must authorize one successful current credential transition and report accurately when current state no longer permits it.
- Remediation: move current token validation/consumption within the account serialization boundary without duplicating maintained token cryptography; verify a real competing-connection reset and changed credential state.
- State: Complete.
- Verification required: one token cannot authorize two password changes; token failure/expiry/stale credential state return failure; rollback preserves retryable intent and existing successful-reset behavior.
- Verification result: ResetPassword now acquires/rechecks the current account before the entire maintained broker check/callback/delete sequence. Password, API/browser/remember revocation, audit/security intent and token consumption commit together; framework event and raw cleanup wait for outermost commit. Ten database cases cover real post-insert and post-token-delete rollback, successful one-use reset, expired/unknown/changed/finalized accounts, and actual competing reset/removal over a second PostgreSQL connection with an independent maintained broker. Full PHPStan and Pint pass. PostgreSQL CI on `8c6b8a7a` passes all ten cases, full suite 876 tests / 75,032 assertions (PHP job `102220339936`); all nine containing workflows pass. Issuance still writes from ForgotPasswordController without this boundary and is tracked separately as HARD-036.
- Completion evidence: PasswordResetAtomicityV3Test; CI `34273380939`, Architecture `34273380837`, Intelligence `34273380888` and all other workflows pass.
- Commit SHA: `8c6b8a7a29a7d0bc1ecba9a1aa579bc88e828d5e`.

### HARD-032 — In-flight account mutations can restore data after finalization

- Area: Accounts credential, MFA, identity and email/profile write boundaries.
- Finding: Several User-row-locked Actions do not recheck anonymized_at after acquiring the lock. A request authorized before finalization can wait for anonymization and then add a password, begin MFA enrollment or set a new pending email on the finalized account.
- Current owner: Accounts mutation Actions and current account lifecycle authority.
- Intended authoritative owner: Accounts rejects terminal lifecycle state at each ordinary mutation boundary; explicit anonymization/reporting APIs retain access to finalized records.
- Rationale: request middleware cannot protect an already-running writer waiting behind account finalization; the terminal state must remain durable under concurrent mutation.
- Remediation: trace all account-owned writes, establish an explicit current-active owner guard without hiding finalized records globally, and exercise both lock orders with real competing connections.
- State: In progress.
- Verification required: finalized accounts cannot regain credentials or personal profile/email data; in-flight writes serialize correctly with finalization; authorized lifecycle/reporting behavior remains intact.
- Verification result: User.ensureActive centralizes the explicit terminal guard after ordinary account locks across password, profile/email, Google/provider, passkey, MFA, session revocation and deletion lifecycle writers; AccountIdentityQuery.lockActive shares it. RecordAccountSession now takes the same account lock and refuses missing/finalized users, retaining the revoked write predicate. VerifyAccountEmail replaces controller-side fulfillment with current-active/hash revalidation and atomic audit; Verified waits for outer commit. Twenty stale-command cases protect complete terminal state and side-effect counts, six session/provider cases including actual competing connections exercise both finalization lock orders, and seven verification cases cover audit failure, outer rollback, real signed HTTP/replay/tampering and account changes after maintained form authorization. Full PHPStan, Pint, documentation links and 62 Architecture tests (67,029 assertions) pass locally; First containing CI/visual runs on e4d56df7 were superseded by the cleanup checkpoint. f606b14f exposed a stalled fixture: Laravel preserves the copied connection name, so a successfully hydrated competitor model saved through the primary connection while holding the competing lock. The fixture now explicitly names its competing connection, preserving real independent writes and both lock-order assertions; the two earlier passkey/provider blocked-writer fixtures receive the same correction. PostgreSQL/containing verification remains pending.
- Completion evidence: pending.
- Commit SHA: `e4d56df7dad3595e6a8c15ee42fd5396443b1621`.

### HARD-033 — Account finalization touches raw session storage before committing

- Area: Accounts anonymization and Platform deletion processing.
- Finding: AnonymizeAccount destroys raw session storage while holding its account transaction. A storage exception can abort finalization, and a later database rollback cannot restore sessions already deleted. This differs from the hardened ordinary revocation boundary.
- Current owner: AnonymizeAccount.
- Intended authoritative owner: Accounts terminal lifecycle/database revocation commits first; best-effort raw session cleanup executes after the outermost commit.
- Rationale: terminal lifecycle already denies stale browser access. External storage must not hold up the database owner transaction or make rollback externally partial.
- Remediation: defer captured session cleanup to the outermost commit, report cleanup failure without undoing committed finalization, and retain rejection of stale session tracking.
- State: In progress.
- Verification required: outer rollback preserves raw sessions, successful finalization cleans them only after commit, and failed cleanup cannot restore access or block account processing.
- Verification result: AnonymizeAccount captures session IDs under lock and registers individual cleanup only after the outermost commit. Three database cases cover direct outer commit/replay/missing accounts, a real late Platform processed-outbox failure that rolls back account/request/session/audit state without cleanup, and a failing raw handler while remaining sessions and two deletion requests finish. Callback observations are asserted outside the error-catching boundary. Full PHPStan and Pint pass; PostgreSQL/containing gates pending.
- Completion evidence: pending.
- Commit SHA: `f606b14f39347563b289e5a575ec467be720720c`.

### HARD-034 — Email-change verification requests have no route rate limit

- Area: Accounts profile email-change entry point.
- Finding: The authenticated recent-proof PATCH email route accepts repeated pending-address requests without a rate limiter, unlike verification resend and other credential mutations. An account can request unbounded verification mail to successive addresses during the proof window.
- Current owner: routes/account.php and RequestAccountEmailChange.
- Intended authoritative owner: the same thin route adapter applies bounded account-level request throttling; Accounts retains current-state validation and intent writes.
- Rationale: durable queuing must not turn repeated requests into unbounded mail workload or recipient abuse.
- Remediation: add a named account-scoped limiter consistent with verification resend and verify actual HTTP rejection before any further owner intent is written.
- State: In progress.
- Verification required: accepted requests are bounded per account/time window, excess requests create no additional pending transition or mail intent, and legitimate retries resume after the window.
- Verification result: EmailVerification registers the named account-email-change limiter (six attempts per account per minute); the authenticated recent-proof PATCH route applies it. One actual HTTP regression uses the production limiter definition with isolated cache, consumes six requests across distinct IPs, rejects the seventh without changing profile/audit/message/delivery intent, proves another account has its own budget, and retries successfully after 61 seconds. Full PHPStan and Pint pass; PostgreSQL/containing verification pending.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-035 — Plain-text security mail HTML-escapes actionable URLs

- Area: Accounts branded verification/reset/email-change mail rendering.
- Finding: The plain-text Blade template uses HTML escaping, turning signed/reset query separators into &amp; and invalidating links followed from that MIME part. Chaining MailMessage.text also replaces view data and drops the HTML eyebrow configured by the earlier view call.
- Current owner: four branded account notifications and their shared HTML/text templates.
- Intended authoritative owner: the same templates with format-correct output and one complete data set shared by both MIME parts.
- Rationale: valid notification objects and fake sends do not establish that a delivered link works. HTML and plain text require different escaping behavior.
- Remediation: render literal text/URLs in the text-only view while keeping HTML escaped, supply both views with one complete data set, and validate actual rendered action links in all four messages.
- State: Complete.
- Verification required: both rendered parts retain branding/action URLs; extracted plain-text verification URLs satisfy the real signature validator; reset query separators remain literal; containing mail/CI gates pass.
- Verification result: four database-free rendered-mail cases pass locally (32 assertions), including actual signed-link extraction/validation for account and pending verification. All four notification types render both parts successfully. Formatter passes; all four rendering cases and delayed signatures pass in PostgreSQL CI on `594f1ea7` (866 tests / 74,985 assertions). All nine workflows pass.
- Completion evidence: AccountSecurityMailRenderingV3Test and durable mail tests; CI `34271255114`, PHP job `102213121398`, Architecture `34271255180`, Intelligence `34271255349` and all other workflows pass.
- Commit SHA: `594f1ea73cb7fa711ab4251e92e51aacb52a418b`.

### HARD-036 — Password-reset issuance bypasses the account owner transaction

- Area: Accounts forgot-password token issuance and reset-link delivery.
- Finding: ForgotPasswordController reads password availability without locking, then invokes the maintained broker to replace reset tokens and send SMTP directly. Issuance does not serialize with reset/removal/finalization, can replace a token during reset consumption, and has no durable delivery retry after token creation.
- Current owner: ForgotPasswordController and the maintained broker/token repository.
- Intended authoritative owner: an Accounts issuance Action coordinates current account eligibility, maintained token generation/throttling and recoverable delivery intent; HTTP remains a generic non-enumerating adapter.
- Rationale: all token writers must share the credential lifecycle boundary. A generic success response must not conceal partial token writes or expose clear recovery material in transport history.
- Remediation: serialize issuance account-first, preserve maintained generation/hashing/expiry/throttling, persist protected and bounded delivery intent, and keep SMTP outside the owner transaction. Remove superseded controller-side writes and ensure removal/reset/finalization invalidates pending delivery.
- State: Planned.
- Verification required: real competing issuance/reset/removal behavior, no tokens for password-less/finalized accounts, generic HTTP response, bounded repeated issuance, delivery rollback/retry/stale suppression and protected recovery material cleanup.
- Verification result: ForgotPasswordController, User reset notification hook and installed PasswordBroker/DatabaseTokenRepository creation/deletion paths traced; implementation pending.
- Completion evidence: pending.
- Commit SHA: pending.

### HARD-037 — Login proof and session registration can straddle credential revocation

- Area: Accounts password/provider/remembered/passkey login boundaries.
- Finding: The password controller validates through Auth.attempt and records login audit before TrackAccountSession registers the rotated session after the controller returns. A concurrent credential change can revoke the currently registered sessions between primary proof validation and registration, allowing an in-flight login to create a new unrevoked row from old proof. Google completion similarly ends its identity-use transaction before session establishment. Middleware lifecycle checking alone does not bind current credential proof to registration.
- Current owner: login controllers, maintained authentication/passkey integrations, MFA completion and session tracking.
- Intended authoritative owner: Accounts completes current credential proof, required assurance, login audit and durable session registration under an explicit owner boundary; maintained packages retain password/WebAuthn validation.
- Rationale: revocation must include sessions whose proof predates the transition, and later failures must not silently leave partially authenticated sessions. Maintained password rehash/remember-token writes also need current lifecycle coordination.
- Remediation: trace each login/recaller/passkey boundary, reproduce the stale-proof race with competing connections, consolidate owner completion and preserve HTTP/session semantics without holding external session storage inside a database transaction.
- State: Planned.
- Verification required: real competing credential removal/reset/finalization cannot grant new access from stale proof; valid password, Google, MFA, remembered and passkey logins retain their intended assurance; audit/storage failures cannot leave partial authentication.
- Verification result: AuthenticatedSessionController and TrackAccountSession confirm the post-controller registration gap; Google completeLogin has the same separation. MFA currently binds current fingerprints under lock; remaining maintained login/rehash/session storage paths need tracing before implementation.
- Completion evidence: pending.
- Commit SHA: pending.

## Repository audit coverage

All rows below remain Planned until actual production paths have been traced. This table tracks audit scope, not discovered defects.

| Area | Required authority/scalability review | State |
| --- | --- | --- |
| Accounts | Identity/provider queries, authentication/credential owners, sessions, MFA, profile/email/reset and account-side deletion traced; repairs under HARD-018–024. Registration/invitation atomicity and deletion/finalization coordination verified under HARD-025–028; credential effects and verification delivery verified under HARD-029/030/035; reset, terminal guards, cleanup, throttling and login proof freshness remain under HARD-031–037 | In progress |
| GameWorld | Progression dataset/topology/prerequisite and Gift Code reminder paths traced (HARD-007/009/011/012); Governors, Kingdoms/transfers/governance, remaining Gift Codes/calculators and KingdomMaps audit remain | In progress |
| Alliance | Lifecycle, membership/rank/delegation, recruitment, content, territories/hive planning | Planned |
| Operations | Events, participation, rallies, King Perks, results/Bear Hunt and reminders | Planned |
| Intelligence | Evidence/Roster structured pipeline and all-family GameEvidence retention/redaction/summary queries verified under HARD-008/010/013–017; observations, other evidence families, ingestion, contributions and projections/signals remain | In progress |
| Communications | Preferences/recipients, inbox, delivery channels, digests, retry/idempotency and revocation | Planned |
| Platform | DataGovernance account request/cancel/process traced with HARD-026–028 findings; administration, integrations/API credentials, webhooks, other retention and operational controls remain | In progress |
| Workflows/ReadModels | NotificationDelivery authority/mutations verified under HARD-005; progression prerequisite provenance under HARD-012; other orchestration, dashboards and Assistant/API projections remain | In progress |
| Infrastructure/entry points | Scheduler registration/commands verified by HARD-003; route authorization, shared mechanisms, queues/listeners/outbox and middleware audit remain | In progress |
| Frontend | Pages, components, composables/stores, server contracts, localization, receipts and accessibility | Planned |
| Schema/verification/operations/docs | Baseline gates, fresh schema, image/recovery and current repaired contracts verified; Evidence retention/query indexes and budgets under HARD-014/017; remaining capability indexes/operations/contracts audit remains | In progress |

## Execution adjustments

- After HARD-003, HARD-005 implements the ownership move but remains In progress until database-backed behavior passes. Local PHP/architecture/command checks are available; local PostgreSQL is not. HARD-006–008 are processed next because baseline formatter/architecture/static-analysis failures prevent CI from reaching those behavior tests. No gate is skipped or weakened; return to HARD-005 verification after these prerequisite repairs.
