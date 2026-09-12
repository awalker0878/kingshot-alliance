# Codebase hardening delivery ledger

## Resume header

- Program state: In progress. Keep PR #163 draft until the full ledger, repository audit and final gates are complete.
- Baseline: `main` at `7e780521295e868005ecfee5bd38b33e8215ec49`; branch `astra/codebase-hardening`.
- Latest pushed checkpoint before this continuation: `cb8c652c1d520cfed6927ada9a41a93d1c0d4760`. All nine normal workflows pass. CI `34709928296` checks merge revision `e9a9d7532da5497d506411141869fd9a4be9f92b`: PHP job `103596747843` passes 1,771 tests / 86,390 assertions in 18:35.019, Pint and types. Container/staging/recovery job `103599626666` and frontend job `103596747773` pass. Other workflows: Architecture `34709928235`, Gift Code `34709928408`, KingdomMaps `34709928263`, Dependency Review `34709928316`, CodeQL `34709928326`, King Perks `34709928306`, Intelligence `34709928262`, Visual `34709928271`.
- Current item/state: HARD-109/110 In progress. Passing prior tests did not cover IPv4-mapped IPv6, proxy bypass, body/signature byte mismatch, durable retry exhaustion or global recipient fan-out. This continuation repairs those gaps under the existing Integrations owner.
- Active files: Integrations destination/transport, delivery/queue/fan-out owners, canonical fresh migration, owner regression tests, ADR-0053/0054 and event/operations guidance. No competing TransferManagement choice implementation is applied.
- Local verification: changed PHP formatting and Integrations PHPStan pass. Destination-policy/transport-option suite passes 48 tests / 61 assertions. PostgreSQL is unavailable in this execution environment; database-backed cases are authored and must pass hosted verification. No local database pass is claimed.
- Next action: publish coherent HARD-109/110 checkpoint; inspect focused hosted Integrations and normal containing results; resolve failures, finish HARD-095 management catalogues/cohort choices, reconcile HARD-106/107/108 evidence, then complete the path-by-path repository audit.
- Remaining gates: all applicable PHP, architecture, capability, frontend, browser, fresh-schema, security, dependency, image, staging and recovery gates on the final immutable candidate. Remove temporary validation/publication/diagnostic workflows and stale publication manifests before final completion.

Checkpoint SHAs identify preceding durable implementations; Git history supplies each documentation checkpoint without circular self-reference.

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
- State: Complete.
- Verification required: finalized accounts cannot regain credentials or personal profile/email data; in-flight writes serialize correctly with finalization; authorized lifecycle/reporting behavior remains intact.
- Earlier verification: User.ensureActive centralizes the explicit terminal guard after ordinary account locks across password, profile/email, Google/provider, passkey, MFA, session revocation and deletion lifecycle writers; AccountIdentityQuery.lockActive shares it. RecordAccountSession now takes the same account lock and refuses missing/finalized users, retaining the revoked write predicate. VerifyAccountEmail replaces controller-side fulfillment with current-active/hash revalidation and atomic audit; Verified waits for outer commit. Twenty stale-command cases protect complete terminal state and side-effect counts, six session/provider cases including actual competing connections exercise both finalization lock orders, and seven verification cases cover audit failure, outer rollback, real signed HTTP/replay/tampering and account changes after maintained form authorization. Full PHPStan, Pint, documentation links and 62 Architecture tests (67,029 assertions) pass locally; First containing CI/visual runs on e4d56df7 were superseded by the cleanup checkpoint. f606b14f exposed a stalled fixture: Laravel preserves the copied connection name, so a successfully hydrated competitor model saved through the primary connection while holding the competing lock. The fixture now explicitly names its competing connection, preserving real independent writes and both lock-order assertions; the two earlier passkey/provider blocked-writer fixtures receive the same correction. All 33 terminal/verification cases pass on 8c4901c3 (913 tests / 75,260 assertions, two unrelated HARD-033 fixture failures), PHP job 102231061580. Full containing verification remains pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: `e4d56df7dad3595e6a8c15ee42fd5396443b1621`.

### HARD-033 — Account finalization touches raw session storage before committing

- Area: Accounts anonymization and Platform deletion processing.
- Finding: AnonymizeAccount destroys raw session storage while holding its account transaction. A storage exception can abort finalization, and a later database rollback cannot restore sessions already deleted. This differs from the hardened ordinary revocation boundary.
- Current owner: AnonymizeAccount.
- Intended authoritative owner: Accounts terminal lifecycle/database revocation commits first; best-effort raw session cleanup executes after the outermost commit.
- Rationale: terminal lifecycle already denies stale browser access. External storage must not hold up the database owner transaction or make rollback externally partial.
- Remediation: defer captured session cleanup to the outermost commit, report cleanup failure without undoing committed finalization, and retain rejection of stale session tracking.
- State: Complete.
- Verification required: outer rollback preserves raw sessions, successful finalization cleans them only after commit, and failed cleanup cannot restore access or block account processing.
- Earlier verification: AnonymizeAccount captures session IDs under lock and registers individual cleanup only after the outermost commit. Three database cases cover direct outer commit/replay/missing accounts, a real late Platform processed-outbox failure that rolls back account/request/session/audit state without cleanup, and a failing raw handler while remaining sessions and two deletion requests finish. Callback observations are asserted outside the error-catching boundary. Full PHPStan and Pint pass. 8c4901c3 completes 913 tests / 75,260 assertions with two fixture assertions expecting plaintext in encrypted session_id columns. Commit timing, complete rollback and continuing cleanup assertions pass; fixtures now check session_id_hash plus decrypted model values while retaining every behavioral assertion. Containing gates pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: `f606b14f39347563b289e5a575ec467be720720c`.

### HARD-034 — Email-change verification requests have no route rate limit

- Area: Accounts profile email-change entry point.
- Finding: The authenticated recent-proof PATCH email route accepts repeated pending-address requests without a rate limiter, unlike verification resend and other credential mutations. An account can request unbounded verification mail to successive addresses during the proof window.
- Current owner: routes/account.php and RequestAccountEmailChange.
- Intended authoritative owner: the same thin route adapter applies bounded account-level request throttling; Accounts retains current-state validation and intent writes.
- Rationale: durable queuing must not turn repeated requests into unbounded mail workload or recipient abuse.
- Remediation: add a named account-scoped limiter consistent with verification resend and verify actual HTTP rejection before any further owner intent is written.
- State: Complete.
- Verification required: accepted requests are bounded per account/time window, excess requests create no additional pending transition or mail intent, and legitimate retries resume after the window.
- Earlier verification: EmailVerification registers the named account-email-change limiter (six attempts per account per minute); the authenticated recent-proof PATCH route applies it. One actual HTTP regression uses the production limiter definition with isolated cache, consumes six requests across distinct IPs, rejects the seventh without changing profile/audit/message/delivery intent, proves another account has its own budget, and retries successfully after 61 seconds. Full PHPStan and Pint pass. The real HTTP limiter case passes on 8c4901c3 (913 tests / 75,260 assertions, two unrelated HARD-033 fixture failures), PHP job 102231061580. Containing verification pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: `8c4901c3de0d95b519d940309d82c12fd7896ce1`.

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
- State: Complete.
- Verification required: real competing issuance/reset/removal behavior, no tokens for password-less/finalized accounts, generic HTTP response, bounded repeated issuance, delivery rollback/retry/stale suppression and protected recovery material cleanup.
- Earlier verification: RequestPasswordReset locks/rechecks the current account, uses maintained broker generation/throttling and atomically queues through the User hook. QueuePasswordResetDelivery validates the current maintained token and stores a unique delivery ID plus encrypted pending token only on the existing token row. The outbox carries only the ID; its consumer rechecks scope/account/token/expiry, sends outside transactions, sanitizes transport failures and clears encrypted material after delivery. Password addition/change/removal, email promotion and finalization use maintained token invalidation inside their transactions; reset already consumes it. Indexed canonical schema and central hourly auth:clear-resets schedule bound expired secrets even after retry exhaustion. Nineteen database cases cover real generic HTTP, encrypted scope, maintained throttle/replay, late intent rollback, SMTP retry/sanitization, seven stale transitions, expiry cleanup, foreign account isolation and real competing reset/removal/finalization. The existing reset-consumption fixture adds competing issuance and an explicitly distinct connection name. Full PHPStan, changed-file Pint, documentation links and 62 Architecture tests pass (67,220 assertions); PostgreSQL CI on 6873e7ed passes all 19 new cases and all corrected HARD-032/033/034 cases (933 tests / 75,595 assertions), PHP job 102234528186; only the expanded competing-reset case errors on a missing RequestPasswordReset import. The import is corrected without changing its concurrency assertions; containing verification remains pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: `6873e7ed7f96ceb3c13ed8089620ec615213378e`.

### HARD-037 — Login proof and session registration can straddle credential revocation

- Area: Accounts password/provider/remembered/passkey login boundaries.
- Finding: The password controller validates through Auth.attempt and records login audit before TrackAccountSession registers the rotated session after the controller returns. A concurrent credential change can revoke the currently registered sessions between primary proof validation and registration, allowing an in-flight login to create a new unrevoked row from old proof. Google completion similarly ends its identity-use transaction before session establishment. Middleware lifecycle checking alone does not bind current credential proof to registration.
- Current owner: login controllers, maintained authentication/passkey integrations, MFA completion and session tracking.
- Intended authoritative owner: Accounts completes current credential proof, required assurance, login audit and durable session registration under an explicit owner boundary; maintained packages retain password/WebAuthn validation.
- Rationale: revocation must include sessions whose proof predates the transition, and later failures must not silently leave partially authenticated sessions. Maintained password rehash/remember-token writes also need current lifecycle coordination.
- Remediation: trace each login/recaller/passkey boundary, reproduce the stale-proof race with competing connections, consolidate owner completion and preserve HTTP/session semantics without holding external session storage inside a database transaction.
- State: Complete.
- Verification required: real competing credential removal/reset/finalization cannot grant new access from stale proof; valid password, Google, MFA, remembered and passkey logins retain their intended assurance; audit/storage failures cannot leave partial authentication.
- Earlier verification: Password, Google and registration controllers establish sessions before separately committing login audit/tracking; maintained PasskeyLoginController likewise logs in after VerifyPasskey returns. VerifyPasskey locks the credential first, validates/updates its counter and emits PasskeyVerified inside its package transaction; Accounts must preserve maintained validation while coordinating the account-first lifecycle. CompleteMfaLogin binds current fingerprints and consumes recovery under lock but calls Auth.login within that transaction; SessionGuard.updateSession destroys raw storage through regenerate(true). Maintained Event.defer can buffer Login/Authenticated until an explicit completion succeeds, avoiding a custom event dispatcher. Current-proof owner completion must recheck the selected credential and account/MFA/remember state, keep raw session writes outside database locks, retain recovery-code atomicity, and invalidate prepared authentication on pre-commit failure. The first implementation slice adds immutable VerifiedAccountLogin and AccountLoginProofs over existing authoritative credential/remember/MFA state. AuthenticateWithPassword uses maintained provider validation/rehashing under the active account lock and starts MFA without a transient login. CompleteAccountLogin prepares raw guard rotation outside database transactions, then rechecks proof and atomically commits recovery consumption, initial remember token, session registration and login audit. It rejects enclosing transactions; maintained Login/Authenticated events defer until commit, and failed preparation/commit clears transient authentication while preserving valid MFA retries. CompleteMfaLogin delegates; challenge state uses the same proof contract. Fifteen new database cases cover actual HTTP event/remember/session ordering, six late/storage failure and retry cases, five real competing credential/lifecycle transitions during raw rotation, competing revocation during final registration, MFA guest assurance and enclosing-transaction rejection. Existing 14 MFA HTTP cases now use real commits. Full PHPStan/Pint, documentation links and 62 architecture cases pass locally (67,527 assertions); ADR-0020 records the explicit boundary and consequences. PostgreSQL verification pending. The password/MFA slice and corrected passkey fixtures are pushed in b3c58f3c. PostgreSQL CI runs 979 tests with nine fixture failures: eight absent-token checks incorrectly expect null from the getter that normalizes to an empty string, and one old session integration suite uses an enclosing fixture transaction. Assertions now check the raw persisted token column and the suite uses real commits; all passkey confirmation cases and password/MFA success/concurrency/stale-primary paths pass. Cleanup also clears the prepared guard ID before logout to avoid reloading it and emitting Authenticated on an aborted raw rotation; the six failure cases now observe both authentication events. Containing verification remains pending. The second slice adds AuthenticateWithGoogle with current active account/exact verified subject binding and provider-use writes under the owner lock, then the same challenge/completion path. Google/password registration now complete against current credentials after onboarding commits; password auto-login pins the exact registration account. Google connection delegates current proof to ConfirmGoogleAccount. Detached Workflow login/audit, MFA lookup and the unused MFA query are removed. Eleven additional cases cover five real competing Google transitions, three rejected account/subject/removed-identity bindings, exact registration account binding, actual registration success and failed auto-login after committed onboarding. Existing Google integration fixtures use real commits and per-case client limits. PHPStan, Pint and 62 Architecture cases pass; containing verification pending. The third slice binds AccountPasskeyLoginController around maintained options/request/assertion/login policy, then uses CompleteAccountLogin with the verified credential model. Its response only presents redirect, and the intermediate passkey-reference session field is removed. User-verifying passkey audit retains its MFA method. User normalizes the maintained guard password contract to a string for password-less remembered-cookie hashing; stored-password eligibility remains explicit. Ten actual signed HTTP cases cover HTML/JSON and passkey-only/MFA success, three failed completion paths, challenge/ceremony feedback and retry, real competing deletion during rotation and rejected registration origin. The old response-only pseudo-login test is removed. PHPStan/Pint and 62 Architecture cases pass locally (67,571 assertions); PostgreSQL verification pending. Recaller and logout integration remain. On a932f7d5, CI and Architecture stop at test discovery because the new private options helper collides with the inherited HTTP OPTIONS method. It is renamed verificationOptions; full-suite local discovery validates test inheritance before the next PostgreSQL run. The fourth slice adds RestoreAccountSession and places TrackAccountSession immediately after StartSession in the actual sorted middleware pipeline. Maintained recaller validation/raw rotation precedes a current-account lock that compares authoritative credential/remember/MFA fingerprints and atomically records the session and remember-provider audit. Authentication events defer until success; failed admission clears prepared browser state without retrying raw destruction; remembered browsers clear recent proof and stale MFA challenge state. Fifteen real HTTP cases cover password/provider-only/MFA recovery, failure/retry, four competing transitions, revocation waiting behind completion, invalid cookies and enclosing-transaction rejection. Production PHPStan and changed-file Pint pass locally; actual booted middleware priority is verified. The workspace transport then disconnected, preventing collection of the launched discovery/Architecture result. The exact authored files are preserved through GitHub with source readback; full containing gates remain required. f3c25909 completes 999 tests / 76,449 assertions, with nine failures confined to passkey HTTP payload construction. Shared fixtures now expose the original browser arrays before maintained deserialization, preserving real signed cryptographic validation and the HTTP credential contract.
- Completion evidence: LogoutAccount replaces controller-side logout and records current-session revocation/audit under the account lock. Its initial remember-preserving policy and cleanup coverage are superseded by HARD-044/ADR-0021: logout also rotates remembered authority and defers success events through fail-safe browser cleanup. Containing verification remains pending.
- Commit SHA: password/MFA slice `b3c58f3cc253272f99aac51c2243a9ddbf468104`; Google/registration slice `c0c65ba998557d79d2d3c27336ee49749befd860`; remembered-session slice `c19fc047f9eaf3673f5f139812410abada64aeb7`; logout slice pending; containing gates running.

### HARD-038 — Recent-authentication proof can survive a failed or stale confirmation

- Area: Accounts password/Google/passkey recent-authentication confirmation.
- Finding: ConfirmPasswordController validates a cached User password and marks recent proof before separately writing audit; a late audit failure leaves new proof in session. Google reauthentication similarly separates current identity use, proof marking and authentication audit. PasskeyVerified marks proof during its package transaction before a later failure can roll back that transaction.
- Current owner: confirmation controllers, Google Workflow and passkey event listener.
- Intended authoritative owner: Accounts validates current active credential under its account lock, records audit atomically and publishes recent session proof only after outermost commit.
- Rationale: request-start credential snapshots and rolled-back audit cannot authorize a fresh confirmation window; existing prior proof should retain its established semantics.
- Remediation: add explicit current-credential confirmation owners, move session proof changes to outer-commit callbacks and preserve maintained password/provider/WebAuthn validation and existing redirects.
- State: Complete.
- Verification required: real late audit failure/outer rollback cannot create recent proof; removed/replaced/finalized credentials cannot confirm from stale requests; successful confirmation preserves intended expiry/method/credential reference and account binding.
- Earlier verification: First slice moves password confirmation to ConfirmAccountPassword with current-active/password validation and atomic audit under the account lock. Proof publication waits for outer commit and rechecks request account/current credential; audit/outer rollback preserve prior proof. Eight database cases cover late actual audit INSERT failure, outer rollback, successful callback timing, three stale credential/lifecycle states, changed request binding and a later credential transition in the same transaction. Full PHPStan, Pint and documentation links pass; PostgreSQL verification pending. The password slice and corrected reset import are pushed in c043b81e; success/stale/binding cases pass. Two prior-proof assertions incorrectly used Store.only with dotted keys and now use Store.get per key; parallel CI also hit HARD-039 before two unrelated fixtures ran. Second slice adds ConfirmGoogleAccount: current account/identity locking, exact subject binding, atomic provider-use metadata and authentication audit, and current-identity proof publication after outer commit. Eight cases cover real late audit failure, outer rollback, successful timing, four rejection paths and later identity removal. PHPStan and Pint pass; containing verification pending. The third slice adds VerifyAccountPasskey around the maintained verifier with current-active account-first locking. PasskeyVerified now commits audit before publishing proof/reference after outer commit with current account/credential/request binding; non-session calls avoid session access. The later HARD-037 passkey integration removes the transient guest reference and hands the verified model directly to completion; guest verification alone grants no recent proof. Fifteen database cases use real ephemeral-key signed assertions for counter/audit rollback, timing, six maintained rejections, four request/credential binding states, routing-to-finalization and a real competing passkey deletion. A database-free local round trip through the maintained validators accepts registration/assertion and rejects six invalid assertions; full PHPStan and Pint pass. PostgreSQL CI 34282214560 / PHP 102249411688 completes 964 tests / 75,893 assertions with three fixture failures: two initial raw snapshots omit database defaults/order and one explicit authenticated resolver is overwritten by Laravel request rebinding. Fixtures now refresh before snapshot and set the resolver after application binding. All maintained rejection/current-lifecycle/concurrency cases pass; containing verification remains pending. HARD-037 owns the broader login completion boundary.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: first slice `c043b81e4b10e1594aea0c85ae570a6b4e5342e0`; Google slice `f60af6b720c3f3888a2ddfe174338697fc0c32ae`; passkey slice `ede6508011fe53cf768a69207661c03884b430e0`; containing gates running.

### HARD-039 — Parallel schema rebuilds exceed CI PostgreSQL lock capacity

- Area: CI PostgreSQL service sizing for the full parallel PHP gate.
- Finding: On c043b81e, concurrent migrate:fresh operations fail with PostgreSQL SQLSTATE 53200 and the explicit max_locks_per_transaction hint. Each process owns an isolated database, but all table/index/sequence locks share the same server lock table.
- Current owner: .github/workflows/ci.yml PostgreSQL service.
- Intended authoritative owner: the existing CI service with explicit capacity for its parallel fresh-schema workload.
- Rationale: service resource exhaustion must not be confused with domain failures or worked around by skipping the migration/parallel checks. PostgreSQL documents this as a server-start setting; see [lock management](https://www.postgresql.org/docs/18/runtime-config-locks.html).
- Remediation: set max_locks_per_transaction to 256 on the ephemeral CI service, restart it before migrations, wait for readiness and verify the effective value. Preserve all existing gates and parallel execution.
- State: Complete.
- Verification required: the full parallel PHP gate rebuilds all isolated schemas without lock exhaustion; fresh-schema and container recovery checks remain green.
- Verification result: All required owner/HTTP/concurrency/browser cases pass in containing checkpoint 29eb4b1b. Full parallel PHP, Architecture and Intelligence suites each pass 1,100 tests / 78,155 assertions; full Pint/PHPStan, fresh schema, frontend, all 50 visual cases, container/staging/backup-and-restore and security gates pass.
- Completion evidence: All nine workflows pass together on `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`. This closes this finding; repository audit coverage and later findings remain in progress.
- Commit SHA: `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`.

### HARD-040 — Newly disclosed js-yaml denial-of-service advisory blocks dependency review

- Area: locked frontend development dependency graph.
- Finding: Dependency Review on c0c65ba9 reports GHSA-2883-xcg3-v3hh (high): js-yaml 4.0.0–4.3.1 does not bound CPU use for empty YAML merge sources. The locked ESLint/eslintrc chain resolves js-yaml 4.3.1.
- Current owner: package-lock.json and maintained npm resolution.
- Intended authoritative owner: the existing lockfile with the compatible maintained patch release.
- Rationale: retain the security gate and reproducible dependency graph when a new advisory appears during execution.
- Remediation: update only the js-yaml entry to 4.3.2 through npm resolution, preserve unrelated platform constraints, reinstall the exact lockfile and run audit/frontend gates.
- State: Complete.
- Verification required: locked audit has no high/critical finding; frontend checks/build and containing dependency/security gates pass.
- Earlier verification: Dependency Review 34284534326 / job 102256916304 identifies the precise advisory/range; npm resolves compatible 4.3.2; only its version/resolution/integrity entry changes, preserving unrelated platform constraints. npm ci, npm audit (zero vulnerabilities) and the full npm run check pass locally, including lint, formatting, types, tests, build/localization and performance budgets. Containing verification pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: pending.

### HARD-041 — Maintained WebAuthn ceremony rejections surface as server errors

- Area: account passkey registration and assertion HTTP error contract.
- Finding: Maintained WebAuthn validators throw AuthenticatorResponseVerificationException or CounterException for rejected ceremonies. Current Accounts adapters propagate them through thin package HTTP controllers, and no exception renderer maps them to validation feedback. Invalid challenges/origins/signatures/counters can therefore return HTTP 500.
- Current owner: account adapters around maintained StorePasskey and VerifyPasskey.
- Intended authoritative owner: the same adapters translate known ceremony rejections to the maintained InvalidPasskeyException credential-validation contract; cryptographic validation stays maintained.
- Rationale: invalid browser ceremonies must be actionable validation failures while operational/database failures remain visible and atomic.
- Remediation: narrowly translate known verifier rejections and exercise actual HTTP validation/retry with real signed assertions.
- State: Complete.
- Verification required: invalid assertions and registration ceremonies return bounded credential errors, mutate no successful proof/counter/audit state, and valid fresh ceremonies still succeed; operational exceptions retain rollback/failure behavior.
- Earlier verification: StoreAccountPasskey and VerifyAccountPasskey override only the maintained validation seam, translating AuthenticatorResponseVerificationException and assertion CounterException to InvalidPasskeyException. Operational/audit errors propagate normally. Six assertion and three registration invalid-ceremony owner cases retain unchanged-state assertions; real HTTP wrong-challenge/missing-ceremony retry and invalid-registration-origin cases exercise credential feedback. Full PHPStan/Pint and architecture pass; containing PostgreSQL verification pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: pending.

### HARD-042 — MFA changes leave previously remembered browsers valid

- Area: Accounts MFA enrollment and recovery transitions.
- Finding: TwoFactorManager.confirm, disable and regenerateRecoveryCodes commit credential/audit/security intent but leave remember_token unchanged. A remembered cookie issued before MFA was enabled can still restore after that transition; HARD-037's in-flight fingerprint check only catches changes during restoration, not an older cookie presented after the change.
- Current owner: TwoFactorManager and Accounts session revocation owners.
- Intended authoritative owner: MFA transitions invalidate prior remembered-browser authority within their current account transaction and retain explicit current-browser/session policy.
- Rationale: adding or replacing account assurance must not leave pre-transition remembered proof valid without a current credential ceremony.
- Remediation: trace the product/session policy, rotate existing remember authority at confirmed MFA transitions through the current owner, and verify real old-cookie rejection plus current-browser behavior and rollback. Do not add a parallel authentication version.
- State: Complete.
- Verification required: cookies issued before enable/disable/recovery regeneration cannot restore after the transition; failed MFA persistence preserves prior credentials/token/session policy; current browser behavior is deliberate and tested.
- Earlier verification: TwoFactorManager now rotates remember_token in the same account-locked transaction as MFA confirmation, disabling and recovery-code regeneration. The current authenticated session and its durable marker are deliberately preserved. Three real old-cookie HTTP cases cover rejection after each transition while the current browser remains active; CredentialSecurityIntentAtomicityV3Test snapshots the raw account row and therefore verifies token rollback with every injected notification-intent failure. Local diff/whitespace inspection passes; PHP runtime and containing PostgreSQL CI remain pending.
- Completion evidence: MfaRememberedBrowserInvalidationV3Test and updated MFA/session contracts; containing verification pending.
- Commit SHA: pending.

### HARD-043 — Session admission exposes a mutable model through its Action contract

- Area: Accounts session admission and request middleware.
- Finding: RestoreAccountSession.handle returns User to TrackAccountSession even though the caller only compares account identity. Architecture V3 correctly rejects this public write contract; c19fc047 otherwise passes its 1,014-test suite.
- Current owner: RestoreAccountSession and TrackAccountSession.
- Intended authoritative owner: same owner, returning nullable integer account identity; maintained guard retains the authenticated model internally.
- Rationale: expose the minimal stable scalar contract and preserve existing architecture enforcement rather than exempting a new violation.
- Remediation: return the admitted account ID and update the sole caller; cover both guest null and authenticated integer outcomes while keeping all remembered-login tests.
- State: Complete.
- Verification required: scalar contract behavior, remembered-session HTTP/failure/concurrency tests, Architecture V3, PHPStan and Pint.
- Verification result: Scalar and remembered-session cases pass; full PHPStan/Pint and Architecture rules/routes pass. Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Completion evidence: Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Commit SHA: verified containing candidate `69bf4440d829952d04fc8d5781e0d51f9a06219b`.

### HARD-044 — Logout preserves replayable remembered credentials and can interrupt cleanup

- Area: Accounts sign-out, remember cookies, event dispatch and external session storage.
- Finding: HARD-037's first logout slice preserves remember authority, allowing a copied cookie to restore access after sign-out. CurrentDeviceLogout listener failure can prevent guard clearing/session invalidation; raw destroy failure can prevent ID rotation. Its two initial tests omit the current session cookie between HTTP requests.
- Current owner: LogoutAccount and the maintained SessionGuard/Store integrations.
- Intended authoritative owner: Accounts revokes current-session and remembered authority atomically; maintained browser cleanup runs outside locks and always clears transient state.
- Rationale: sign-out must invalidate replayable credentials, and a failed cleanup consumer must not leave authenticated browser state. One account-scoped remember token cannot selectively preserve other remembered cookies safely.
- Remediation: rotate existing token with revocation/audit; defer CurrentDeviceLogout through cleanup, suppress success on persistence failure, always forget/flush/regenerate, and report bounded raw cleanup failures; preserve other active sessions. ADR-0021 supersedes the prior remember-preserving policy.
- State: Complete.
- Verification required: current/other session policy, password/provider cookie replay, audit/token rollback with no success event, false/throwing raw storage with stale replay, throwing listener, enclosing transaction rejection, PHPStan/Pint/Architecture and containing suite.
- Verification result: All required owner/HTTP/concurrency/browser cases pass in containing checkpoint 29eb4b1b. Full parallel PHP, Architecture and Intelligence suites each pass 1,100 tests / 78,155 assertions; full Pint/PHPStan, fresh schema, frontend, all 50 visual cases, container/staging/backup-and-restore and security gates pass.
- Completion evidence: All nine workflows pass together on `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`. This closes this finding; repository audit coverage and later findings remain in progress.
- Commit SHA: `7ae4489c49d99871d8c418829480c571f7367b77`.

### HARD-045 — Direct role assignment bypasses bounded permission delegation

- Area: Alliance Access single/bulk role assignments and Membership rank delegation.
- Finding: AssignMembershipRole checks the actor's held permissions only for self-assignment. A role administrator can therefore assign another member permissions the actor does not possess, contradicting the product contract and bulk preview. UpdateAllianceRank likewise does not bound the target rank's permissions. Bulk preview also applies assignment-only delegation checks to removal.
- Current owner: Alliance Access role Actions/authorization and Membership rank Actions.
- Intended authoritative owner: the existing authoritative mutation boundaries, with consistent current permission delegation and separate revocation semantics.
- Rationale: direct HTTP requests and each item after a stale bulk preview must enforce the same bounded delegation rule; restricting only self-assignment permits indirect escalation.
- Remediation: enforce delegation for every grant, recheck each bulk item, preserve R5/self-rank protections, and align preview/remove behavior without making previews authorization authorities.
- State: Complete.
- Verification required: direct and HTTP cross-member rejection, permitted subsets, self/cross-Alliance cases, stale bulk authority, rank grants and removal parity with no audit/outbox on rejected writes.
- Earlier verification: shared AllianceRoleDelegation enforces custom permission ceilings for every recipient, with explicit system-role semantics tracked separately under HARD-047. Membership owns a rank ceiling shared by preview and locked commits; removing a role no longer requires removed permissions. Sixteen database cases pass in 2ad8dfaf's completed Intelligence suite (1,043 tests / 77,008 assertions; job 102288101285). Its sole failure was the HTTP fixture lacking the required context-version header; 507701a2 supplies owner-issued current context and recent authentication while retaining middleware. ADR-0022 records the policy. Corrected HTTP and final containing verification remain pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: `2ad8dfaf6e797ab51a2f76e7e19f8384742f9520`.

### HARD-046 — Role-definition revocation does not serialize with protected Alliance writes

- Area: Alliance Access current-role authority and role definition lifecycle.
- Finding: ordinary writes hold a shared Alliance lock and the actor membership. UpdateAllianceRole and ArchiveAllianceRole also use a shared Alliance lock, then mutate role permissions/assignments without locking all affected memberships. A delegated writer can therefore validate role authority while a concurrent role revocation is uncommitted and finish after revocation using the obsolete permissions.
- Current owner: AllianceWriteState, UpdateAllianceRole and ArchiveAllianceRole.
- Intended authoritative owner: existing Alliance-scoped authority serialization; no competing permission cache or version.
- Rationale: role definition changes affect multiple holders and must coordinate with the existing current-write boundary; locking just the administrator's membership does not protect other holders.
- Remediation: choose and document compatible lock ordering for role definition mutations, then exercise both competing commit orders against real PostgreSQL connections.
- State: Complete.
- Verification required: revocation waits behind an admitted writer; a writer waiting behind revocation rechecks and rejects obsolete authority; different Alliances remain independent and rollback preserves permissions.
- Earlier verification: UpdateAllianceRole and ArchiveAllianceRole now acquire the existing exclusive Alliance scope before administrator membership and role state. Eight cases cover actual separate-connection contention in both orders, current rejection on retry, independence of another Alliance in the same Kingdom, and late audit rollback for both update and archive. ADR-0023 records lock order and alternatives. All role-permission/assignment mutation callers were traced; authorization remains lock-free. Source/whitespace and documentation checks pass; PostgreSQL/static/style containing verification remains pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: `507701a2bde99abfd6ab6cd7b1f209741ddeaad5`.

### HARD-047 — System-role grant authority is incomplete and conflicts with Gift Code commissioning

- Area: Alliance system roles, Operations role interpretation and Gift Code coverage.
- Finding: Event Coordinator has no Alliance permissions but carries Operations authority, so the old assignment loop allows an ordinary role administrator to grant that authority. Conversely, blindly enforcing held Alliance permissions breaks the first Gift Code Coordinator grant: R5 deliberately lacks coverage access while the product requires explicit delegation to another member.
- Current owner: Alliance role grants, default role provisioning, Operations permission interpretation and Gift Code coverage authorization.
- Intended authoritative owner: AllianceRoleDelegation defines explicit commissioning/grant authority; consumers continue to interpret their own capabilities from owner facts.
- Rationale: delegation authority and automatic data access must remain distinct where the existing product requires it; an empty local permission list cannot establish authority over another context.
- Remediation: allow R5 to commission provisioned system roles on other members without self/custom-role bypass; require R5 or current holding of Event Coordinator for its grants. Share policy between recipient-specific preview and locked owner actions, retaining Gift Code's explicit coverage gate and existing regression.
- State: Complete.
- Verification required: R5 commissioning gives access only to the selected member; self/ordinary unauthorized grants fail; held Event Coordinator plus role management can delegate; real Operations outcomes and current Gift Code behavior remain correct; preview query count is independent of selected recipient count.
- Earlier verification: code and four system-role cases plus one query-budget case published alongside HARD-045; ADR-0022 reconciles formerly contradictory contracts. All five pass in 2ad8dfaf's completed 1,043-test Intelligence suite (job 102288101285); its sole failure is HARD-045's unrelated HTTP fixture setup. Final containing verification remains pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: `2ad8dfaf6e797ab51a2f76e7e19f8384742f9520`.

### HARD-048 — Parallel PHP tests share rate-limit cache state across isolated databases

- Area: PostgreSQL/Redis-backed CI test isolation.
- Finding: CI 34292661039 / PHP job 102282433482 completes 1,026 tests with only AccountsSecurityCloseoutV3Test's signed email-verification request receiving 429. CI exports CACHE_STORE=redis, overriding PHPUnit's non-forced array default; parallel databases recreate the same numeric account identities while cache.prefix stays common. Distinct tests can therefore consume each other's authenticated throttle budgets.
- Current owner: tests/v3/TestCase and CI cache configuration.
- Intended authoritative owner: test-only cache namespacing established before application/provider boot, preserving the real Redis-backed gate and production rate limits.
- Rationale: database isolation without cache isolation makes valid tests order/process dependent. Disabling throttles, raising limits or replacing the Redis-backed gate is not a fix.
- Remediation: establish per-test cache namespaces that survive application recreation within that test; verify the failing scenario and full parallel suite without weakening gates.
- State: Complete.
- Verification required: separate tests/processes cannot share limiter or cached authority state; one test retains state across its intended application reboots; full parallel PHP and container/recovery checks pass.
- Verification result: All required owner/HTTP/concurrency/browser cases pass in containing checkpoint 29eb4b1b. Full parallel PHP, Architecture and Intelligence suites each pass 1,100 tests / 78,155 assertions; full Pint/PHPStan, fresh schema, frontend, all 50 visual cases, container/staging/backup-and-restore and security gates pass.
- Completion evidence: All nine workflows pass together on `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`. This closes this finding; repository audit coverage and later findings remain in progress.
- Commit SHA: `30e7033bbee951c781f479331be811f46cb60a95`.

### HARD-049 — Unnamed route throttles unintentionally share counters across unrelated operations

- Area: HTTP/API route rate-limit ownership and operation boundaries.
- Finding: dozens of routes use numeric throttle middleware with no prefix. The exact locked Laravel ThrottleRequests implementation builds an authenticated key from account ID only (guest key: route domain plus IP); the limit value and route name are not part of the key. A request to a high-volume endpoint therefore consumes the same counter used by low-volume credential/verification endpoints, and conversely unrelated operations with different limits inherit each other's attempts.
- Current owner: route adapters across Accounts, Integrations, Gift Codes, API, Contributions and Platform.
- Intended authoritative owner: explicit operation or deliberate shared-budget definitions with stable account/client scope; maintained middleware continues enforcing counters.
- Rationale: limits must protect the intended workload without accidental cross-capability denial or silently losing deliberate aggregate budgets.
- Remediation: trace each unnamed route's intended budget and consumers; define explicit namespaces/shared limiter groups with unchanged safety limits, then exercise cross-operation isolation and deliberate aggregate throttling in real HTTP tests.
- State: Complete.
- Verification required: unrelated operations do not exhaust each other's limits; related/aggregate workloads retain deliberate shared budgets; account/client isolation, retry timing and route boot remain correct.
- Verification result: All eight route/HTTP budget cases pass, including the package upload signature check, exact 60/minute ceiling and independent workload/client counters. Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Completion evidence: Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Commit SHA: verified containing candidate `69bf4440d829952d04fc8d5781e0d51f9a06219b`.

### HARD-050 — Authenticated password-proof endpoints have no attempt limit

- Area: Accounts password confirmation and password change HTTP admission.
- Finding: POST /confirm-password and PUT /profile/password call current credential validation without a route or owner attempt limit. An authenticated browser can repeatedly guess the account's password across either endpoint, despite bounded login and password-method routes.
- Current owner: Authentication/Profile Actions and their web route adapters.
- Intended authoritative owner: explicit shared HTTP password-proof budget, with credential validation and transactional proof publication retained by the existing owners.
- Rationale: recent-authentication assurance must not expose an unlimited alternate password-verification surface; changing endpoint, client IP or browser session must not multiply the same account's proof budget.
- Remediation: apply six requests per minute per account to both endpoints under one explicit prefix, distinct from unrelated verification/reset/game workloads. Preserve the maintained 429/Retry-After response and all credential/authority checks.
- State: Complete.
- Verification required: mixed attempts across endpoints/IPs/sessions exhaust one account budget; blocked correct credentials change no password/proof/audit; another account retains admission; expiry permits valid proof again.
- Earlier verification: two full HTTP cases exercise the configured store and deterministic clock expiry. ADR-0024 and Authentication/security contracts record the policy. Source/whitespace and documentation checks pass; executable verification remains pending.
- Verification result: All item-specific regression cases pass in 533275da's completed PostgreSQL/Redis parallel suite (1,063 tests / 77,589 assertions; job 102296839479). Full Pint, PHPStan and fresh installation pass; frontend, dependency review, CodeQL, Gift Code, King Perks, KingdomMaps and visual workflows pass. The suite's sole remaining failure is HARD-049's package-registered unnamed upload budget, not a failure of this item's behavior.
- Completion evidence: Existing item-specific owner/HTTP/concurrency cases in the completed containing run. Program-wide final gates and remaining audit coverage are tracked separately.
- Commit SHA: pending.

### HARD-051 — Role input shape and generated-key bounds can produce server errors

- Area: Alliance specialist-role HTTP and owner input boundaries.
- Finding: The permissions field is optional in validation but unconditionally indexed by create/update controllers; omitted input therefore fails before owner validation. A valid 65–100-character ASCII role name also produces a slug longer than the roles.key 64-character column.
- Current owner: AllianceRoleController, CreateAllianceRole and UpdateAllianceRole.
- Intended authoritative owner: Explicit permission replacement at the HTTP boundary and current owner name/storage bounds.
- Rationale: Missing permission input must not implicitly clear authority or crash, and valid-looking names must not escape as database errors.
- Remediation: Require an explicit list of distinct known permissions, including a valid empty list; reject names/keys exceeding storage bounds before persistence.
- State: Complete.
- Verification required: Real HTTP create/update rejection leaves role/permission/audit/outbox state unchanged; explicit empty permissions work; direct owner calls enforce name/key bounds.
- Verification result: All eight input/storage-bound HTTP and owner cases pass without partial role/permission/audit/outbox writes. Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Completion evidence: Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Commit SHA: verified containing candidate `69bf4440d829952d04fc8d5781e0d51f9a06219b`.

### HARD-052 — Role catalogs and management counts grow without bounds

- Area: Alliance role management, dashboard and bulk role selection.
- Finding: AllianceRoleController loads every active and archived role, then performs one memberships count query per role. Dashboard and bulk role selectors also load unbounded catalogs; the dashboard includes archived roles that cannot be assigned.
- Current owner: Alliance Access role controller and AllianceDashboard/AllianceGovernance projections.
- Intended authoritative owner: Owner-scoped bounded role catalog queries with usable pagination/search and grouped counts.
- Rationale: A local administrator can accumulate role/history rows without a storage cap; every dashboard or management read must not grow with all history or issue a query per role.
- Remediation: Introduce bounded scoped role reads and appropriate indexes, migrate all catalog consumers and maintain accessible selection/navigation.
- State: Complete.
- Verification required: Large same-/cross-Alliance role sets, stable page traversal, invalid/cross-scope cursors, archived-role selection exclusion, constant query count and real frontend navigation.
- Verification result: All required owner/HTTP/concurrency/browser cases pass in containing checkpoint 29eb4b1b. Full parallel PHP, Architecture and Intelligence suites each pass 1,100 tests / 78,155 assertions; full Pint/PHPStan, fresh schema, frontend, all 50 visual cases, container/staging/backup-and-restore and security gates pass.
- Completion evidence: All nine workflows pass together on `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`. This closes this finding; repository audit coverage and later findings remain in progress.
- Commit SHA: `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`.

### HARD-053 — Role editor state does not follow retained Inertia props

- Area: Alliance/Roles/Index.vue create/edit feedback.
- Finding: Drafts are created once from initial roles. Inertia retains component state after create/update, so a newly returned role has no draft although the template dereferences drafts[role.id].name. Existing updates use router.patch without showing row validation errors.
- Current owner: Alliance Roles frontend page.
- Intended authoritative owner: Role editor form state keyed to current server roles with visible per-row validation feedback.
- Rationale: Successful creation must remain usable without a hard reload; rejected owner edits need actionable feedback while preserving the user's draft.
- Remediation: Initialize/refresh editor state as roles change, preserve in-progress drafts safely, and expose save errors/processing through maintained form behavior.
- State: Complete.
- Verification required: Create then edit newly returned role in one retained page, server validation feedback, successful refresh, archive and pagination transitions; frontend type/style and browser behavior.
- Verification result: All 50 desktop/mobile visual cases pass, including creation, immediate retained-page editing, rejected empty name with preserved draft, rename, archive and paged selection. Visual workflow 34300410434 and frontend job 102305914739.
- Completion evidence: Visual workflow 34300410434 and frontend job 102305914739.
- Commit SHA: verified containing candidate `69bf4440d829952d04fc8d5781e0d51f9a06219b`.

### HARD-054 — Concurrent creation of the same role key escapes as a database error

- Area: Alliance Access role-creation idempotency and collision handling.
- Finding: CreateAllianceRole locks an existence query for a not-yet-existing key while holding only a shared Alliance scope. Two role administrators can both observe absence; the unique constraint rejects the losing insert as an unhandled database exception.
- Current owner: CreateAllianceRole and the existing Alliance/key unique constraint.
- Intended authoritative owner: Owner-controlled collision handling using the maintained transaction/unique-key mechanism.
- Rationale: Same-key contention must produce one complete role and ordinary validation feedback without overwriting the winner or aborting unrelated caller work.
- Remediation: Retain the database uniqueness authority and translate only the expected creation collision at a recoverable transaction boundary.
- State: Complete.
- Verification required: Separate-connection competing creation, one role/permission/audit/outbox outcome, safe retry and unrelated database errors still propagated.
- Verification result: All four creation cases pass: real competing connections on same/different keys, preserved winner, usable caller transactions, safe retries, unrelated unique failures and late audit rollback. Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Completion evidence: Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Commit SHA: verified containing candidate `69bf4440d829952d04fc8d5781e0d51f9a06219b`.

### HARD-055 — Role archival copies every assignment into audit and outbox payloads

- Area: Alliance role archival memory and durable payload size.
- Finding: ArchiveAllianceRole plucks every assigned membership ID into memory and duplicates that unbounded list in audit/outbox metadata. Suspended historical memberships retain assignments and are not limited by active member capacity. No production consumer reads removed_membership_ids.
- Current owner: ArchiveAllianceRole and role/assignment persistence.
- Intended authoritative owner: Atomic owner revocation with bounded summary metadata and indexed set-based assignment removal.
- Rationale: A single archival should not materialize unbounded historical membership lists or create oversized delivery/audit payloads.
- Remediation: Preserve immediate atomic revocation while recording a bounded assignment count and using Alliance-scoped indexed SQL removal.
- State: Complete.
- Verification required: Large assignment sets, exact bounded count, tenant isolation, no per-assignment reads, idempotent archival and late audit rollback.
- Verification result: All four archival cases pass: zero/one/1,000 historical assignments, one scoped deletion, exact bounded metadata, no population reads, tenant isolation, idempotency and late rollback. Existing revocation concurrency cases also pass. Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Completion evidence: Full PHP CI job 102305914972: 1,087 tests / 78,054 assertions, with only the unrelated catalog session-switch fixture failure. Full PHPStan/Pint and fresh PostgreSQL pass.
- Commit SHA: verified containing candidate `69bf4440d829952d04fc8d5781e0d51f9a06219b`.

### HARD-056 — Serial verification job reaches its deadline before reporting completion

- Area: Intelligence backend verification runtime budget.
- Finding: Job 102290021321 starts at 00:28:17 UTC and finishes its 1,046-test output at 00:53:22, immediately followed by cancellation at the configured 25-minute deadline. Earlier 7ae4489c also cancelled at that boundary. Growing real PostgreSQL concurrency/commit coverage leaves insufficient setup/reporting headroom.
- Current owner: Intelligence verification workflow job configuration.
- Intended authoritative owner: the existing complete serial gate with an explicit bounded runtime allowance.
- Rationale: verification must complete and report its actual outcome; a deadline cancellation is not passing evidence and must not be hidden by reducing test scope.
- Remediation: allow 35 minutes for the serial Intelligence backend job; retain every command, test, failure condition and diagnostics step.
- State: Complete.
- Verification required: the containing serial job completes with test output and normal success/failure reporting; all required tests and static/style gates remain unchanged.
- Verification result: Serial backend job 102302658390 completes normally in approximately 27 minutes, reports 1,079 tests / 77,947 assertions and uploads diagnostics. Its single catalog fixture failure is reported normally, with no deadline cancellation. Complete serial scope and static/style commands are preserved. Intelligence workflow 34299315784, backend job 102302658390.
- Completion evidence: Intelligence workflow 34299315784, backend job 102302658390.
- Commit SHA: verified containing candidate `8814c59ac1d72f590a3ab48f4e2bec0d8b97f2b8`.

### HARD-057 — Specialist role permission labels do not resolve

- Area: Alliance role creation, editing and read-only permission presentation.
- Finding: Browser job 102302658631 cannot find the readable content-management checkbox in either desktop or mobile. The catalogue stores literal dotted permission keys while the production resolver splits paths into nested segments, so every role permission displays a raw translation key.
- Current owner: Alliance capability localization catalogue and the existing nested-path resolver.
- Intended authoritative owner: The existing catalogue/resolver contract, with correctly nested permission entries.
- Rationale: Administrators must understand the permission being granted; untranslated identifiers are not usable feedback.
- Remediation: Structure all eight labels as nested catalogue paths and assert their readable browser labels before the existing create/edit/archive scenario.
- State: Complete.
- Verification required: All permission labels resolve in real desktop/mobile role creation and the full retained-page edit/archive flow passes; frontend style/types/localization remain passing.
- Verification result: All eight readable permission labels and the create/edit/archive flow pass on desktop and mobile; all 50 visual cases and full frontend checks pass. Visual workflow 34300410434 and frontend job 102305914739.
- Completion evidence: Visual workflow 34300410434 and frontend job 102305914739.
- Commit SHA: verified containing candidate `69bf4440d829952d04fc8d5781e0d51f9a06219b`.

### HARD-058 — Role-removal retries create false events and timestamp collisions

- Area: Alliance Access assignment removal and governance history.
- Finding: RemoveMembershipRole records audit/outbox removal even when no assignment exists. Every retry produces false governance history; the microsecond timestamp delivery key also collides if distinct assignment/removal cycles share a clock instant.
- Current owner: RemoveMembershipRole, membership_roles and existing audit/outbox records.
- Intended authoritative owner: The same locked owner mutation, deriving event creation from an actual affected assignment.
- Rationale: Retrying a completed mutation must not manufacture a new business fact; a genuinely later transition must remain independently deliverable.
- Remediation: Return after a zero-row detach only after current authority/tenant checks; use a ULID delivery key for each actual removal.
- State: Complete.
- Verification required: Absent/repeated removal, distinct same-time transitions, current authority on retries and late outbox rollback followed by successful retry.
- Verification result: All required owner/HTTP/concurrency/browser cases pass in containing checkpoint 29eb4b1b. Full parallel PHP, Architecture and Intelligence suites each pass 1,100 tests / 78,155 assertions; full Pint/PHPStan, fresh schema, frontend, all 50 visual cases, container/staging/backup-and-restore and security gates pass.
- Completion evidence: All nine workflows pass together on `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`. This closes this finding; repository audit coverage and later findings remain in progress.
- Commit SHA: `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`.

### HARD-059 — Alliance creation and settings use inconsistent input invariants

- Area: Alliance Lifecycle HTTP and direct owner settings input.
- Finding: Creation accepts reserved URL names and arbitrary language values that settings updates reject. CreateAlliance has no owner name/slug/timezone bounds; UpdateAllianceSettings does not enforce the 120-character columns after slug normalization, which can expand valid-looking Unicode input.
- Current owner: Two controller/Action rule sets under Lifecycle.
- Intended authoritative owner: One immutable AllianceSettingsInput contract shared by both owner Actions.
- Rationale: Every creation/update path must produce usable settings within schema bounds and the same closed language vocabulary.
- Remediation: Share normalization, reserved names, storage limits, supported locales and IANA timezone validation; retain controller input-shape feedback and no-op update behavior.
- State: Complete.
- Verification required: Both direct owners reject malformed/expanded values without partial writes, real HTTP creation rejects reserved URLs/unsupported languages, valid storage boundaries persist and same-value updates remain no-ops.
- Verification result: All required owner/HTTP/concurrency/browser cases pass in containing checkpoint 29eb4b1b. Full parallel PHP, Architecture and Intelligence suites each pass 1,100 tests / 78,155 assertions; full Pint/PHPStan, fresh schema, frontend, all 50 visual cases, container/staging/backup-and-restore and security gates pass.
- Completion evidence: All nine workflows pass together on `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`. This closes this finding; repository audit coverage and later findings remain in progress.
- Commit SHA: `29eb4b1b3caa7130e6ca373c5b03c41d50ef19cf`.

### HARD-060 — Concurrent Alliance URL claims escape as database errors

- Area: Alliance creation/settings uniqueness under concurrent different administrators.
- Finding: Creation relies on the controller's earlier unique check; settings locks an absent target slug while holding only its own Alliance. Competing owners can both observe the same available global slug before the unique constraint rejects one insert/update.
- Current owner: Lifecycle mutations and alliances.slug unique constraint.
- Intended authoritative owner: Database uniqueness with recoverable, narrowly classified owner validation.
- Rationale: A competing URL claim must preserve the winner and give ordinary field feedback without poisoning an enclosing transaction or hiding unrelated database faults.
- Remediation: Use savepoint-backed exact slug collision recovery for creation/update and preserve current authorization/atomic events.
- State: Complete.
- Verification required: Real separate-connection create/create, create/update and update/update competition; one winner, usable caller transactions, independent slugs, retry safety and unrelated failures.
- Verification result: All required owner, concurrency, rollback and existing behavior cases pass in containing checkpoint e306e4b7. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,170 tests / 79,907 assertions. Full Pint (1,919 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine e306e4b7 workflows succeed. PHP job 102325783947, Architecture job 102325783511 and Intelligence job 102325783461 establish complete containing execution.
- Commit SHA: `e306e4b7ed0ac1dc27c26891567ea2205261cd84`.

### HARD-061 — Alliance creation uses unlocked Player ownership and Kingdom facts

- Area: Alliance creation versus Player release/reconciliation/account finalization and competing creation.
- Finding: CreateAlliance reads a claimed Player snapshot without locking its current account/Player/Kingdom. ReleasePlayerAccount and account finalization can clear ownership after that read, while creation still establishes active R5 membership. Same-Player competing creations also both pass an absent active-membership query before a unique violation.
- Current owner: Alliance creation consuming GameWorld and Accounts owner references.
- Intended authoritative owner: Creation validates current active account, canonical Player ownership and active Kingdom under a consistent owner lock order before establishing membership.
- Rationale: Creating leadership must serialize with ownership revocation and must not create an unclaimed/terminal account's Alliance from stale facts.
- Remediation: Acquire current owner locks through reference APIs, recheck candidate identities, coordinate one-active-membership outcomes and preserve atomic bootstrap/audit/outbox.
- State: Complete.
- Verification required: Real competing ownership release/finalization/reconciliation, both commit orders, same-Player creation, inactive Kingdom, changed owner and late rollback; no new lock-order inversion.
- Verification result: All required owner, concurrency, rollback and existing behavior cases pass in containing checkpoint e306e4b7. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,170 tests / 79,907 assertions. Full Pint (1,919 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine e306e4b7 workflows succeed. PHP job 102325783947, Architecture job 102325783511 and Intelligence job 102325783461 establish complete containing execution.
- Commit SHA: `e306e4b7ed0ac1dc27c26891567ea2205261cd84`.

### HARD-062 — Alliance duplicates Platform provisioning and entitlement interpretation

- Area: Alliance creation, Membership/Content capacity and Platform AllianceAdministration.
- Finding: AllianceBootstrapProvisioner writes Platform-owned plan/settings tables with upserts that can reset existing configuration. MemberCapacityPolicy and StorageCapacityPolicy duplicate PlanEntitlementService's plan resolution and entitlement lookup instead of consuming an owner query.
- Current owner: Duplicated Alliance services/policies and Platform plan/settings models/service.
- Intended authoritative owner: Platform owns initial plan/settings persistence and entitlement lookup; Alliance owns its usage/capacity decisions through explicit owner contracts.
- Rationale: Plan/default changes must have one authority and initialization retries must preserve administrative changes.
- Remediation: Move initial persistence behind a Platform owner Action and centralize entitlement facts, migrate current consumers and remove superseded copies with architecture protection.
- State: Complete.
- Verification required: Atomic initial provisioning and failure rollback, duplicate initialization preserves settings, consistent missing/custom plan limits, current capacity behavior and dependency/ownership tests.
- Verification result: All required owner, concurrency, rollback and existing behavior cases pass in containing checkpoint e306e4b7. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,170 tests / 79,907 assertions. Full Pint (1,919 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine e306e4b7 workflows succeed. PHP job 102325783947, Architecture job 102325783511 and Intelligence job 102325783461 establish complete containing execution.
- Commit SHA: `e306e4b7ed0ac1dc27c26891567ea2205261cd84`.

### HARD-063 — Account membership cleanup reverses the Alliance lock order

- Area: Account deletion's RemovePlayersFromAlliances versus Alliance administrative writes.
- Finding: Cleanup locks memberships before shared Alliance rows. Ordinary and exclusive Alliance writers acquire Alliance before membership, allowing a two-transaction cycle between cleanup's membership lock and an administrator's exclusive Alliance lock.
- Current owner: Membership cleanup Action composed by account deletion.
- Intended authoritative owner: The same owner cleanup following the established Alliance-before-membership lock order.
- Rationale: Account cleanup and administrative writes must not deadlock because they acquire identical rows in opposite order.
- Remediation: Discover candidate scopes, acquire ordered Alliance locks before current membership locks, revalidate current rows and preserve atomic rank guards, detach and durable records.
- State: Complete.
- Verification required: Two-connection cleanup/administration contention, current R5 protection, scoped multi-Alliance cleanup, new/changed membership handling and late rollback.
- Verification result: All required owner, concurrency, rollback and existing behavior cases pass in containing checkpoint e306e4b7. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,170 tests / 79,907 assertions. Full Pint (1,919 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine e306e4b7 workflows succeed. PHP job 102325783947, Architecture job 102325783511 and Intelligence job 102325783461 establish complete containing execution.
- Commit SHA: `e306e4b7ed0ac1dc27c26891567ea2205261cd84`.

### HARD-064 — Owned identity edits and Kingdom movement use stale account authority

- Area: GameWorld MoveOwnedPlayerToKingdom, UpdateOwnedPlayerIdentity and PersistPlayerIdentity.
- Finding: Both account-facing wrappers read ownership/name/stable ID/Kingdom before PersistPlayerIdentity starts its Kingdom/Player transaction. Persistence checks current lifecycle constraints but does not revalidate the requesting account; concurrent release, reassignment or account deletion can leave an old owner able to mutate the Player and overwrite newer identity facts.
- Current owner: GameWorld movement wrapper and identity persistence.
- Intended authoritative owner: Current authenticated movement under the account, Kingdom and Player owner barriers, reusing authoritative identity persistence.
- Rationale: An account-bound command must use current ownership and identity values at commit time.
- Remediation: Coordinate account authority and current locked Player facts with identity persistence without duplicating transition/history logic or reversing Kingdom/Player ordering.
- State: Complete.
- Verification required: Both commit orders with release/finalization/reassignment, concurrent identity edits, ordinary authorized movement and complete history/audit rollback.
- Verification result: All required owner, concurrency, rollback and existing behavior cases pass in containing checkpoint e306e4b7. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,170 tests / 79,907 assertions. Full Pint (1,919 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine e306e4b7 workflows succeed. PHP job 102325783947, Architecture job 102325783511 and Intelligence job 102325783461 establish complete containing execution.
- Commit SHA: `e306e4b7ed0ac1dc27c26891567ea2205261cd84`.

### HARD-065 — Kingdom archival materializes every active tracked Alliance

- Area: GameWorld Kingdom archival cascade.
- Finding: ArchiveKingdom loads every active KingdomAlliance into memory under row locks and performs one update and audit insert per row. Tracked external Alliances have no enforced upper bound.
- Current owner: ArchiveKingdom and KingdomAlliance lifecycle records.
- Intended authoritative owner: The same Kingdom transaction with bounded traversal and preserved per-Alliance audit semantics.
- Rationale: A growing tracked Kingdom must not require an unbounded application collection or nondeterministic row-lock order to archive.
- Remediation: Traverse a stable bounded cursor under the Kingdom lifecycle barrier, preserve atomic child/archive records and maintain an exact affected count.
- State: Complete.
- Verification required: More than one batch, stable child ordering, exact audit/count behavior, repeat archival, competing child changes and late full rollback.
- Verification result: All required owner, concurrency, rollback and existing behavior cases pass in containing checkpoint e306e4b7. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,170 tests / 79,907 assertions. Full Pint (1,919 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine e306e4b7 workflows succeed. PHP job 102325783947, Architecture job 102325783511 and Intelligence job 102325783461 establish complete containing execution.
- Commit SHA: `e306e4b7ed0ac1dc27c26891567ea2205261cd84`.

### HARD-066 — Membership activation uses unlocked Player facts and absent exclusivity locks

- Area: UpdateMembershipStatus activation versus identity changes and other Alliance activation.
- Finding: Activation reads Player Kingdom without a current Player/ownership barrier, then locks any other active membership. Two independent Alliance activations can both find no active row and expose a unique-constraint error; release or Kingdom movement can also change the checked Player facts before activation commits.
- Current owner: Membership activation Action and active-membership database constraint.
- Intended authoritative owner: Current membership activation coordinated with identity lifecycle through explicit owner locks and recoverable database exclusivity.
- Rationale: Reactivation must preserve current Player placement/lifecycle and report a competing membership as ordinary owner validation.
- Remediation: Establish a consistent creation/acceptance/activation/identity lock protocol, recheck current Player facts and recover exact exclusivity conflicts without locking unrelated membership scopes in reverse order.
- State: Complete.
- Verification required: Both activation commit orders across Alliances, release/reconciliation/Kingdom movement, safe retries, caller transaction recovery, current authority and complete event rollback.
- Verification result: All new admission/roster and existing behavior cases pass in containing checkpoint 802f1a6b. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,205 tests / 80,125 assertions. Full Pint (1,923 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine 802f1a6b workflows succeed. PHP job 102331819570, Architecture job 102331819428 and Intelligence job 102331819460 establish complete containing execution; container job 102335421893 passes.
- Commit SHA: `802f1a6b91b597e9e34af4d77482ce75f5781ba1`.

### HARD-067 — Neutral Alliance mutations reverse Kingdom lifecycle lock ordering

- Area: GameWorld neutral Alliance identity update, restoration and reconciliation versus Kingdom archival/resolution.
- Finding: UpdateKingdomAllianceIdentity, RestoreKingdomAlliance and ReconcileKingdomAlliances lock child identities before their Kingdom. ArchiveKingdom and ResolveKingdomAlliance lock Kingdom first, allowing a child/Kingdom wait cycle. Child-only archival also omits the shared lifecycle serialization contract.
- Current owner: GameWorld Kingdoms lifecycle and identity Actions.
- Intended authoritative owner: Kingdom-first locking followed by ordered current child identities and revalidated discovered scope.
- Rationale: All writers touching the same parent/child aggregates must agree on lock order, including the bounded cascade under HARD-065.
- Remediation: Acquire the expected/discovered Kingdom before child rows, verify current scope/canonical state and preserve existing history/audit semantics.
- State: Complete.
- Verification required: Both commit orders against Kingdom archival for update/restore/reconciliation/child archival, current aliases and scope changes, independent Kingdom progress and complete rollback.
- Verification result: All required owner, concurrency, rollback and existing behavior cases pass in containing checkpoint e306e4b7. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,170 tests / 79,907 assertions. Full Pint (1,919 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine e306e4b7 workflows succeed. PHP job 102325783947, Architecture job 102325783511 and Intelligence job 102325783461 establish complete containing execution.
- Commit SHA: `e306e4b7ed0ac1dc27c26891567ea2205261cd84`.

### HARD-068 — Roster update reverses Player and roster row locking

- Area: UpsertRosterEntry existing-entry updates versus MarkRosterEntryLeft and invitation acceptance.
- Finding: Existing roster updates lock the roster entry before Player, while roster departure locks Player before the entry. Both permit shared Alliance scope and distinct administrator memberships, so the same Player/entry can form a two-transaction wait cycle. Acceptance also holds Player before reading its roster witness.
- Current owner: Alliance Membership roster mutations.
- Intended authoritative owner: Current scoped roster operations following Alliance, Player, roster ordering with target revalidation.
- Rationale: Shared scope permits concurrent administrators and must not conceal inverse row-lock dependencies.
- Remediation: Discover entry routing without locking, acquire current Player before the scoped entry lock, then recheck routing and all current constraints before writing.
- State: Complete.
- Verification required: Both update/departure commit orders with distinct administrators, changed routing, unrelated entries, current scope and complete event rollback.
- Verification result: All new admission/roster and existing behavior cases pass in containing checkpoint 802f1a6b. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,205 tests / 80,125 assertions. Full Pint (1,923 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine 802f1a6b workflows succeed. PHP job 102331819570, Architecture job 102331819428 and Intelligence job 102331819460 establish complete containing execution; container job 102335421893 passes.
- Commit SHA: `802f1a6b91b597e9e34af4d77482ce75f5781ba1`.

### HARD-069 — Invitation onboarding locks Player before its existing Alliance scope

- Area: AcceptInvitationForAccount composition versus roster writers and membership admission.
- Finding: The workflow claims/locks Player before AcceptInvitation acquires the existing Alliance and roster scope. Roster writers acquire Alliance first and then Player; revocation/exclusive Alliance writers can complete the reverse dependency. Adding current Player locking to activation under HARD-066 also requires consistent onboarding ordering.
- Current owner: AccountOnboarding workflow and Alliance Membership acceptance.
- Intended authoritative owner: Atomic onboarding composition with account, existing Alliance/Kingdom scope and current Player in a consistent order.
- Rationale: The authorized claim and membership must remain atomic while agreeing with Alliance owner writers on shared resource acquisition.
- Remediation: Acquire the discovered invitation's owner scope before claiming Player through explicit owner contracts, then revalidate the current invitation, account, Player, roster and membership facts.
- State: Complete.
- Verification required: Acceptance versus roster changes, activation, Alliance revocation and account deletion in both commit orders; changed/revoked invitations, one membership winner, safe caller transactions and full claim/registration rollback.
- Verification result: All new admission/roster and existing behavior cases pass in containing checkpoint 802f1a6b. Full parallel PHP and complete serial Architecture/Intelligence each pass 1,205 tests / 80,125 assertions. Full Pint (1,923 files), PHPStan, fresh PostgreSQL, frontend, all 50 visual cases, container/staging/backup-and-restore/image scanning and security/capability gates pass.
- Completion evidence: All nine 802f1a6b workflows succeed. PHP job 102331819570, Architecture job 102331819428 and Intelligence job 102331819460 establish complete containing execution; container job 102335421893 passes.
- Commit SHA: `802f1a6b91b597e9e34af4d77482ce75f5781ba1`.

### HARD-070 — Serial verification exceeds its measured job budget

- Area: Architecture and Intelligence complete PostgreSQL suites.
- Finding: On 65eed97b, Architecture passes 63 structural tests / 69,101 assertions, then is cancelled at its 30-minute job limit before the full serial suite completes. The same commit's Intelligence serial suite passes 1,140 tests / 79,669 assertions in 28:14, in addition to dependency setup and analysis. New concurrency coverage further increases measured work.
- Current owner: GitHub Actions serial verification jobs.
- Intended authoritative owner: Complete required suites with an explicit runtime budget supported by observed execution.
- Rationale: A budget that interrupts the required suite cannot establish containing verification.
- Remediation: Set both serial backend job budgets to 45 minutes; retain complete suites, static checks and failure semantics.
- State: Complete.
- Verification required: Both complete serial jobs finish within their declared limits with every required case executed.
- Verification result: Both complete serial suites pass 1,205 tests / 80,125 assertions within the retained 45-minute budgets. Architecture test runtime is 35:36 and Intelligence is 27:54; all static/setup gates also complete successfully. No cases or checks were removed.
- Completion evidence: All nine 802f1a6b workflows succeed. PHP job 102331819570, Architecture job 102331819428 and Intelligence job 102331819460 establish complete containing execution; container job 102335421893 passes.
- Commit SHA: `802f1a6b91b597e9e34af4d77482ce75f5781ba1`.

### HARD-071 — Invitation issuance locks another Alliance's membership

- Area: IssueAllianceInvitation, CreateInvitation, ResendInvitation and recruitment conversion.
- Finding: Issuance holds its Alliance and administrator membership, then locks a target's active membership across all Alliances before rejecting an already-active Player. Two Alliances inviting each other's active administrator can acquire inverse membership dependencies even though both requests should simply fail validation. Target identity is also read without a canonical/current Player barrier.
- Current owner: Alliance Membership invitation issuance service.
- Intended authoritative owner: Current invitation eligibility under its Alliance scope and owner identity contract without foreign membership locks.
- Rationale: An ineligible foreign member does not require locking foreign authority; current identity eligibility must agree with lifecycle writers.
- Remediation: Stabilize current target identity in the agreed scope order, use nonlocking foreign membership facts and audit bounded supersession/capacity behavior through all issuance callers.
- State: Complete.
- Verification required: Opposing Alliance invitations do not wait on foreign membership rows; stale canonical/placement/ownership facts, current capacity, supersession and late event rollback.
- Verification result: Initial issuance and resend share InvitationEligibility under their exclusive Alliance scope. It locks current canonical Player identity shared, validates roster/Kingdom and recipient ownership, and observes active membership without foreign locks. Recruitment conversion reuses the issuer. Thirteen PostgreSQL cases cover opposing Alliance attempts, both ownership commit orders, reconciled identities, live versus expired capacity reservations, supersession/token validity and late delivery rollback. Initial issuance supersedes matching pending records under its exclusive scope; expired status is only a read projection and production renewal preserves the existing pending record. On 2f7042d5 all 1,218 parallel PHP tests / 80,223 assertions pass, including all thirteen cases; full Pint/PHPStan/fresh schema pass. Both complete serial suites also pass 1,218 tests / 80,223 assertions (Architecture job 102336847964, Intelligence job 102336847760; runtimes 35:39 and 36:00). The parallel enclosing job times out as results finish, tracked under HARD-075, so the containing deployment gate remains pending. The abda37b1 containing parallel job passes all 1,267 tests / 80,498 assertions, full Pint/PHPStan and fresh schema; container/staging/recovery also passes (job 102352927089); both complete serial gates now pass with the same 1,267 tests / 80,498 assertions.
- Completion evidence: All nine workflows pass on abda37b1b3093c8cde37026eb19fccc8b9d097eb, including 1,267 tests / 80,498 assertions in parallel PHP and both complete serial suites, current static analysis, fresh schema, frontend, fifty visual cases, security/capabilities and production container/staging/recovery. The containing commit includes the recorded owner/concurrency/rollback regressions.
- Commit SHA: verified containing candidate `abda37b1b3093c8cde37026eb19fccc8b9d097eb`.

### HARD-072 — Outgoing transfer completion acquires destination Kingdom after Player

- Area: CompleteTransferParticipant, TransferWriteState and roster handoff owner contracts.
- Finding: Transfer authority locks Alliance, home Kingdom and actor Player; completion locks target Player before PersistPlayerIdentity locks the outgoing destination Kingdom. Ordinary identity writers lock their destination Kingdom before Player. A queued destination archival can complete a wait cycle between these writers. The transfer roster activation owner itself reads Player without stabilizing current identity, relying on its present caller's lock.
- Current owner: GameWorld transfer completion composed with Alliance membership/roster and GameWorld identity owners.
- Intended authoritative owner: Current transfer completion with all required Kingdom scope acquired before Player locks, revalidated participant routing and independently authoritative roster writes.
- Rationale: Atomic transfer handoff must agree with identity and Kingdom lifecycle writers while preserving current permissions, capacity and completion records.
- Remediation: Discover and revalidate outgoing routing, acquire current home/destination Kingdom scope in stable order before target Player locks without an unnecessary actor identity lock, and make the roster activation owner stabilize current identity. Trace every TransferWriteState consumer before changing its contract.
- State: Complete.
- Verification required: Incoming/outgoing/staying completion, destination archival and identity mutation in both commit orders, changed routing, independent scopes, retries and complete handoff/history/event rollback.
- Verification result: Completion now locks home/outgoing destination Kingdoms shared in stable ID order before canonical target Player, then revalidates current participant routing. TransferWriteState retains exclusive Alliance/current membership authority while removing the unnecessary actor Player lock; its consumers were traced for actor identity writes. Both roster handoff owners acquire current Player before roster. Fifteen PostgreSQL cases cover all directions and archival orders, independent Alliances, opposing officer targets, changed routing, archived-destination completed retry, direct roster admission versus movement and full late rollback. ADR-0033 accepted; all fifteen cases pass in the ee943b7f parallel suite. Its unrelated four account attribution test failures prevent containing verification. The abda37b1 containing parallel job passes all 1,267 tests / 80,498 assertions, full Pint/PHPStan and fresh schema; container/staging/recovery also passes (job 102352927089); both complete serial gates now pass with the same 1,267 tests / 80,498 assertions.
- Completion evidence: All nine workflows pass on abda37b1b3093c8cde37026eb19fccc8b9d097eb, including 1,267 tests / 80,498 assertions in parallel PHP and both complete serial suites, current static analysis, fresh schema, frontend, fifty visual cases, security/capabilities and production container/staging/recovery. The containing commit includes the recorded owner/concurrency/rollback regressions.
- Commit SHA: verified containing candidate `abda37b1b3093c8cde37026eb19fccc8b9d097eb`.

### HARD-073 — Withdraw unsupported account audit-reference lock change

- Area: AccountIdentityQuery lifecycle barriers, account deletion and audited Alliance operations.
- Finding: The proposed User foreign-key wait cycle was disproved. AllianceWriteState uses PlayerReference: auditUserId is null and auditPlayerId identifies the Player. The added tests incorrectly asserted account-based attribution and did not establish the claimed User lock dependency.
- Current owner: Accounts lifecycle barriers and Player-based Alliance audit attribution.
- Intended authoritative owner: Existing exclusive account lifecycle serialization and explicit domain actor references.
- Rationale: Preserve the actual owner contract and remove a lock change unsupported by the executed path.
- Remediation: Restore AccountIdentityQuery FOR UPDATE and the original existing test hooks; withdraw ADR-0032; correct the six new regression cases to assert Player attribution, actual cleanup ordering, rollback and account/ownership exclusivity.
- State: Complete.
- Verification required: Both cleanup/writer orders, complete rollback and terminal authority, exclusive account/ownership contenders and the containing suite under the restored account lock.
- Verification result: On ee943b7f, parallel PHP executed 1,239 tests / 80,325 assertions with four failures, all at the incorrect actor_user_id assertion in AccountAuditReferenceLockV3Test line 109. The two exclusivity cases and all fifteen HARD-072 cases passed. The four cleanup cases reached the intended interleaving, but assertions after the bad attribution assertion did not execute. The production change is reverted and all six corrected cases await execution; this item is not a confirmed production deadlock fix. The abda37b1 containing parallel job passes all 1,267 tests / 80,498 assertions, full Pint/PHPStan and fresh schema; container/staging/recovery also passes (job 102352927089); both complete serial gates now pass with the same 1,267 tests / 80,498 assertions.
- Completion evidence: All nine workflows pass on abda37b1b3093c8cde37026eb19fccc8b9d097eb, including 1,267 tests / 80,498 assertions in parallel PHP and both complete serial suites, current static analysis, fresh schema, frontend, fifty visual cases, security/capabilities and production container/staging/recovery. This verifies withdrawal of the unsupported lock change and the six corrected actual-contract regressions; it is not evidence of a User foreign-key deadlock.
- Commit SHA: verified containing candidate `abda37b1b3093c8cde37026eb19fccc8b9d097eb`.

### HARD-074 — Transfer planning identity resolution locks Player before source Kingdom

- Area: ResolveTransferPlayer, SaveTransferParticipant and current canonical identity routing.
- Finding: The planning adapter locks the current Player and any matching game ID before PersistPlayerIdentity acquires the source Kingdom. ResolveKingdom validates existence/status without retaining a lifecycle lock. The adapter can therefore reproduce the Kingdom/Player inversion against archival and ordinary identity writes; two changed-ID edits can also acquire current and matched Players in opposite order before rejecting identity replacement.
- Current owner: GameWorld KingdomTransfers planning identity adapter over the Players owner.
- Intended authoritative owner: Explicit planning identity validation under a source Kingdom barrier with deterministic current canonical Player routing and preserved owner history.
- Rationale: Planning observation must preserve identity and current lifecycle without acquiring locks in an order opposite the underlying owner.
- Remediation: Stabilize active source Kingdom before Player, avoid locking an unrelated conflicting identity merely to reject it, revalidate current canonical identity and preserve transactional planning/history/audit behavior.
- State: Complete.
- Verification required: Both source archival/identity commit orders, conflicting identity edits in both directions, reconciled aliases, current placement/roster/membership guards and complete late planning rollback.
- Verification result: ResolveTransferPlayer now owns a transaction, locks active source Kingdom before canonical Player, rejects stable-ID replacement without foreign identity locks and retains placement guards before delegating owner history. Eleven new PostgreSQL cases cover new/edit planning against both archival orders, opposing edits, movement orders, reconciled aliases and complete late planning rollback. ADR-0034 accepted; executable verification pending. The abda37b1 containing parallel job passes all 1,267 tests / 80,498 assertions, full Pint/PHPStan and fresh schema; container/staging/recovery also passes (job 102352927089); both complete serial gates now pass with the same 1,267 tests / 80,498 assertions.
- Completion evidence: All nine workflows pass on abda37b1b3093c8cde37026eb19fccc8b9d097eb, including 1,267 tests / 80,498 assertions in parallel PHP and both complete serial suites, current static analysis, fresh schema, frontend, fifty visual cases, security/capabilities and production container/staging/recovery. The containing commit includes the recorded owner/concurrency/rollback regressions.
- Commit SHA: verified containing candidate `abda37b1b3093c8cde37026eb19fccc8b9d097eb`.

### HARD-075 — Parallel PHP verification reaches the enclosing job limit

- Area: General CI backend job and dependent container/staging/recovery gate.
- Finding: On 2f7042d5, full Pint/PHPStan/fresh schema pass and ParaTest emits success for all 1,218 tests / 80,223 assertions after 17:52 of test execution. Including setup, the job reaches its 20-minute limit immediately after this result, is cancelled, and skips the required container job.
- Current owner: General CI PHP verification job.
- Intended authoritative owner: Complete unchanged PHP gate with a declared budget that includes measured setup and suite runtime.
- Rationale: Passing test output cannot replace a successful enclosing job and required downstream verification.
- Remediation: Increase this backend job budget to 30 minutes; retain all checks, cases, failure behavior and container dependencies.
- State: Complete.
- Verification required: Full PHP job completes successfully and its container/staging/recovery dependency executes and passes on a containing checkpoint.
- Verification result: Job 102336847932 records the complete passing test summary at 04:43:39 UTC and cancellation immediately afterward; its services started at 04:23. The earlier 802f1a6b suite took 16:35 before setup. The budget change is prepared; containing execution pending. The abda37b1 containing parallel job passes all 1,267 tests / 80,498 assertions, full Pint/PHPStan and fresh schema; container/staging/recovery also passes (job 102352927089); both complete serial gates now pass with the same 1,267 tests / 80,498 assertions.
- Completion evidence: All nine workflows pass on abda37b1b3093c8cde37026eb19fccc8b9d097eb, including 1,267 tests / 80,498 assertions in parallel PHP and both complete serial suites, current static analysis, fresh schema, frontend, fifty visual cases, security/capabilities and production container/staging/recovery. The containing commit includes the recorded owner/concurrency/rollback regressions.
- Commit SHA: verified containing candidate `abda37b1b3093c8cde37026eb19fccc8b9d097eb`.

### HARD-076 — Stable Player identity conflicts lock foreign rows and escape as database errors

- Area: PersistPlayerIdentity current stable-ID edits, attachment and identity creation.
- Finding: An expected-Player edit locks its current identity and then a different Player owning the submitted game ID before rejecting replacement. Opposing edits can wait on each other's identity. Concurrent absent stable-ID attachment/creation can also reach the unique index with no owner-level validation recovery.
- Current owner: GameWorld Players identity persistence and the existing stable-ID unique constraint.
- Intended authoritative owner: Current canonical identity mutation with nonlocking conflict witnesses and exact recoverable uniqueness validation.
- Rationale: Rejecting a foreign identity requires no mutation lock on it; absent identity uniqueness must remain database-enforced without poisoning caller transactions or committing partial history.
- Remediation: Validate immutable current stable IDs before foreign lookup, observe conflicts without foreign locks and translate only the actual stable-ID constraint after transaction rollback.
- State: Complete.
- Verification required: Opposing edits in both directions, simultaneous absent-ID attachment and creation in both orders, usable enclosing transactions, unrelated unique errors retained, safe retries and full history/audit rollback.
- Verification result: Persistence validates the locked stable ID before nonlocking foreign conflict lookup. Exact players_game_player_id_unique failures become game-ID validation only after owner transaction/savepoint rollback; unrelated integrity errors propagate. Seven new PostgreSQL cases exercise opposing edits, both absent creation/attachment winners, usable caller transactions, retry behavior and real unrelated primary-key failures. ADR-0034 accepted; executable verification pending. The abda37b1 containing parallel job passes all 1,267 tests / 80,498 assertions, full Pint/PHPStan and fresh schema; container/staging/recovery also passes (job 102352927089); both complete serial gates now pass with the same 1,267 tests / 80,498 assertions.
- Completion evidence: All nine workflows pass on abda37b1b3093c8cde37026eb19fccc8b9d097eb, including 1,267 tests / 80,498 assertions in parallel PHP and both complete serial suites, current static analysis, fresh schema, frontend, fifty visual cases, security/capabilities and production container/staging/recovery. The containing commit includes the recorded owner/concurrency/rollback regressions.
- Commit SHA: verified containing candidate `abda37b1b3093c8cde37026eb19fccc8b9d097eb`.

### HARD-077 — Account Player creation can claim an identity that appeared after its precheck

- Area: CreatePlayerForAccount composed with PersistPlayerIdentity and ClaimPlayerAccount.
- Finding: Account creation checks existing game identity before the persistence owner's Kingdom lock. An unclaimed identity can appear after the absence check; the generic persistence call then reuses it and the claim silently assigns it to the caller, contrary to this Action's explicit claim/recovery rule. The early existing Player lock also reverses Kingdom/Player ordering.
- Current owner: GameWorld Players account registration composition and identity persistence.
- Intended authoritative owner: Current existing-owner precondition enforced on the identity actually locked by the persistence owner, after current account and Kingdom scope.
- Rationale: A stale absence check cannot authorize adopting another durable identity; an ordinary repeated registration may reuse only an identity currently owned by the same account.
- Remediation: Remove the early duplicate lookup and carry an explicit expected existing account-owner precondition into the single authoritative persistence transaction before identity changes. Keep claim/history atomic and preserve trusted unclaimed persistence behavior.
- State: Complete.
- Verification required: Concurrent unclaimed/foreign-owned identity arrival, creation collisions across accounts, same-owner reuse, source archival orders, current account finalization and complete late rollback.
- Verification result: CreatePlayerForAccount removes its early duplicate lookup and passes the expected existing account owner to persistence. Persistence checks the actual locked identity before mutation, preserving current account/Kingdom/Player order and atomic claim/history/audit. Eight new PostgreSQL cases cover unclaimed/other-account arrival in both caller orders, current same-owner reuse versus both archival orders and new/existing late registration rollback. Existing terminal-account/ownership cases remain required. ADR-0034 accepted; executable verification pending. The abda37b1 containing parallel job passes all 1,267 tests / 80,498 assertions, full Pint/PHPStan and fresh schema; container/staging/recovery also passes (job 102352927089); both complete serial gates now pass with the same 1,267 tests / 80,498 assertions.
- Completion evidence: All nine workflows pass on abda37b1b3093c8cde37026eb19fccc8b9d097eb, including 1,267 tests / 80,498 assertions in parallel PHP and both complete serial suites, current static analysis, fresh schema, frontend, fifty visual cases, security/capabilities and production container/staging/recovery. The containing commit includes the recorded owner/concurrency/rollback regressions.
- Commit SHA: verified containing candidate `abda37b1b3093c8cde37026eb19fccc8b9d097eb`.

### HARD-078 — Membership and Recruitment bulk selections are bounded only by HTTP

- Area: Membership status and Recruitment stage bulk preview, execution and aggregate receipts.
- Finding: Both PreviewMembershipStatusBulkChange and PreviewRecruitmentStageBulkChange accept arbitrary or empty direct-owner selections and retain duplicates; their bulk execution actions record the original unnormalized IDs in audit. HTTP enforces 50 distinct selections but direct callers do not share that owner contract.
- Current owner: Membership status and Recruitment stage bulk owner actions.
- Intended authoritative owner: Bounded canonical selection shared by preview outcomes, execution and audit receipts.
- Rationale: The owner must enforce the existing 50-item product limit and avoid duplicate work or oversized aggregate metadata independently of one adapter.
- Remediation: Normalize concrete selections at preview, reject empty or more than 50 distinct IDs before scope queries, and derive execution/audit IDs from canonical preview results.
- State: Complete.
- Verification required: Direct preview/execution boundaries, duplicate normalization, 50-item acceptance, authority, real per-item transitions and canonical audit receipts.
- Verification result: Both bulk preview owners enforce the existing one-to-fifty distinct selection limit before scope queries. Execution/audit use canonical preview IDs. Twelve new direct-owner cases cover empty/oversized preview and execution, the exact limit, repeated selection transitions and one canonical receipt. Executable verification pending.
- Completion evidence: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

### HARD-079 — Public Recruitment submission uses stale account and Kingdom facts

- Area: SubmitRecruitmentApplication and its public HTTP adapter.
- Finding: The controller checks active Kingdom before the owner transaction, while the owner only locks Alliance/settings. Direct submission can therefore admit an archived Kingdom. Optional applicant identity is read without active-account serialization, so finalized identity or a changing account email can be accepted from stale facts.
- Current owner: Recruitment intake composed with Accounts and GameWorld owner references.
- Intended authoritative owner: Current optional active account followed by Alliance and active Kingdom scope, then Recruitment intake policy.
- Rationale: Account-backed applicant attribution and operating Kingdom must remain current through application creation, invitation consumption and delivery records.
- Remediation: Acquire current optional active account before Alliance scope, hold the active Kingdom barrier in the owner, and compare current account email before candidate creation. Preserve anonymous public intake.
- State: Complete.
- Verification required: Both archival/email/finalization commit orders, anonymous intake, current invitation/settings policy, independent scopes and full candidate/answer/invitation/event rollback.
- Verification result: Intake now locks optional active account, Alliance, active Kingdom and settings in owner order and validates current account email. Twelve new PostgreSQL cases cover both archival/email/finalization/settings orders, terminal current-email rejection, anonymous duplicate policy and full public/invitation answer/history/consumption/delivery rollback. Executable verification pending.
- Completion evidence: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

### HARD-080 — Anonymized Recruitment candidates remain mutable through owner actions

- Area: Recruitment candidate stage, notes, tags, reviewer, communication, onboarding, merge and conversion paths.
- Finding: The management listing, bulk preview and re-entry owner exclude anonymized candidates, but most direct mutation actions check only merged_into_id. After purge, a candidate can be reopened and receive new notes, tags or communications, recreating data on a terminal retained record.
- Current owner: Recruitment candidate lifecycle and mutation owners.
- Intended authoritative owner: An explicit current terminal-state guard enforced by every candidate mutation after acquiring its row lock.
- Rationale: A retained anonymized record must not regain personal data or operational authority through a stale ID.
- Remediation: Centralize the candidate mutability invariant, apply it to all direct mutation/merge inputs and align management detail/action projections without hiding historical rows globally.
- State: Complete.
- Verification required: Every candidate mutation rejects terminal records without effects; both purge/write orders, mutable retries, merge source/target protection, current projections and complete rollback.
- Verification result: The explicit candidate invariant is enforced after current row locks by mutation owners, including both merge inputs; the joined projection ignores terminal records. Detail and duplicate projections exclude terminal records without a global scope. Twenty retention cases include nine direct mutations after actual purge, management/duplicate projections, both communication/onboarding cleanup orders, changed binding, child delivery rollback and rollback after the final anonymization update. Executable verification pending.
- Completion evidence: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

### HARD-081 — Reviewer assignment and direct transfer handoff reverse membership locks

- Area: AssignRecruitmentReviewer and EndMembershipForTransfer independent owner boundaries.
- Finding: Both acquire a shared Alliance scope and their actor membership before exclusively locking another target membership. Opposing officer operations can acquire inverse actor/target dependencies before eligibility or hierarchy rejection. Transfer completion currently supplies an exclusive outer scope, but the handoff action itself does not.
- Current owner: Recruitment reviewer assignment and Membership transfer handoff owners.
- Intended authoritative owner: Exclusive current Alliance coordination before actor/target membership authority for these protected target writes.
- Rationale: Current target validation must not require a lock sequence that can wait on another actor in the same Alliance.
- Remediation: Acquire the established exclusive Alliance owner scope for reviewer assignment and standalone transfer membership handoff while preserving permissions, hierarchy, membership and idempotency.
- State: Complete.
- Verification required: Opposing officer operations and authority revocation in both orders, direct handoff independent of completion, unrelated Alliance progress and full rollback.
- Verification result: Reviewer assignment and standalone transfer membership handoff now use exclusive Alliance scope before actor and target membership. Ten new cases cover both opposing officer orders, current reviewer suspension in both orders, unrelated Alliance progress, late delivery rollback and idempotent retry. The other status/rank/role writers with the same dependency are implemented separately under HARD-084. Executable verification pending.
- Completion evidence: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

### HARD-082 — Recruitment child updates lock communication or onboarding before candidate

- Area: MarkRecruitmentCommunicationSent and UpdateRecruitmentOnboardingStatus versus candidate purge.
- Finding: These child writers lock their child row before shared-locking candidate. Purge locks candidate before deleting communications/onboarding rows, creating an inverse parent/child dependency under shared Alliance scope.
- Current owner: Recruitment candidate child writers and retention purge.
- Intended authoritative owner: Candidate-first lifecycle coordination with revalidated discovered child routing.
- Rationale: Terminal candidate checks must serialize with child updates before retention removes dependent rows.
- Remediation: Discover child routing without locking, acquire scoped current candidate before the child lock, revalidate candidate binding and preserve sent/complete retry semantics.
- State: Complete.
- Verification required: Both child-update/purge orders for communication and onboarding, changed routing, terminal rejections, independent candidates and late event rollback.
- Verification result: Both child owners now discover routing without a lock, stabilize scoped candidate shared, validate terminal/merge state, and lock the child with the candidate binding rechecked. The twenty retention cases include both child/purge orders, changed routing, removed-child retry and complete late child/retention rollback. Executable verification pending.
- Completion evidence: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

### HARD-083 — Staying transfer completion blocks another Player actor reference

- Area: CompleteTransferParticipant and Player foreign keys in completion/audit records.
- Finding: Staying completion acquires target Player FOR UPDATE even though it does not mutate identity. Two Alliances in the same Kingdom can track each other's officers as Staying roster targets. Each completion can then hold the other actor's Player while inserting its own actor reference, reversing the actual Player foreign-key dependency.
- Current owner: Transfer completion target identity stabilization.
- Intended authoritative owner: Shared canonical Player stabilization for Staying observation; exclusive Player ownership for incoming/outgoing identity mutation.
- Rationale: Preserve current identity without blocking compatible Player actor references across independent Alliance scopes.
- Remediation: Use FOR SHARE for Staying target identity and retain FOR UPDATE for actual incoming/outgoing movement. Keep Kingdom-before-Player order and all current authority, roster and completion checks.
- State: Complete.
- Verification required: Real opposing Staying completions in both initiating orders, unchanged identity/history, correct Player attribution, idempotent retry, archival serialization and late rollback for every direction.
- Verification result: Two new connection cases construct legitimate cross-Alliance roster targets and attempt the opposing completion while the first holds its target identity. Existing archival cases recognize shared or exclusive target locks and retain Kingdom-first and no-competing-Player assertions. Executable verification pending. The abda37b1 containing parallel job passes all 1,267 tests / 80,498 assertions, full Pint/PHPStan and fresh schema; container/staging/recovery also passes (job 102352927089); both complete serial gates now pass with the same 1,267 tests / 80,498 assertions.
- Completion evidence: All nine workflows pass on abda37b1b3093c8cde37026eb19fccc8b9d097eb, including 1,267 tests / 80,498 assertions in parallel PHP and both complete serial suites, current static analysis, fresh schema, frontend, fifty visual cases, security/capabilities and production container/staging/recovery. The containing commit includes the recorded owner/concurrency/rollback regressions.
- Commit SHA: verified containing candidate `abda37b1b3093c8cde37026eb19fccc8b9d097eb`.

### HARD-084 — Remaining protected membership writers acquire opposing actor/target locks

- Area: UpdateMembershipStatus suspension/removal, UpdateAllianceRank, AssignMembershipRole and RemoveMembershipRole.
- Finding: These writers still take shared Alliance scope, then actor membership FOR UPDATE, then a different target membership FOR UPDATE. Opposing officers can hold inverse actor/target resources before current permission or hierarchy rejection. Activation already uses exclusive Alliance scope.
- Current owner: Membership administration and specialist role assignment/removal owners.
- Intended authoritative owner: Existing exclusive Alliance coordination before protected actor/target authority.
- Rationale: All current target membership writers must follow the same cycle-free coordination contract.
- Remediation: Trace composed callers, use the existing exclusive Alliance owner scope, and preserve rank/delegation/hierarchy and idempotent receipt behavior.
- State: Complete.
- Verification required: Opposing target actions in both orders, current revocation, independent Alliances, role/rank/status behavior and full rollback.
- Verification result: All four owners use exclusive Alliance scope; activation retains that existing contract. Direct adapters and bulk compositions do not hold a preceding shared scope. Twenty new PostgreSQL cases cover both opposing orders for role assignment/removal, rank, suspension and removal; explicit specialist grants and current demoted rank ceiling; unrelated Alliance progress; late delivery rollback and idempotent retry. Existing delegation/revocation suites remain required. Executable verification pending.
- Completion evidence: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

### HARD-085 — Manual Recruitment stage owner can mark a pending invite Joined

- Area: ChangeRecruitmentStage and controlled Membership handoff.
- Finding: HTTP manual/bulk stage rules exclude Joined, but owner preview can advertise it as ready and the direct stage owner accepts it whenever membership_invitation_id is non-null. Issuing an invitation therefore permits a direct caller to record Joined before acceptance; MarkRecruitmentCandidateJoined already owns the invitation.accepted projection.
- Current owner: Recruitment manual transition and Membership acceptance event projection.
- Intended authoritative owner: Joined is caused by the controlled invitation acceptance handoff; manual transitions enforce that boundary themselves.
- Rationale: An issued invitation is not evidence of completed Membership admission.
- Remediation: Reject manual Joined transitions in the owner and verify real invitation acceptance remains the authoritative, replay-safe transition.
- State: Complete.
- Verification required: Direct/manual/bulk Joined rejection before acceptance, actual acceptance projection, terminal/stale events and idempotent delivery.
- Verification result: Manual Joined is rejected at the owner boundary and bulk preview marks it transition-not-allowed. Six new cases cover absent/pending invitation attempts, real AcceptInvitation output projected exactly once, late projection rollback/retry, and delayed events after Declined or actual retention purge. Membership acceptance remains the authoritative relationship. The first 4de40440 static-analysis run identifies a now-unreachable Joined timestamp branch; it is removed. Executable verification pending.
- Completion evidence: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

### HARD-086 — Recruitment bulk execution loses remaining outcomes after authority revocation

- Area: BulkChangeRecruitmentStage partial commits and aggregate audit receipt.
- Finding: Each candidate commits independently, but the executor catches unavailable/invalid transitions only. A current AuthorizationException on a later item escapes after earlier commits, suppressing remaining outcomes and the aggregate receipt. Membership's corresponding bulk executor already handles current authority rejection per item.
- Current owner: Recruitment bulk execution adapter over the authoritative single-candidate action.
- Intended authoritative owner: Complete bounded per-item results and receipts while each write revalidates current authority.
- Rationale: Callers must be able to account for partial progress and retry only failed items after current permission changes.
- Remediation: Handle current authorization failure as an explicit per-item failed outcome while retaining owner enforcement and the aggregate receipt.
- State: Complete.
- Verification required: Revocation before first and between committed items, unchanged failed candidates, complete outcomes/receipt and selective retry after authority restoration.
- Verification result: The bulk executor maps current AuthorizationException to permission-denied while retaining per-item owner enforcement, all outcomes and aggregate receipt. The outcome is localized in every supported catalogue. Two cases use actual role revocation after preview or after the first real transaction commits, then restore authority and selectively retry only failures. Executable verification pending.
- Completion evidence: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

### HARD-087 — Recruitment detail and duplicate projections materialize unbounded history

- Area: RecruitmentCandidateController.show and RecruitmentDuplicateFinder.
- Finding: Detail eagerly loads every note, stage history and communication and hydrates every duplicate candidate. Repeated valid mutations and repeated historical applications grow these sets without an owner limit; the complete collections are returned in one page.
- Current owner: Recruitment detail/duplicate projection adapters.
- Intended authoritative owner: Bounded scoped projections with explicit navigation or continuation semantics.
- Rationale: Retained history must remain inspectable without unbounded request memory, query results or response size.
- Remediation: Introduce bounded deterministic history/duplicate queries and visible continuation using the existing page/ReadModel architecture; preserve current scope and terminal-state filters.
- State: Complete.
- Verification required: Large histories, deterministic pagination, independent history categories, scoped continuation, current authorization and matching frontend contracts.
- Verification result: Authorized detail composition moves into the existing RecruitmentManagement ReadModel; the mutation controller no longer renders it. Notes/history/communications and duplicates each return independent 25-record PageSlices with scope-bound timestamp/ID cursors, current authority and terminal checks, and duplicate-fact fingerprinting. Current indexes include tie-breakers and normalized matching. Thirteen PHP cases cover bounds, deterministic continuation, changed/deleted boundaries, scope and authority, with two desktop/mobile browser cases covering independent navigation and retained note drafts. Local full frontend checks pass before the final pagination preservation option; that option and browser fixture receive final containing checks. Executable PHP/browser verification is pending. Remaining catalogues/selectors are tracked under HARD-088.
- Additional composition finding: TransferCampaignWorkspaceQuery hydrated up to fifty communications and reported that capped length as total. It now uses an SQL count and one latest record, so the complete detail stays within the communication hydration budget; the 51-record history case also checks the true summary total. All 52 browser cases pass on db969f23, including both new history navigation cases (job 102362996920). PHP containing verification remains pending.
- Completion evidence: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

### HARD-088 — Recruitment catalogues, attachments and selection lists lack result bounds

- Area: RecruitmentManagementQuery configuration collections, candidate attached tags/reviewers/onboarding/answers, conversion roster selection and decision-template selection.
- Finding: Configuration and candidate projections still materialize entire question/template/onboarding/tag/reviewer collections. Valid creation/assignment history has no corresponding owner cardinality bound; the conversion roster selector also loads every active nonmember entry while UpsertRosterEntry has no roster count limit. Membership's active-member bound does not bound these other collections.
- Current owner: Recruitment configuration owners, candidate associations and RecruitmentManagement projections over Membership roster facts.
- Intended authoritative owner: Explicit bounded configuration/selection projections with visible continuation and current selection validation.
- Rationale: Bounding notes/history/communications/duplicates alone does not bound the remaining workspace and public form projections.
- Remediation: Trace each collection's product contract, use paginated/searchable selectors or justified owner cardinality constraints, and preserve complete current form/assignment semantics without silently truncating results.
- State: Complete.
- Verification required: Large catalogues/rosters/attachments, scoped continuation, selected-item preservation, complete required intake answers, current authorization and frontend behavior.
- Verification result: All three implemented slices pass the final containing normal CI at e6f29ebbd40687c38a43554facb3ec9ef872ea8c. Independent JUnit inspection confirms RecruitmentConfigurationCapacityV3Test 14 cases / 84 assertions, RecruitmentCataloguePaginationV3Test 4 / 85, and RecruitmentCandidateCollectionsV3Test 10 / 197, each with zero failures/errors/skips. The complete run passes 1,507 cases / 83,068 assertions; normal Visual Regression passes all 62 cases, including both candidate-collection and catalogue-navigation viewports. The full frontend, static-analysis, fresh-schema and security gates also pass.
- Additional verification: The earlier local/partial results are superseded by containing execution. Current selector/attachment pagination, selected-item preservation and complete merge batching are implemented; this item has no remaining pending slice. Other Recruitment findings discovered later require new IDs.
- Completion evidence: ADR-0042; existing owner-local configuration/catalogue/candidate tests; CI 34479708547, Visual 34479708461, phpunit-results artifact 10153732965 (JUnit SHA-256 542451055014a36d3564bee1715cb0d81c4ee148932cb019027823e284cde05d).
- Commit SHA: implementation in branch history including ffbfed1ccab98f553abb1ea8479237e076a48478; verified containing head e6f29ebbd40687c38a43554facb3ec9ef872ea8c, actual test checkout 4485b87f0ab5aef0f93d2f70f8ae6b088697c22b.

### HARD-089 — Recruitment free-text owner limits depend on HTTP adapters

- Area: AddRecruitmentNote and individual/bulk stage, merge and re-entry reason mutation owners.
- Finding: AddRecruitmentNote validates only nonempty text while its HTTP adapter permits up to 10,000 characters and the candidate textarea stops at 5,000. Stage, merge and re-entry reasons accept direct-owner text without the adapter's finite input limit; bulk must reject malformed common input before outcomes/audit.
- Current owner: Recruitment text mutation owners and their adapters.
- Intended authoritative owner: Shared existing product input bounds enforced by each protected owner entrypoint.
- Rationale: Direct and HTTP callers must have one finite accepted input contract.
- Remediation: RecruitmentTextInput owns trimmed Unicode-aware note/reason normalization (10,000/5,000 characters), null empty optional reasons and field feedback. All five owners validate before effects; adapters/page props use the constants. Candidate note/reason forms display matching errors, and the note textarea uses its existing accepted 10,000-character contract.
- State: Complete.
- Verification required: Exact boundaries, whitespace and Unicode handling, optional empty reasons, direct-owner rejection before effects and matching adapter contracts.
- Verification result: Nineteen new PostgreSQL cases exercise direct/HTTP exact multibyte boundaries and overflow for all five paths, both optional-null/whitespace forms and empty-note feedback, with retained values and unchanged candidate/history/audit/outbox snapshots on rejection. Full local frontend check passes; final bulk-page prop type, lint and formatting verification and documentation links (252 files) pass. PHP execution remains pending.
- Completion evidence: RecruitmentTextBoundaryV3Test and current architecture/owner contracts; no database fallback or historical migration.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

- Containing verification: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.

### HARD-090 — Recruitment configuration and public intake have incomplete owner input limits

- Area: Settings, question create/update, decision templates, onboarding configuration and public application owners.
- Finding: Configuration owners lack existing HTTP prompt/help/name/body/description and 30-option/160-character bounds. Public application text answers have no finite length bound even through HTTP; full name/contact/source owner validation also differs from adapter bounds. These inputs feed retained snapshots and communications.
- Current owner: Recruitment configuration and intake actions, with some limits only in controllers.
- Intended authoritative owner: Existing protected owner input contracts, reused by adapters and forms.
- Rationale: Bounded result counts alone do not bound individual retained payloads or prevent direct-owner database errors.
- Remediation: Reconcile current schema/adapter/form limits, enforce meaningful owner validation for all accepted input shapes, and bound answer/options content without discarding valid required answers.
- State: Complete.
- Verification required: Current and excessive text/options, malformed answer shapes, full required-answer semantics, direct/HTTP parity and atomic rejection.
- Verification result: Shared limits now apply before effects in settings, question create/update, decision templates, onboarding, application invites and submission. Adapters and maxlength controls use the same map, with visible form/row errors. Fifteen configuration/page and ten application-answer cases cover exact Unicode boundaries, empty/oversized/malformed values, unchanged state after rejection, unique selections and required/optional semantics. Full local frontend checks pass; final question edit-state initialization receives targeted checks. Two new desktop/mobile cases verify visible create/update errors, retained drafts and creating then editing a question without reloading. Containing PHP/browser execution is pending.
- Completion evidence: RecruitmentConfigurationInputV3Test, RecruitmentApplicationAnswerBoundsV3Test, ADR-0040 and reconciled owner/HTTP/form contracts.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

The first containing PHP run confirms all remaining history/privacy behavior apart from one tag-fixture expectation. HARD-090 exposes signed-smallint position storage and real-cache test throttling; integer storage, isolated input clients and one explicit throttle case correct those causes without changing maximum-value persistence assertions.

- Containing verification: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.

### HARD-091 — Recruitment anonymization leaves private control and identity links

- Area: Unsuccessful-candidate retention, re-entry projection and retained delivery/audit metadata.
- Finding: PurgeExpiredRecruitmentCandidates retains player_id, source and re-entry fields, while the alternate re-entry page serves anonymized candidates. Audit copies retain private source/tag/control detail; delivery events unnecessarily copy reason/date/source values. The product requires private re-entry controls to obey candidate retention while auditing current reason/date changes.
- Current owner: Recruitment retention/re-entry/intake and Infrastructure AuditRecorder.
- Intended authoritative owner: Recruitment's retention boundary with scoped Infrastructure metadata redaction and minimal delivery payloads.
- Rationale: Private review state must expire with the candidate while preserving event chronology and independently owned handoff facts.
- Remediation: Clear candidate Player/source/re-entry fields, exclude terminal alternate detail, delegate exact subject/Alliance/event metadata redaction to AuditRecorder, and emit control/change/presence delivery flags instead of private text. Application, tag and re-entry audit metadata become a retention marker in the same transaction; event/actor/subject/time fields remain. No historical migration or fallback event shape.
- State: Complete.
- Verification required: Actual private control and linked candidate before/after purge, read denial, unchanged handoff facts, both competing orders, scoped redaction, rollback/retry and retained metadata policy.
- Verification result: Seven new PostgreSQL cases cover actual conversion and decline, audited reason/date before purge, minimal outbox values, terminal-field clearing and both HTTP pages, application-source/tag audit removal, unrelated subject/type/event/Alliance isolation, two late failure points and both re-entry/purge orders. PHP execution pending; whitespace passes.
- Completion evidence: RecruitmentPrivateRetentionV3Test, ADR-0037 and reconciled acceptance/architecture contracts.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

- Containing verification: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.

### HARD-092 — Governance timeline exposes recruiter-private metadata through broader officer access

- Area: AllianceGovernanceTimelineQuery, HTTP governance timeline and AllianceGovernanceAssistantQuery.
- Finding: Timeline access accepts MembershipManage/RoleManage/Manage and returns raw metadata for all recruitment events. Those permissions do not imply RecruitmentManage, so private source/tag/re-entry audit detail can bypass the Recruitment page's current authority. The query has no current viewer argument and Assistant uses the same projection.
- Current owner: AllianceGovernance timeline and its HTTP/Assistant adapters.
- Intended authoritative owner: Current viewer-authorized cross-owner projection.
- Rationale: Broader governance access must not disclose recruiter-private state, including through continuation or Assistant composition.
- Remediation: Carry current viewer identity into the projection, retain governance admission, filter unauthorized Recruitment records before paging and migrate all callers without a viewer-less fallback.
- State: Complete.
- Verification required: Officer without RecruitmentManage, recruiter with timeline access, direct/HTTP/Assistant paths, revocation, filtered continuation and cross-Alliance scope.
- Verification result: Current viewer admission and Recruitment visibility now live in the shared query; unauthorized events are removed in SQL before paging. Six new cases cover direct/HTTP/Assistant paths, authorized private metadata, filtering before limits, both role revocations, existing-instance/cursor freshness, and cross-Alliance isolation. PHP execution pending.
- Completion evidence: GovernanceRecruitmentPrivacyV3Test, migrated existing behavior cases, ADR-0038 and acceptance contract.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

- Containing verification: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.

### HARD-093 — Transfer campaign composition clips facts and expands unrelated history

- Area: TransferCampaignWorkspaceQuery and TransferEligibilityQuery.
- Finding: The campaign's latest-twenty blocker load is filtered for active state only after limiting, hiding older active blockers. Eligibility independently materializes all plan observations, all window conditions and group Kingdoms even for one candidate. The communication total is separately corrected within HARD-087.
- Current owner: GameWorld transfer eligibility facts and RecruitmentManagement campaign composition.
- Intended authoritative owner: Bounded current-fact selection with truthful visible history summaries.
- Rationale: A nominally bounded page must neither omit active blockers through pre-filter limits nor load unrelated historical evidence for a single participant.
- Remediation: Trace factual selector semantics and consumers, select only relevant latest/valid evidence with stable provenance, filter active blockers before bounded presentation and expose continuation/true summaries as required.
- State: Complete.
- Verification required: Older active blockers behind resolved entries, multiple observation kinds/targets, stale/missing facts, unrelated participant/Kingdom volume and current source authority.
- Verification result: Shared bounded witness selection preserves the existing observation/condition selectors, and groups are restricted to relevant Kingdom membership. Capacity observations are selected in SQL and commitments aggregated without model hydration. Campaign counts cover all matching records. History has an independently authorized 25-record cursor endpoint and lazy first/next/retry UI. Twenty-two PHP cases and two desktop/mobile cases cover semantics, scope, volume and continuation. Full local frontend checks pass; the final browser additions pass targeted formatting/lint. All 22 PHP cases pass within main CI job 102402657713 on `460f0f9a` (1,472 tests / 82,752 assertions); all 60 visual cases, including both new Transfer history cases, pass in Visual run 34332044023. PHPStan and fresh PostgreSQL also pass.
- Completion evidence: ADR-0041, TransferEligibilityEvidenceBoundsV3Test, KingdomTransferPlanning browser cases and current contracts; containing main PHP, visual, PHPStan and fresh-schema evidence above.
- Commit SHA: verified containing candidate `460f0f9ae47081b7e30f330eb636dbdddc57875a`.

### HARD-094 — Membership governance history filters after an unrelated global limit

- Area: MembershipGovernanceHistoryQuery and member history projection.
- Finding: The query loads the latest 500 Alliance audit records, then filters for a selected Player and takes at most 100, so unrelated recent activity can hide all valid Player history; the response has no continuation.
- Current owner: AllianceGovernance Membership history projection.
- Intended authoritative owner: Scoped Player-history query with bounded visible continuation.
- Rationale: Bounded materialization must preserve the user's requested subject and history reachability.
- Remediation: Apply supported Player metadata predicates in SQL before limits, reconcile indexes and scoped cursors, and migrate the HTTP page and Member Capability Profile preview to the same authorized PageSlice contract.
- State: Complete.
- Verification required: More than 500 unrelated entries, multiple Player reference keys, deterministic ties, complete scoped continuation and current governance authority.
- Verification result: SQL target filtering, stable scoped continuation, current read admission and target relationship guard are implemented. Twelve PHP cases cover five keys, large unrelated history, tied/deleted/new boundaries, wrong scopes, revocation and HTTP/profile consumers; two browser cases cover complete navigation. Full local frontend checks and 255 documentation-link checks pass. Containing PHP and browser gates remain pending.
- Completion evidence: MembershipHistoryPaginationV3Test, MembershipHistoryPagination.spec.ts, extended RecruitmentHistoryVisualFixture and ADR-0039.
- Commit SHA: implementation commits in branch history; containing verification `14a6bc4d8392502c186af2631842d2a1eee1a6f7`.

- Containing verification: Containing verification on `14a6bc4d8392502c186af2631842d2a1eee1a6f7`: main PHP job 102391286419 executes 1,454 tests / 82,456 assertions in 24:22; all cases for this item pass. Its six errors are confined to HARD-093 fixtures missing required Player IDs. Pint (1,950 files), PHPStan, fresh schema, full frontend and all 58 visual cases pass.

### HARD-095 — Transfer workspaces and member profiles expand complete participant histories

- Area: TransferParticipantQuery, Readiness/Manage/Completion, MemberCapabilityProfileQuery and AllianceCommandQuery.
- Finding: forPlan loads the complete participant set; includeWithdrawn also eagerly loads all blockers and readiness transitions with actors. MemberCapabilityProfileQuery calls the full-plan query before selecting one Player in PHP. CommandOverview also loads the full plan before aggregate evaluation. HARD-093 bounds evidence per requested participant but does not bound these participant or workflow-history collections.
- Current owner: GameWorld Transfer participant queries and consuming ReadModels/HTTP workspaces.
- Intended authoritative owner: Scoped participant facts and explicit bounded workspace/history/aggregate projections.
- Rationale: A member profile must not hydrate unrelated participants, and current workflow state must remain complete without returning every historical child row.
- Remediation: Trace writer cardinality and each consumer, introduce precise single-participant reads and bounded workspace/history navigation or aggregate evaluation without clipping meaningful facts.
- State: In progress.
- Verification required: Large plans and child histories, complete current blocker/state semantics, scoped continuation, current authority and profile/dashboard query budgets.
- Verification result: First slice replaces full-plan profile hydration with a current-authorized alliance/plan/player SQL lookup, nonwithdrawn only and without unrelated eager relationships. The existing partial unique plan/player index supports the lookup. Five new regressions cover 150 unrelated participants, withdrawn/missing targets, exact scope, revoked membership and foreign officers; before remediation the profile hydrated 151 rows (or 30 unrelated rows after withdrawal). Corrected new/existing profile tests pass 8 / 29; broader Roster/Transfer Feature plus budget tests pass 57 / 603. Changed production PHPStan and Pint pass. No production schema, response contract or eligibility implementation changed. Dashboard/workspace/history remediation and final containing gates remain required.
- Workflow-history slice: `TransferWorkflowHistoryQuery` reauthorizes the current actor and exact Alliance/Plan/participant before independent active/resolved blocker and readiness-transition pages. Encrypted cursors bind kind/state; complete scoped SQL counts replace embedded child arrays. `(created_at,id)` keysets have fresh-schema indexes and dated rows; deletion of the boundary row does not prevent continuation. The owner-local Vue component retains drafts, displays retry, validates response shape and discards superseded requests. Existing writes and canonical eligibility remain unchanged.
- Workflow-history verification: red HTTP case materialized 145 child models; green materializes zero with complete counts. Thirteen PostgreSQL cases / 132 assertions cover complete multi-page traversal, old active blockers, timestamp ties, deleted boundaries, current revocation, cross-state/kind/participant cursors, mismatched scope rows, malformed positions, bounded probes, schema and separate project fixtures. Full production PHPStan and frontend checks/build pass. The new desktop/mobile journey is authored and wired into normal visual CI; the changed readiness layout requires rendered fingerprint review. Participant, dashboard and other workspace collections remain open. Containing randomized Transfer/Profile/CommandOverview/System Architecture passes 150 tests / 47,673 assertions in 52.648 seconds, seed 95. Actual Playwright discovery finds all prior 64 cases plus two new history cases, 66 total in 19 specs; discovery is not a browser pass. This is not complete program acceptance.
- Participant-workspace slice: Index, Manage, Readiness and Completion now request authorized 25-row ID-keyset pages (26-row probe) and complete separately scoped SQL totals. Encrypted cursors bind actor, Alliance, plan, withdrawal view and permission; deletion of the boundary record does not invalidate continuation. Current authorization is checked before hydration. Related cohort/completion/reservation/invitation rows are correlated to the exact plan and Alliance. Canonical fresh schema adds full/active participant-page indexes. Canonical eligibility evaluates only the explicitly displayed page; readiness filters are clearly labelled page-local, not complete-plan eligibility totals.
- Participant verification: red HTTP fixture hydrates67 instead of a bounded page; corrected13 cases/85 assertions pass for all four workspaces, full counts,67-row traversal, deleted boundary, foreign actor/plan/filter cursors, invalid positions, revocation/demotion, related-plan isolation, SQL budgets and separate browser fixtures. Final stable randomized containing scope passes165/47,772 in63.018s, seed95. Full production PHPStan and frontend checks/build pass. All21 Node cases pass, including active-principal change isolation, edited draft retention and100 clean page transitions without retaining stale rows. The owner-local pager retains the last page on network/HTTP failures, supports retry and cancels stale scope requests. All original writes/eligibility rules remain.
- Participant browser verification: two new per-viewport journeys traverse61 unique rows across25/25/11 pages, inject a503, retry and check draft retention after returning to the first page. Independent seeded accounts prevent contamination of existing visual fixtures. Discovery finds68 cases/20specs; containing browser execution remains required. The prior history journey now selects the actual labelled Summary field, preserving its draft assertion. Complete totals count the current view, not a cross-request snapshot; database row changes may alter later pages.
- Remaining collections: CommandOverview still consumes `forPlan`; do not delete the live method or relabel it as unused compatibility. Management windows/plans, cohorts/groups/conditions/capacity and identity selectors still require bounded continuation or bounded option search with complete counts. These are not covered by participant paging. HARD-095 remains In progress.
- Completion evidence: MemberTransferProjectionTest and targeted runtime output in the profile implementation commit; not Complete because the remaining consumers are still unbounded.
- Commit SHA: first profile slice `a5cadcec93d9db046c96baf14caeccbbf11b98ca`; remaining consumers are not complete.

#### Dashboard verification preview slice

- Implemented owner-local `TransferVerificationPreviewQuery` and immutable coverage DTO; at most25 actual canonical assessments, with exact whole-active-plan participant/manual-blocked totals from the same SQL statement. No copied eligibility rule, persistent derived authority, global cache or compatibility API.
- A partial preview is `assessment_incomplete`, never verified, even when the assessed prefix has no finding. Known affected count is explicitly a lower bound; unassessed total and bounded-ID completeness travel through Dashboard, Assistant evidence/answers, Officer Briefs and queued notifications. Existing small-plan/missing-window behavior and all write/source/delivery fences remain intact. ADR-0051 records why the explicit preview is preferable to unbounded evaluation or a duplicated snapshot authority.
- Removed `TransferParticipantQuery::forPlan` after migrating its final live dashboard consumer. The separate canonical `TransferEligibilityQuery::forPlan` remains. No fallback alias or second evaluator was added.
- Red: actual overview hydrates67 participants. Green: eight cases/49 assertions pass, with at most25 hydrated/assessed, exact67 total/42 unassessed, off-page manual blockers, withdrawal, no double counting, real missing-evidence rules, empty/clear small plans, revocation/tenant isolation and actual consumer propagation. Randomized containing295 cases/51,494 assertions pass seed951 in56.031s. Full production PHPStan and frontend check/build pass;22 Node source cases include17-locale coverage verification. See overview-red.xml, overview-expanded.xml and overview-containing.xml in the current evidence set.
- Remaining HARD-095 work: management catalogue and identity-selection continuation; complete containing browser review and normal milestone. This slice changes the intended overview contract explicitly rather than misrepresenting an incomplete calculation as complete. No whole-program or merge readiness is claimed.
- Prior participant implementation: `6457d0a817eb1d04a2f46ac372132acf74ba7e7b`, now contained by successful normal PHP CI and passing new browser journeys. Current dashboard slice follows that checkpoint.

- Read-ownership continuation: from `4eea5dfc`, two new cases reproduce the misplaced GET adapters and unused overview catalogue. The corrected three-case scope passes 10 assertions. A 65-cohort fixture now hydrates zero unrelated cohorts and exports no unused catalogue; revoked R1 management is denied before plan hydration. The randomized ReadModel/participant-page/System Architecture scope passes 39 tests / 47,076 assertions in 6.261 seconds, seed 952, with zero failures/errors/skips. Full app/routes PHPStan passes at level8; scoped Pint and PHP8.5.10 syntax pass. After reconciling concurrent HARD-106, the same scope passes39/47,105 in5.815s, with full production PHPStan and all72 Node cases passing. The original write methods and transport row shapes remain intact; only the unused overview cohorts prop is removed. Remaining management arrays are still explicitly pending in HARD-095, not silently declared bounded.

#### Current-authorized management choice slice

- The previous management GET hydrated65 roster rows for a single selector and loaded the whole active membership audience. TransferManagement now owns searchable25-row window/coordinator/roster pages with26-row probes, stable-ID/high-water cursors, complete current matching counts and separately authorized selected values. Cursor scope binds actor/Alliance/kind/plan/search; current access is checked even for authentic cursors. Foreign or no-longer-current selections return no private option data.
- Removed management's `players` and `rosterOptions` arrays and their unused query dependencies; no compatibility props or duplicate permission authority remain. Original create/update/assignment Actions are unchanged. Fresh-schema indexes cover the tenant/state/ID traversals. Private choice responses are not cached.
- The owner-local picker searches and loads lazily, retains off-page selection and last successful data on network/HTTP/shape failures, retries the same cursor and discards superseded requests. Active actor/Alliance/plan changes discard old-scope creation drafts. All17 locales provide search, count, unavailable and retry copy. No frontend rule grants authority or replaces canonical compatibility checks.
- Verification: the red hydration case fails65vs0. The corrected15 PostgreSQL cases pass203 assertions, including65-row complete traversal for all three kinds, selected values outside search/page, deleted boundaries, current membership/roster states, closed/foreign plans, cursor isolation/tampering, archived Kingdoms, exact index existence and distinct55-row browser fixtures. The randomized containing191-case scope passes48,291 assertions with no failures/errors/skips, seed953. Full production PHPStan and current frontend quality/build checks pass as recorded in the resume header; twelve new Node cases preserve bounded responses, errors/retry, race handling, scope isolation,100 clean page transitions and locale contracts.
- Two real desktop/mobile browser journeys are authored and included in normal visual fixture setup: traverse55 choices across25/25/5, inject503/retry, retain selected/draft state, select an off-page coordinator, perform actual cohort/participant writes and locate an older window beyond the former25-row cap. They are not yet reported as executed. Full management catalogue and cohort-choice bounds remain unfinished; this slice is not HARD-095 or program completion.

### HARD-096 — Full serial verification exceeds the CI job budget

- Area: Architecture V3 Verification and Intelligence Verification workflows.
- Finding: Both full serial suites on `460f0f9a` reach their 45-minute job timeouts after passing preparation/static/architecture checks. The expanded main parallel suite passes 1,472 tests in 24:40; complete serial execution needs more time than the inherited job budget.
- Current owner: GitHub Actions workflow definitions.
- Intended authoritative owner: Main CI owns complete PHP regression; specialized Architecture and Intelligence gates own their applicable contracts without independently repeating the complete application suite.
- Rationale: Increasing serial timeouts retained duplicated full-suite work. The stronger verified design keeps every PHP identity in the mandatory main suite and preserves dedicated architecture, Intelligence and security acceptance rather than repeating unrelated application tests in each specialized gate.
- Remediation: The earlier timeout-only proposal was superseded during the repository-wide testing work: disjoint owner-first discovery, bounded existing two-worker complete CI, dedicated specialized contracts, safe dependency-download caching, early layout guards and timing/failure artifacts. No required scenario or critical integration gate became non-blocking.
- State: Complete.
- Verification required: Complete main PHP inventory, specialized Architecture/Intelligence contracts, source/discovery reconciliation and all normal containing workflows pass with failing exits preserved.
- Verification result: All nine normal workflows pass at e6f29ebbd40687c38a43554facb3ec9ef872ea8c. Full main PHP is 1,507 unique cases / 83,068 assertions with no failures/errors/skips, two workers; Architecture 34479708446 and Intelligence 34479708408 finish successfully. Main CI 34479708547 also passes schema/static/dependency/frontend/image/recovery gates; Visual 34479708461 passes 62 cases. All 1,488 pre-reorganization PHP identities remain plus explicit reporter/reference additions. The obsolete requirement to repeat the entire suite in each serial gate is superseded, not claimed as executed.
- Completion evidence: Canonical executed evidence and limitations are in docs/codebase/test-validation-2026-09-10.md and test-performance-reporting.md; full JUnit artifact 10153732965. No causal whole-suite speedup is asserted.
- Commit SHA: verified source e6f29ebbd40687c38a43554facb3ec9ef872ea8c; documentation-only closeout 5c70e4402a35fc4ed68072fe36f83b5b845c40a2.

### HARD-097 — Assistant self-transfer duplicates eligibility interpretation and loads unbounded evidence

- Area: TransferSelfEligibilityQuery used by Alliance Assistant and other self projections.
- Finding: The self query reconstructs the canonical eligibility input/evaluation independently and loads complete participant observations, target conditions and all official groups. HARD-093 already bounds the canonical TransferEligibilityQuery evidence path, but this parallel path bypasses it and risks different conflict/provenance behavior.
- Current owner: Two evaluation compositions within GameWorld/KingdomTransfers.
- Intended authoritative owner: TransferEligibilityQuery and TransferEligibilityEvidenceQuery own one evaluation/evidence composition; self projection owns only current-authorized target selection and response formatting.
- Rationale: An Assistant answer must interpret the same authoritative facts as the management surface without materializing historical collections or creating another eligibility authority.
- Remediation: Route self evaluation through the canonical bounded query, preserve current actor/Alliance/target checks, use SQL for complete observation counts, remove duplicate evaluator/selector/condition/group construction and add actual outcome/provenance/hydration regressions.
- State: Complete.
- Verification required: Canonical/self parity for current conflicts, unknown/untrusted facts, staying/missing targets, correct complete observation counts, current revocation, cross-Alliance/self isolation, bounded hydration and Assistant behavior.
- Verification result: The self query now delegates current facts to the canonical bounded eligibility query and selects its exact actor participant through the authorized owner query. All duplicate input/assessment/selector/group/condition composition is removed; complete observationCount is a scoped SQL aggregate. Eight additional behavior cases preserve canonical outcome, all requirement/provenance fields, complete counts, target semantics, revocation and bounded hydration. Before remediation two regression cases hydrated 183 and 80 unnecessary observations. All 11 self cases / 49 assertions pass; containing Transfer/Assistant/Roster plus owner architecture passes 95 / 714, with changed PHPStan/Pint clean. The pure composition guard is included. Full milestone gates remain required.
- Completion evidence: ADR-0044 and TransferSelfEligibilityQueryV3Test / TransferSelfEligibilityCompositionTest. Reconciled containing local JUnit hard097-containing.xml records 144 tests / 4,092 assertions; one additional real capacity/reservation parity case protects observed versus projected values. No existing test was removed. Not Complete until containing milestone verification is recorded.
- Commit SHA: `3cf898c713775f4c7df65c9ca47149f886fead1e`; containing local verification and additional capacity coverage recorded by this checkpoint.
- Final containing verification: Complete for this item at `20bf34c96532b56a091cd344eb47574c46220b46`, checkout `5e1230bff054c0cabad8790613199a966413fd59`. All nine normal workflows and 1,640 PHP cases /84,405 assertions pass with no failures/errors/skips; all62 browser cases pass without retries. CI34595577609, Visual34595577546 and artifact10261698764 supply the recorded evidence. This supersedes the earlier pending-milestone language above, not the still-open program.


### HARD-098 — Delivery claims and completion are not consistently fenced

- Area: Communications/Delivery immediate and digest workers.
- Finding: Digest claim locks its dispatch but does not recheck queued/failed-retry/stale-pending eligibility, so two preselected workers can both claim one active dispatch. Both worker completion paths accept any Pending row without matching the immutable attempt count, allowing a stale response to overwrite a newer attempt and its member/outbox state.
- Current owner: Independent claim predicates in ProcessNotificationDeliveries and ProcessNotificationDigests.
- Intended authoritative owner: Communications/Delivery owns a shared current claim-eligibility contract and monotonic attempt fencing; adapters own transport only.
- Rationale: A row lock alone does not preserve eligibility after waiting, and a Pending flag alone does not identify the worker authorized to finalize a result. Lost network acknowledgements must remain explicit at-least-once delivery limitations, not invented exactly-once guarantees.
- Remediation: Recheck the complete due/status/retry/lease/budget predicate under the row lock, fence completion by attempt count, preserve atomic delivery/digest/member/outbox transitions and add actual stale-selection/worker regressions. Review exhausted lease handling without queue starvation or silently losing ambiguous outcomes.
- State: Complete.
- Verification required: Current lease and retry exclusion, stale claim recovery, old completion rejection, member/outbox preservation, max-attempt behavior, due-time boundaries, PostgreSQL concurrency, containing Communications behavior and architecture/static gates.
- Verification result: One due/status/retry/300-second lease predicate now constrains selection and the locked read; current budgets are enforced, attempt numbers fence completion, and endpoint health commits only with an accepted result. Exhausted generations terminalize without more sends; receipt suffixes distinguish terminal reconciliation from a prior retryable failure. Digest completion mutates only still-attached Queued members. Initial real-PostgreSQL regressions failed ten of sixteen cases; final 27 cases / 214 assertions pass, including two connections/Fiber-paused transports, actual lock contention, stale success/failure, exact boundaries, rollback, endpoint pause, detachment and exhaustion starvation. The 75-case Communications/Workflow/Architecture scope and full PHPStan pass. Milestone full verification remains required.
- Completion evidence: NotificationAttemptFencingTest, ADR-0045, local hard098-milestone-local.xml (75 tests / 46,667 assertions, no failures/errors/skips), full PHPStan and scoped Pint. Do not mark Complete until containing normal milestone gates are recorded.
- Commit SHA: `e194c6baf5b1166d6727266e965ae03debc9fe51`.
- Final containing verification: Complete for this item at `20bf34c96532b56a091cd344eb47574c46220b46`, checkout `5e1230bff054c0cabad8790613199a966413fd59`. All nine normal workflows and 1,640 PHP cases /84,405 assertions pass with no failures/errors/skips; all62 browser cases pass without retries. CI34595577609, Visual34595577546 and artifact10261698764 supply the recorded evidence. This supersedes the earlier pending-milestone language above, not the still-open program.


### HARD-099 — External delivery does not reauthorize queued source authority

- Area: Communications/Delivery claims and every current notification source owner.
- Finding: Queue-time Officer Brief and Intelligence publishers verify source authority, but immediate/digest claims previously checked only destination routing and optional routing-Player ownership. Account-scoped destinations bypassed original message-Player ownership, and membership/rank could be revoked before send or retry.
- Current owner: Queue-time source authorization in source capabilities and Workflows; destination authorization in Communications.
- Intended authoritative owner: Source-specific current eligibility stays in its capability; an explicit delivery-side port is composed by the existing NotificationDelivery Workflow. Communications retains routing, claim, retry and outcome ownership.
- Rationale: A stored body or endpoint is not a durable source-data grant. Public/account-security types require different rules from Alliance messages. The dependency-inversion port avoids source-model reach-through and a circular source-to-delivery implementation dependency.
- Remediation: Add the original immutable source descriptor, bind the port to owner queries, and check it before every immediate/digest claim and retry. Cancel/detach each denied digest member before payload construction; preserve eligible members. Unknown types or missing/revoked sources fail closed; infrastructure failures propagate/roll back. Keep network work outside transactions, no compatibility fallback, and no new source authority/cache. See ADR-0046 for all thirteen type contracts and provider-handoff limitations.
- State: Complete.
- Verification required: Current account/Governor ownership, membership/rank revocation, retry-time checks, mixed digests, all thirteen type contracts, public/account-security controls, operational grants, source/poll lifecycle, infrastructure failure and existing claim/transport/architecture/static behavior; full containing milestone.
- Verification result: Initial real-publisher regressions fail nine of thirteen cases. The expanded 50-case scope passes 135 assertions, including all source types, account destinations, retry revocation, mixed member/officer digest, original account/Governor transfer, account security without a Governor, public catalogue scope, platform grant revocation, draft preview, Event and poll cancellation/expiry and real container binding. Full app/routes PHPStan passes. Existing transport/attempt tests retain their assertions and now use real owner-created Event/King Perk sources instead of synthetic subject-less payloads. Source/layout guards assign 297 PHP files once. The expanded containing Communications/Workflow/System Architecture scope passes 124 tests / 47,058 assertions in 35.618 seconds, no failures/errors/skips. No whole-application claim yet.
- Completion evidence: `QueuedNotificationSourceAuthorizationTest`, `NotificationSourceEligibilityTest`, real provider-binding test, owner-local source fixtures and ADR-0046; local source-authorization-complete.xml and containing-final.xml. Do not mark Complete before containing normal milestone gates pass.
- Commit SHA: `590894fc8c10410b56584818187d02deb3da548e`; containing normal milestone verification remains required.
- Final containing verification: Complete for this item at `20bf34c96532b56a091cd344eb47574c46220b46`, checkout `5e1230bff054c0cabad8790613199a966413fd59`. All nine normal workflows and 1,640 PHP cases /84,405 assertions pass with no failures/errors/skips; all62 browser cases pass without retries. CI34595577609, Visual34595577546 and artifact10261698764 supply the recorded evidence. This supersedes the earlier pending-milestone language above, not the still-open program.


### HARD-100 — Endpoint health can certify replacement settings using an older request

- Area: Communications endpoint lifecycle and in-flight immediate/digest provider outcomes.
- Finding: A correctly fenced current delivery attempt could use settings A, overlap an authorized change to B, and certify or degrade B using A's result. Stable endpoint identity, enabled state and delivery attempt number do not identify the settings actually used for IO.
- Current owner: Communications/Delivery.
- Intended authoritative owner: Communications owns one monotonic endpoint verification generation and one transport handoff that observes configuration plus generation together; source permissions and delivery-attempt fencing remain their existing separate contracts.
- Architectural rationale: Precise local result attribution without holding locks during network calls, leaking credential hashes, duplicating transport rules or inventing exactly-once delivery.
- Required remediation: Increment an internal generation in the existing owner-locked update/state actions; initialize it in the actual canonical fresh-schema migration. Share current recipient/channel-bound provider handoff across immediate/digest workers. Carry the observed generation in an immutable credential-free result and require it for endpoint-health mutation, while preserving truthful original delivery/dispatch outcomes. Remove both duplicated private handoff implementations. No backfill or compatibility mode.
- State: Complete.
- Verification required: Both workers and success/failure outcomes; replacement, A-to-B-to-A, label-only reset, pause/resume, deletion/recreation, current-generation control and changes before actual handoff; existing attempt/member/source/receipt atomicity, containing owner/static and final normal milestone gates.
- Verification result: Recovered candidate first reproduced sixteen failures in twenty-four cases; the eight controls passed. Twenty-eight corrected real-PostgreSQL cases now pass / 258 assertions in 54.472 seconds, including four additional actual-handoff controls and explicit generation/default/reset assertions. Provider callbacks verify IO is outside a transaction and mutate via real owner Actions; replacement raw attributes remain unchanged by the older result. Full app/routes PHPStan passes. The randomized Communications/NotificationDelivery/System Architecture scope passes 166 tests / 47,466 assertions in 107.099 seconds, seed 100, zero failures/errors/skips. No runtime-source authorization, delivery-attempt/receipt or digest-scope assertion was removed.
- Completion evidence: EndpointHealthGenerationTest, ADR-0047, hard100-red.xml, hard100-expanded.xml and hard100-containing-final.xml from this continuation. Exact canonical migration is `2026_08_16_000000_create_notification_delivery_tables.php`; the earlier staged manifest's nonexistent migration and stale bases were reconciled rather than published. No client-mass-assignable generation field or duplicate schema was added. Item remains In progress until the normal containing milestone passes.
- Commit SHA: `bd3398618cc2f5a50f5135b1dff5f5f04e778339`; containing full milestone remains required.
- Final containing verification: Complete for this item at `20bf34c96532b56a091cd344eb47574c46220b46`, checkout `5e1230bff054c0cabad8790613199a966413fd59`. All nine normal workflows and 1,640 PHP cases /84,405 assertions pass with no failures/errors/skips; all62 browser cases pass without retries. CI34595577609, Visual34595577546 and artifact10261698764 supply the recorded evidence. This supersedes the earlier pending-milestone language above, not the still-open program.


### HARD-101 — Notification reference guidance describes an obsolete single-endpoint contract

- Area: docs/reference/notifications.md and current Communications recipient UI.
- Finding: The reference advertises only Discord/Telegram and says saving replaces the previous Governor/channel configuration. The real owner supports multiple named destinations, Web Push and verified email, explicit pause/update/delete and logical inbox routes.
- Current owner: Reference documentation contradicted by the current Communications owner and product contract.
- Intended authoritative owner: One current user-facing reference linked to the actual recipient delivery contract.
- Rationale: Operators and users must not mistake adding another destination for updating or replacing one, or rely on incomplete channel/health/retry guidance.
- Remediation: Rewrite the reference for current channels and endpoint lifecycle, bounded current-attempt retry semantics and the separation between source facts and provider status; remove obsolete instructions instead of keeping a compatibility appendix.
- State: Complete.
- Verification required: Trace actual actions and current UI/channel catalogue, documentation links and existing recipient behavior evidence.
- Verification result: Reference rewritten against current Save/Update/SetState/QueueTest endpoint actions, channel enum, route resolver and inbox query. The current 75-case containing Communications/Workflow/Architecture run includes all existing recipient behavior; documentation links pass. No code, endpoint behavior or channel selection changed by this documentation-only item.
- Completion evidence: docs/reference/notifications.md now documents all five channels, additive named destinations, explicit update/pause/resume/test/delete, inheritance and bounded retry/recovery with current canonical links.
- Commit SHA: this documentation checkpoint following `e194c6baf5b1166d6727266e965ae03debc9fe51`; Git history identifies the exact reference/ledger update together.

### HARD-102 — Digest membership does not structurally bind recipient and destination

- Area: Communications/Delivery digest claim, completion and exhaustion.
- Finding: Join IDs were sufficient to read/mutate a delivery without binding its account recipient, channel and concrete endpoint to the locked dispatch. A malformed membership could disclose an otherwise authorized source to a different recipient/destination; an old response or exhausted dispatch could finalize a rebound route.
- Current owner: Communications digest grouping and worker membership reads.
- Intended authoritative owner: One Communications-owned locked member query enforces the dispatch recipient, channel, endpoint and exact join at every claim/finalization boundary. Source authorization remains the separate HARD-099 owner contract.
- Rationale: A message's own source authorization is not permission to send it to a different dispatch recipient. Earlier builder grouping and a join ID are not current authorization to mutate another route.
- Remediation: Replace ID-only member lookups with one private scoped locked query in claim, completion and exhausted reconciliation. Detach invalid joins only from the current dispatch; never cancel, fail, mark sent or rewrite an out-of-scope route. Keep the twenty-member bound and current-attempt fences. Real provider outcomes still belong to the original dispatch even when a member is rebound during IO, but do not authorize rewriting the rebound route.
- State: Complete.
- Verification required: Cross-account, concrete-endpoint and channel membership; empty/mixed digests; valid grouping; no unauthorized disclosure; foreign-route preservation at claim/exhaustion; route rebinding during successful/failed provider IO; existing retry/fencing/source/architecture gates and containing normal milestone.
- Verification result: Red PostgreSQL suite reproduced thirteen failures across fourteen cases; the valid grouping control passed. All fourteen now pass / 89 assertions in 11.939 seconds. Successful and failed transport callbacks mutate route scope only after asserting the claim transaction has committed. Full route attributes remain unchanged by the old result, invalid joins detach, and the original dispatch retains truthful outcome. Randomized containing Communications Feature/Integration, Workflow and System Architecture passes 138 tests / 47,147 assertions in 51.586 seconds, seed 102, no failures/errors/skips. Scoped production PHPStan and both changed files' syntax/Pint pass. No schema, provider, attempt, retry or worker change.
- Completion evidence: `tests/Contexts/Communications/Delivery/Integration/Concurrency/DigestMemberScopeTest.php`, local digest-scope-red.xml, digest-scope-green.xml and containing-digest.xml; recovered at bb479d6c and verified with real PostgreSQL 18.6 and one worker. Run the owner file, then `phpunit tests/Contexts/Communications/Delivery tests/Workflows/NotificationDelivery tests/System/Architecture --order-by=random --random-order-seed=102`. This is not the full containing normal milestone, so the item remains In progress.
- Commit SHA: `50780faaed7f7946e57a2bf840c056237d64a939`; containing normal milestone remains required.
- Final containing verification: Complete for this item at `20bf34c96532b56a091cd344eb47574c46220b46`, checkout `5e1230bff054c0cabad8790613199a966413fd59`. All nine normal workflows and 1,640 PHP cases /84,405 assertions pass with no failures/errors/skips; all62 browser cases pass without retries. CI34595577609, Visual34595577546 and artifact10261698764 supply the recorded evidence. This supersedes the earlier pending-milestone language above, not the still-open program.


### HARD-103 — King Perk source sweeps materialize complete manager audiences

- Area: Operations/KingPerks reminder queueing and GameWorld governance audience projections.
- Finding: The former action eagerly materialized all manager IDs/Players, bounded only successful sends and repeatedly selected fixed early source prefixes. Replays/denials could consume unbounded work; later sources could starve.
- Current owner: Operations/KingPerks orchestration using an unbounded Governance projection.
- Intended authoritative owner: Operations owns bounded due-source and durable per-source/kind traversal; Governance owns bounded effective-permission audience candidates. Communications remains notification/delivery authority.
- Architectural rationale: Finite live audiences must remain reachable after small budgets and restarts, without queue cursors becoming cached permission or source truth. Preserve per-recipient transaction ownership, current authorization and existing idempotency instead of adding a parallel broadcast framework.
- Required remediation: Replace the old all-ID projection with DISTINCT-before-LIMIT Player keysets. Select at most50 due sources and25 recipients/source; count denials/replays/empty sources in the clamped work budget. Add owner-private version/identity-fenced progress to the fresh canonical schema, bounded expiry and fair visit ordering. Atomically advance with real intent/outbox writes, reacquire current Kingdom/Player/role/source authority and actual per-recipient time. Update command diagnostics, owner CI, contracts and ADR-0048; remove the old unbounded API.
- State: Complete.
- Verification required: Large/multiple audiences and keysets, duplicate grants, deleted boundaries, revocation between selection/queue, live regrant, source fairness, restart continuation, two-connection stale-page fencing, replacement identity, atomic rollback, due/source changes, bounded pruning, exact command budgets, owner/static/architecture/fresh-schema and normal containing milestone gates.
- Verification result: Original targeted cases reproduced missing SQL LIMIT and later-source starvation. Eighteen implemented real-PostgreSQL cases pass95 assertions in20.966s. Tests cover53 recipients,25-row pages, application restart, budget1 fairness, effective authority filters, injected outbox rollback, another connection advancing the cursor, replacement identity, regrant and source rescheduling/deadline changes. The deadline regression first failed because the sweep timestamp was stale; writes now reread current time after locks. Final containing scope175/1,126 passes randomized seed103 in249.368s, zero failures/errors/skips. Architecture76/70,468 and full app/routes PHPStan passed; no existing scenario was removed.
- Completion evidence: KingPerkReminderTraversalTest, KingdomPermissionAudienceTest, local hard103-red, hard103-deadline-red, hard103-final-targeted XML/logs, hard103-containing, ADR-0048 and updated operations/owner guidance. The private cursor has no domain FKs deliberately: deletion cascades must not reverse its locking order, missing source identities never authorize a send, and bounded expiry reclaims orphan progress. No compatibility path, backfill or provider IO was introduced.
- Commit SHA: implementation `f025ee941b9062c4d40d7f9e7c9417edbb800594`; verified restart-test correction `28727fb2f57af48b83aa08fcd1b6459013479497`.

- Subsequent containing correction: CI34605678790 first failed the restart fixture under ParaTest because refreshApplication returned to the unsuffixed database. Commit28727fb2 preserves and asserts the actual worker target across a real application reboot; no production logic or original continuation assertion changed.
- Final verification: all nine normal workflows pass at28727fb2, checkout2a4e0064. CI34609537385 includes complete PHP, frontend and staging/recovery/image gates. Artifact10268895109 independently reconciles1,658 cases/84,680 assertions with no failures/errors/skips, including the restart test. This supersedes the earlier pending-milestone language for HARD-103 only; HARD-104 and the program remain In progress.

### HARD-104 — Bound announcement occurrence and recipient preparation

- Area: Alliance/Content one-off/recurring broadcasts, Membership audiences and management progress.
- Finding: A run limit concealed all-member ID/Player loading and one full-audience transaction. Work, memory and lock duration grew with the entire Alliance. One-off keys omitted revision. A simple merged due queue could still let historical recurrence backlog monopolize a one-source budget.
- Current owner: Alliance/Content intent/preparation, Membership candidates and Communications delivery.
- Intended authoritative owner: Same owners, with immutable occurrence identity and separately mutable bounded preparation. No provider authority, recipient receipt duplicate, compatibility layer or historical schema mode is added.
- Architectural rationale: Bound work before hydration and atomically checkpoint each current authorized recipient with its Communications intent. Keep source truth separate from progress, preserve fairness without audience-wide locks, and retain external reauthorization.
- Required remediation: Materialize due sources with revision/schedule-generation identity and finite membership upper keys. Persist source/run visit priority. Process at most 25 candidates per run visit under a global work budget, reacquiring current scope/membership/Governor/publication facts. Fence progress under a row lock; stale pages cannot advance twice. Distinguish suppression, skips and replays; finish with atomic audit/outbox. Cancel obsolete preparation without deleting history. Share current occurrence policy with external source authorization. Update fresh schema/indexes, scheduled command budgets and truthful UI progress. ADR-0049 supersedes ADR-0005's eager execution model.
- State: Complete.
- Verification required: Real PostgreSQL red/green multi-page/restart/global-budget/second-connection/revocation/deletion/generation/archival/suppression/intent-and-receipt-rollback/source-fairness tests; preserve existing notification/source assertions; actual browser Pending-to-complete presentation; production static analysis and normal containing milestone.
- Verification result: Reconstructed baseline eight cases reproduce seven failures and one control pass. Corrected 22 cases pass / 119 assertions in 73.679 seconds. The randomized Content/Communications/NotificationDelivery/System Architecture scope passes 225 / 48,009 in 147.901 seconds, seed104, no failures/errors/skips. Full app/routes PHPStan passes; complete frontend checks/build pass. Existing fixtures opt into notification and call the canonical coordinator instead of the removed eager API; authorization assertions remain. Browser fixture seed is executed in an independent database and creates actual Pending progress; both browser projects passed in the final containing milestone below. No elapsed-performance percentage is claimed.
- Completion evidence: `hard104-red.xml`, `hard104-targeted.xml`, `hard104-containing.xml`, full static/frontend logs and owner-local tests. Source inventory/suite guards and documentation links are checked. Current source reconstructs the saved prior attempt, including its historical-backlog fairness correction and distinct-name competing connection fixture. Normal CI artifacts and immutable implementation SHA are recorded below.
- Commit SHA: `74b84bc7b1a6bc58069fd34031abcb9487b992d2`.

- Final containing verification: all nine normal workflows pass at `74b84bc7b1a6bc58069fd34031abcb9487b992d2`, checkout `cb86cb0c27f3d1dd8eed7a0bb04e920e21d3a95d`; downloaded phpunit-results artifact10273830329 contains1,680 cases /84,980 assertions with no failures/errors/skips. Visual run34622391392 lists all64 cases passing, including desktop/mobile BroadcastProgress. Main CI34622391272 and all specialized/security/dependency gates passed. Two later preparation-only commits contain no runtime change and do not invalidate this milestone. HARD-106 outcome/pagination findings remain separate open work.
- Verified implementation SHA: `74b84bc7b1a6bc58069fd34031abcb9487b992d2`.

### HARD-105 — Adjacent occurrence labels bypass fixture timestamp normalization

- Area: System/Acceptance browser verification and its pure source contract.
- Finding: The current Events UI renders date and status in adjacent spans. Browser innerText joins AM/PM directly to Scheduled, so the normalizer's trailing word boundary fails and commits the wall-clock-derived fixture date into both rally fingerprints. The normal run at bd339861 passes the other 60 browser cases and all six other matrix surfaces.
- Current owner: System/Acceptance normalization.
- Intended authoritative owner: Same owner; bounded fixture normalization must preserve the actual status and all surrounding semantic content, not change production dates or freeze operational time globally.
- Rationale: A date-changing test fixture is not changed business behavior. Blind fingerprint regeneration or broad suffix removal would obscure real changes.
- Remediation: Recognize an adjacent current occurrence label (Scheduled, Completed or Cancelled) as a date boundary without consuming it. Keep all calendar/time checks. Add four regressions for changing dates, status distinctions, invalid dates and unknown/misspelled suffixes. Replace only the two rally hashes with the digest of the identically corrected full old/current text.
- State: Complete.
- Verification required: Red/green Node cases, exact prior/current captured-text comparison, lint/format, containing desktop/mobile matrix and normal full browser gate.
- Verification result: Three new status cases fail before repair. All fifteen Node source cases then pass, including the original eleven. ESLint and formatting pass. Artifact 10260746755 (normal failed run 34593116533) compared to reviewed artifact 10151284039: old digest 92eb5061... and current 127854eb... differ solely in Sep 12, 12:00 PMScheduled versus Sep 13, 11:00 AMScheduled. Applying the corrected normalizer yields identical complete texts, SHA-256 3093b4876646f4cc53555618f7d4f805ed9a5c51805b467ff7ebbe5fc5fd32c4. The other twelve hashes and all assertions/images are unchanged. That original red evidence is superseded by the containing milestones recorded below.
- Completion evidence: hard105-red.log, hard105-green.log and exact captured-text comparison, followed by all-nine normal milestones at20bf34c9 and28727fb2. Visual34609536868 includes this unchanged correction.
- Commit SHA: `20bf34c96532b56a091cd344eb47574c46220b46`, also contained by verified28727fb2.

### HARD-106 — Announcement management truncates outcomes and loads unbounded catalogues

- Area: AnnouncementBroadcastManagement, Content catalogue/history and Communications outcome composition.
- Finding: The original manager loaded all schedules, sampled 100 runs / 1,000 messages / 5,000 deliveries and presented sampled totals as complete. Content, categories, media and revisions had no complete bounded continuation. A forged retry metadata tuple could refer to a real run while naming another Alliance/Content pair.
- Current owner: Content owns its current authorized catalogue and occurrence facts; Communications owns retained delivery/read outcomes; AnnouncementBroadcastManagement composes these projections without becoming a write authority.
- Intended authoritative owner: These existing owners, with one ContentManagementQuery for manager-authorized keyset collections and the existing exact AnnouncementDeliverySummaryQuery for bounded actual run/Content scopes. The existing owner retry Action must enforce the full metadata tuple.
- Architectural rationale: Apply current tenant/Governor permission and SQL bounds before hydration. Keep complete live counts separate from a bounded page, and cursors separate from authority. Preserve complete history access and drafts without sampling outcomes, loading a tenant-wide workspace or persisting another derived counter.
- Required remediation: Retain the current ADR-0050 exact outcome query/DTO. Replace managerList and forAlliance with current-manager Content pages of 20 items, 25 category/media choices, 10 revisions and five runs. Resolve schedules only for visible subjects, use one scoped cursor/frontier per collection, load histories on demand, preserve selected off-page choices and unsaved drafts, and reject inconsistent Alliance/Content/run retry metadata. Correct canonical fresh indexes, migrate every caller/assertion and document ADR-0052; remove superseded implementations and duplicate proposed tests.
- State: In progress.
- Verification required: Above former outcome caps; all statuses/reads and deterministic retry limits; actual hydration/query bounds; full catalogue/option/history traversal, deleted boundaries, new insertion frontier, scopes and changed filters; internal and HTTP authority after demotion/membership loss/Kingdom archival; fresh indexes; draft/save reconciliation, aborted/failed history requests, off-page selection, all supported locales, desktop/mobile browser and final containing gates.
- Verification result: The earlier exact-outcome repair retains its large 6,025-route, complete 1,205-read, 375-retry-candidate/50-selected assertions. This continuation recovers the prior 55-path candidate but preserves the already-authoritative Communications implementation and ADR-0050. On current source the first ten pagination cases reproduce nine failures and a missing owner query. Corrected and expanded scope passes fifteen tests / 171 assertions in 3.931 seconds. It proves 21 Content models and 20 schedules loaded from 67 items, independent 25-row option cursors, actual deleted-boundary continuation, old runs reachable beyond 125 newer runs, live authorization, literal search and current schema indexes. The randomized containing scope passes 258 tests / 48,461 assertions in 110.387 seconds with seed 106 and zero failures/errors/skips. Full app/routes PHPStan and full frontend check/build pass; the final merged source contracts pass 67 cases including all current HARD-107/108 cases. No original outcome or preparation-counter assertion was removed; callers now inspect the separate authorized run-history response.
- Completion evidence: ContentManagementPaginationTest, ContentDrafts.test.ts, existing BroadcastDeliveryCompletenessTest and BroadcastDeliveryOwnerBoundaryTest, new browser/fixture pair, ADR-0052 and owner/product/reference/operations/codebase guidance. Local hard106-pagination-red.xml, hard106-expanded.xml, hard106-containing-expanded.xml and frontend/static logs record results. Existing case assertions and canonical delivery authority remain; counts are current observations, not a cross-request snapshot. No provider or background-processing behavior changed.
- Remaining work: The two new browser journeys are authored but not yet executed; their four desktop/mobile cases and the existing BroadcastProgress/outcome behavior need hosted verification. Full normal containing PHP/frontend/browser/security/schema/deployment gates still apply. The complete program, HARD-095 and HARD-107/108 are not closed by this slice.
- Commit SHA: `fc0f58a2305a4aa15302a2d310443441b05d72d9` is the earlier outcome projection. This bounded workspace is the coherent implementation following the recovered `2bf349ad5446e6d89fb9625004a93028f9e9a214`; the next checkpoint records its immutable commit SHA.

### HARD-107 — Oversized readiness screenshots omit populated lower regions

- Area: KingdomTransfers browser visual verification.
- Finding: In normal run34645079329 at6457d0a8, the desktop1440x18076 and mobile390x28587 full-page images contain large solid-background lower sections while the captured DOM still contains participant/evidence content. The existing aggregate pixel digest therefore does not reliably verify every rendered region. The two fingerprints also reflect intentional paging/history layout changes; neither fact authorizes blind regeneration.
- Current owner: KingdomTransfers browser specification.
- Intended owner: Same owner, with bounded independently reviewed region captures and explicit coverage of the complete intended readiness layout; no production masking, clipping or hidden content.
- Rationale: A matching hash of an incomplete raster is false visual confidence. The browser/compositor root cause is not yet established, so do not assert a particular hardware limit as fact.
- Remediation: Capture bounded meaningful regions, preserve original semantic/keyboard/filter/evidence/overflow assertions, assert coverage and dimensions, review actual desktop/mobile images, and only then record new fingerprints. Retain the existing twelve unrelated PNG baselines and retry/worker policy.
- State: In progress.
- Verification required: Pixel/DOM evidence comparison; new capture coverage checks; reviewed hosted region screenshots and containing full browser/normal gates. Do not label source-only checks as passing visuals.
- Verification result: Downloaded artifact10281818226 (ZIP digest fad0c8a52c818148ab867cc59740d85147aec013cdee2e2f21417462598f82ea) reconciles68 cases:66 pass, two existing readiness fingerprint failures. The new pagination and history journeys pass both viewports. Visual inspection and pixel-row inspection establish the lower solid-background regions, not their underlying cause.
- Completion evidence: current artifact/report/crop review, no completed fix yet.
- Commit SHA: finding recorded alongside the HARD-095 dashboard slice following6457d0a8.

- Current implementation: snapshot the shell viewport once and cover every main-content vertical pixel through contiguous viewport-fitting tiles, without changing viewport size, scale, DOM visibility or existing semantic assertions. Account for current sticky headers and actual scrolling; assert stable layout and exact bounded PNG dimensions. Reject a uniform-color raster rather than approving a blank lower page. Capture all tile images and a positional/hash manifest for review. The old two fingerprints are explicitly review-required until real hosted screenshots are inspected; this is a checkpoint, not a passing browser claim.
- New source verification: thirteen cases cover all five RGB/RGBA PNG row filters, clipped dimensions, invalid/empty/excessive capture bounds, complete no-gap/no-overlap tiling and flat-raster rejection. All35 current Node source tests pass, scoped ESLint/formatting pass. No new production dependency is introduced; the helper uses Node's built-in zlib only on bounded local Chromium PNGs, not a general image-upload parser.
- Publication checkpoint: follows c504a27d; exact reviewed baseline and containing gate remain pending. HARD-095/106 and the repository audit are not complete.
- Runtime continuation: hosted run `34651212749` at `4ba3ab87`, artifact `10283617928`, reproduces both projects failing the second tile bounds (desktop bottom1912 >1000; mobile1582 >844). The source CSS enables smooth scrolling, while the helper sampled after only two animation frames. Use explicit instant scrolling only for screenshot placement; do not change production CSS, viewport, masks, assertions or timeouts. All36 Node source tests and fast PHP212/73,203 pass locally on recovered exact source (PHP8.5.10, Node24.20.0). Expected visual fingerprints remain review-required until complete captures are inspected. No new passing browser result is claimed.

- Complete image review at `1a8a3b58`, run34652455100/artifact10284159021: shell plus20 desktop tiles and39 mobile tiles cover the exact main rectangles without overlap, clipping or uniform blank rasters. All existing semantic checks and new Yes/No checks pass; only expected placeholders fail. All tiles and shell images were inspected against captured DOM text. Review caught a test-induced duplicate Succeeded label: the old whole-leaf replacement overwrote the receipt span while its sibling already supplies the status. A pure owner-local helper now replaces only the single generated receipt ULID and rejects missing/ambiguous identities, preserving actual status/labels/spacing. Five new source cases plus all55 existing cases pass; scoped lint/format passes. No production rendering, observation/evidence state, assertion or accepted fingerprint changed. The corrected capture must be reviewed and repeated before completion.


- Reviewed baseline checkpoint: `2bf349ad`, run `34653637040`, artifact `10285031816` (ZIP SHA-256 `1e4505dff242561bf46c67a5019e0bf6d6cbee7c11b94fddc3f79cc8be1864df`). Both final comparisons are reached with all semantic/label/raster assertions passing. Full reviewed capture: shell plus 20 desktop and 39 mobile tiles, exact main rectangles 1144×17989 and 390×28344. Independent PNG/hash reconciliation proves complete coverage. Only one desktop tile changes from the prior full review; both shells and other 19 tiles are identical. All mobile tiles and changed receipt tile were inspected again. Current single Succeeded receipt and localized boolean facts are correct. Record full manifest hashes desktop `b1de7819934ebc8ffe4d90943f5960196d6ef60273ccf68074b6346d8fb23c35` / mobile `06d19d1ce2cab4094a7002bde3012e381e0b3e751b1f5155c21d4e3684d68d38` without altering assertions or pixel tolerances. Repeated no-retry and normal containing verification still apply.

- Repeatability continuation: run `34654411101` / artifact `10284608558` on `67590b5a`: three of four original project repetitions pass, no retries. Independent image comparison isolates the failed desktop digest to pixel(271,257) in tile05, RGB(11,17,17) versus(11,17,16); all other pixels/tiles/shells match. The installed Playwright screenshot expectation likewise waits for consecutive stable frames. Capture now requires two consecutive byte-identical current frames within six acquisitions, throws on continued instability and immediately propagates acquisition errors. It never sees the expected baseline, so stable changed content still fails the unchanged final comparison. Five new helper cases plus all60 existing Node cases pass; scoped lint/format passes. This is bounded paint stabilization, not a retry policy or a pixel tolerance. No existing hash or assertion changes.

- Raster profile experiment after `e51a1bc5`: complete viewport captures still differ across browser instances by one channel unit on a few background pixels, despite consecutive-frame stability. Test the Chromium tooling flag `--disable-partial-raster` only for this specification, preserving the installed browser, viewport, production CSS, current expected hashes, all semantic checks and zero pixel tolerance. GoogleChrome/chrome-launcher documents this rendering control. The existing no-retry repeated hosted job will determine whether it addresses damage-reuse nondeterminism; no passing result is assumed. Source/listing checks do not certify rendering.

### HARD-108 — Boolean observations expose missing shared localization keys

- Area: KingdomTransfers readiness and Transfer Evidence presentation, core localization.
- Finding: The actual readiness capture renders `Observed: common.yes · Required: common.yes`. Both boolean value formatters and the true/false input choices reference common.yes/common.no, but the canonical core catalogues do not define those keys.
- Current owner: Shared presentation labels used by KingdomTransfers and Intelligence/Evidence UI.
- Intended authoritative owner: The existing core message catalogue owns generic Yes/No labels in every supported locale; server-owned typed boolean facts and missing/unknown states remain unchanged.
- Rationale: Do not approve unresolved identifiers as a new visual baseline or couple common boolean labels to Recruitment/Progression dictionaries. No compatibility alias or alternate formatter is needed.
- Remediation: Add the two canonical shared labels in all17 supported locales. Add owner-local source regressions derived from the actual locale registry and both current consumers, plus actual-browser assertions for the rendered observed/required facts and absence of unresolved keys.
- State: In progress.
- Verification required: Reproduce missing keys, all-locale and actual-source contract tests, frontend type/lint/format, rendered desktop/mobile review and containing normal verification.
- Verification result: Nineteen new cases fail on the original core catalogues. All55 current Node source cases now pass (19 added, all36 original retained), zero skips/failures; scoped ESLint/formatting and Vue TypeScript pass. No PHP semantics, authorization, observation types, evidence schemas, locales, routes or retry settings change. Both hosted viewports in run34652455100 verify the actual observed/required Yes values and reject unresolved labels. HARD-107 raster baseline and normal containing verification remain pending.
- Completion evidence: hard108-red.log and hard108-green.log; actual raw text from readiness run34651212749/artifact10283617928. HARD-107 instant capture run34651886705/artifact10283689032 reaches both final comparisons with every bounded tile captured; placeholders remain until corrected labels are rendered and all tiles reviewed.
- Commit SHA: `1a8a3b5830860268c7a218c17a1b3712911fe875`; containing normal milestone remains required.

### HARD-109 — Outbound webhooks do not bind transport to an approved public destination

- Area: Platform/Integrations webhook endpoint policy and delivery transport.
- Finding: URL validation rejects literal private addresses but does not resolve hostnames; delivery follows redirects and does not bind the connection to the address approved immediately before transport. DNS rebinding or a redirect can therefore cross the intended egress boundary.
- Current owner: Platform/Integrations `WebhookEndpointPolicy` and `DeliverWebhook`.
- Intended authoritative owner: Same owner; no shared HTTP bypass or controller policy.
- Rationale: Configuration-time validation alone cannot authorize a later network destination. TLS must continue to authenticate the original hostname while transport connects only to a freshly approved public address.
- Remediation: Re-resolve at every attempt, reject empty/private/reserved/mixed answers, pin one approved address, retain certificate verification, restrict HTTPS and disable redirects. Preserve signing and public event contracts.
- State: In progress.
- Verification required: IPv4/IPv6 and mixed DNS answers, rebinding between configuration and delivery, redirects, no provider IO on denial, real HTTPS behavior, static analysis, security and containing gates.
- Verification result: prior containing `cb8c652c` is green but additional review found transport gaps. New policy/options tests pass locally (48 cases /61 assertions); changed Pint and Integrations PHPStan pass. Database-backed and actual HTTPS execution remain pending.
- Completion evidence: ADR-0053 and `WebhookOutboundHardeningV3Test`; do not mark Complete before executed containing evidence.
- Commit SHA: pending coherent publication.

### HARD-110 — Webhook retry recovery is unbounded and not fenced to an exact attempt

- Area: Platform/Integrations webhook queue claim, failed callback and stale recovery.
- Finding: a delayed job can claim Pending work before `available_at`; the queue failed callback updates by delivery ID without an attempt fence; stale recovery materializes every eligible delivery; concurrent sweeps can fan out duplicate jobs before claim.
- Current owner: `DeliverWebhook`, `DeliverWebhookJob` and `QueueDueWebhookDeliveries`.
- Intended authoritative owner: Same Integrations delivery owner with one persisted attempt identity and bounded scheduler reservation.
- Rationale: Queue job identity is not provider-attempt authority. Recovery must remain finite under backlog and stale completions must not rewrite newer state.
- Remediation: Persist exact queue reservation and provider-attempt UUIDs, due-time checks, a five-attempt durable budget, bounded skip-locked stale recovery and durable 25-subscription fan-out pages. Remove queue retry/failure state ownership. Manual retry grants five further attempts without resetting cumulative counts. Preserve stable delivery identity and at-least-once semantics.
- State: In progress.
- Verification required: early jobs, stale reservation jobs and provider responses, overlapping sweep selection, bounded large backlogs, queued/delivering interruption, retry exhaustion/manual retry, fresh schema/indexes and containing gates.
- Verification result: Source and owner regressions are authored; PostgreSQL concurrency and containing execution are pending.
- Completion evidence: ADR-0054 and `WebhookOutboundHardeningV3Test`; do not mark Complete before executed containing evidence.
- Commit SHA: pending coherent publication.

## Repository audit coverage

All rows below remain Planned until actual production paths have been traced. This table tracks audit scope, not discovered defects.

| Area | Required authority/scalability review | State |
| --- | --- | --- |
| Accounts | Identity/provider queries, authentication/credential owners, sessions, MFA, profile/email/reset and account-side deletion traced; repairs under HARD-018–024. Registration/invitation atomicity and deletion/finalization coordination verified under HARD-025–028; credential effects and verification delivery verified under HARD-029/030/035; reset, terminal guards, cleanup, throttling and login proof freshness remain under HARD-031–037 | In progress |
| GameWorld | Progression dataset/topology/prerequisite and Gift Code reminder paths traced (HARD-007/009/011/012); Governors, Kingdoms/transfers/governance, remaining Gift Codes/calculators and KingdomMaps audit remain | In progress |
| Alliance | Access authority/write-state, role lifecycle, direct/bulk role adapters, rank delegation and system-role consumers traced; defects HARD-045/046/047. Content preparation traced under HARD-104 and management projections under HARD-106; remaining lifecycle, membership/recruitment/content and consumers require audit | In progress |
| Operations | HARD-103 King Perk reminder audience/source traversal and fairness verified; Events, participation, rallies, results/Bear Hunt and remaining reminders still require full production audit | In progress |
| Intelligence | Evidence/Roster structured pipeline and all-family GameEvidence retention/redaction/summary queries verified under HARD-008/010/013–017; observations, other evidence families, ingestion, contributions and projections/signals remain | In progress |
| Communications | Immediate/digest claim, completion, routing and producer authority traced; HARD-098 implements current claim/fencing and exhausted recovery pending milestone verification; HARD-099/100/101 record source authorization, credential-generation diagnostics and obsolete reference guidance. Preferences, inbox, remaining transports and retention still require audit | In progress |
| Platform | DataGovernance account request/cancel/process traced with HARD-026–028 findings; administration, integrations/API credentials, webhooks, other retention and operational controls remain | In progress |
| Workflows/ReadModels | NotificationDelivery authority/mutations verified under HARD-005; progression prerequisite provenance under HARD-012; other orchestration, dashboards and Assistant/API projections remain | In progress |
| Infrastructure/entry points | Scheduler registration/commands verified by HARD-003; route authorization, shared mechanisms, queues/listeners/outbox and middleware audit remain | In progress |
| Frontend | Pages, components, composables/stores, server contracts, localization, receipts and accessibility | Planned |
| Schema/verification/operations/docs | Baseline gates, fresh schema, image/recovery and current repaired contracts verified; Evidence retention/query indexes and budgets under HARD-014/017; remaining capability indexes/operations/contracts audit remains | In progress |

## Execution adjustments

- After HARD-003, HARD-005 implements the ownership move but remains In progress until database-backed behavior passes. Local PHP/architecture/command checks are available; local PostgreSQL is not. HARD-006–008 are processed next because baseline formatter/architecture/static-analysis failures prevent CI from reaching those behavior tests. No gate is skipped or weakened; return to HARD-005 verification after these prerequisite repairs.

- Workspace interruption checkpoint: after local PHPStan/Pint and sorted middleware inspection, exec-server disconnected. Prepared remembered-session source/tests were reconstructed from authored text against exact remote parent f3c25909; the GitHub tree/commit is read back before the non-forced branch update. Browser-payload fixture correction was made from locked maintained package sources and CI failure evidence. No post-disconnection local gate or clean-checkout status is claimed. On resume, compare any retained scratch changes with the committed files before resetting or reapplying them.

- 2026-09-10 resumption: HARD-088/096 were stale pending entries, reconciled against the actual final all-nine-workflow milestone rather than reimplemented. HARD-095 is resumed with its smallest independently verified single-profile slice. HARD-097 is separately recorded and precedes the remaining workspace/aggregate work because those consumers must use one canonical eligibility composition. The complete production coverage table remains open.
- Final containing verification: Complete for this item at `20bf34c96532b56a091cd344eb47574c46220b46`, checkout `5e1230bff054c0cabad8790613199a966413fd59`. All nine normal workflows and 1,640 PHP cases /84,405 assertions pass with no failures/errors/skips; all62 browser cases pass without retries. CI34595577609, Visual34595577546 and artifact10261698764 supply the recorded evidence. This supersedes the earlier pending-milestone language above, not the still-open program.

#### HARD-107 capture checkpoint — actual overlay and work-budget correction

The first focused run 34649439808 at 86cc403f exposed two capture defects before any baseline was accepted. The desktop fixed top bar is a div rather than a header, so earlier tiles captured repeated overlay pixels. The mobile main edge rounds one pixel beneath the sticky bar. Inspect actual sticky/fixed overlays intersecting main; preserve the initial rounded prefix in the shell and tile every remaining pixel below the overlay. A new source case proves complete prefix/tile coverage and invalid-overlay rejection. All thirty-six Node source cases pass.

The desktop trial took 31.3 seconds for nineteen captures plus the existing interaction assertions; mobile requires thirty-nine bounded captures. Set only this complete-raster journey to a ninety-second cap instead of silently sampling pixels or dropping assertions. All other timeouts, worker counts and retry settings remain unchanged. Combine scroll, painting wait and viewport-offset retrieval in one browser call per tile. Original expected hashes remain review-required until corrected hosted captures are inspected. HARD-107 is still In progress; the first image set is not an accepted baseline.
