# Phase 4 Exit Report

**Phase:** Recruitment  
**Status:** Accepted  
**Branch:** `agent/phase-4-recruitment`

## Objective

Replace informal recruitment tracking with a tenant-safe, auditable pipeline that takes a candidate from public/invitation-based application through review, decision, onboarding invitation, and documented retention/deletion without a separate spreadsheet.

## Delivered scope

- Public and invitation-only alliance application intake.
- Configurable recruitment questions with ordering, required/optional state, active/inactive state, question type/options, and editing of existing questions.
- Candidate records with source, contact identity, pipeline stage, timestamps, and next-action date.
- Pipeline stages: new, screening, contacted, interview, accepted, declined, withdrawn, and joined.
- Reviewer assignment, private recruiter notes, tags, stage history, and attributable decisions.
- Duplicate-candidate detection and controlled merge workflow.
- Decision templates and controlled candidate communication records.
- Accepted-candidate conversion into an alliance invitation plus onboarding checklist.
- Recruitment metrics covering source, response time, conversion, and stage aging.
- Retention/anonymization controls for unsuccessful applications.
- Recruitment workspace navigation from the alliance home surface for authorized recruiters.

## Scope boundaries

- Contribution scoring/reporting remains Phase 5-owned.
- Platform-wide administration remains Phase 6-owned.
- No automated game-data collection is introduced.
- Candidate decisions remain human-controlled; no AI-generated acceptance/decline decision is authoritative.
- No Phase 4 recruitment export surface was introduced, so export-specific exposure is not created by this phase.

## Verification evidence

### Functional, authorization, and tenancy

- `RecruitmentHttpTest` verifies public/private data separation, public submission, invitation-only token behavior, recruiter permission denial, active-alliance candidate isolation, tenant-scoped question editing, and recruiter navigation permission exposure.
- `RecruitmentCoordinationTest` covers the core recruitment lifecycle, stage controls, reviewer/note/tag behavior, duplicate merge, decision/onboarding behavior, metrics, and retention coordination.
- Recruitment question edits re-resolve the question under the active alliance before mutation and then use the existing `UpdateRecruitmentQuestion` application action.
- Recruiter navigation is permission-gated through `recruitment.manage` rather than exposed as an unconditional alliance-home action.
- Privileged recruitment changes use the established audit recorder and transactional outbox.

### Privacy and retention

- `docs/PHASE_4_THREAT_MODEL.md` documents candidate/reviewer-note privacy, public/private boundaries, invitation tokens, stage/decision authorization, merge integrity, retention, stored-content injection, and metrics isolation.
- Declined/withdrawn candidates receive `retention_due_at` based on alliance configuration.
- `recruitment:purge-expired` anonymizes due candidates transactionally, removes application answers/private notes/communications/reviewer-tag/onboarding rows, strips identifying fields, and emits audit/outbox evidence.
- The retention command runs daily with single-server and overlap protection.

### Accessibility

- `RecruitmentAccessibilityGuardTest` applies the established source-level guard to Phase 4 recruitment pages.
- Public/recruiter flows use native form controls, labels, links/buttons, DOM-order keyboard navigation, textual status, responsive layouts, and no raw `v-html` rendering.
- Existing-question editing includes explicit labels/IDs for prompt, type, position, help text, and options plus native required/active checkboxes.
- `docs/PHASE_4_ACCESSIBILITY.md` documents automated coverage and staging/device smoke expectations.

### Migration and recovery

- Phase 4 recruitment schema is introduced by `2026_08_07_030000_create_recruitment_tables.php` and `2026_08_07_031000_add_recruitment_anonymization.php`.
- `RecruitmentMigrationRollbackTest` verifies the Phase 4 rollback/forward path.
- `docs/PHASE_4_MIGRATION_ROLLBACK.md` documents forward deployment, destructive rollback cautions, retention-specific constraints, and recovery strategy.
- The accepted technical-head CI built the immutable production image, deployed ephemeral staging, completed the backup/restore drill, and passed the production-image vulnerability scan.

### Operations and documentation

- `docs/PHASE_4_OPERATIONS.md` documents public/private runtime surfaces, scheduled retention, outbox behavior, health/alert implications, backup/recovery, deployment/rollback, and incident triage.
- `docs/RECRUITMENT.md` documents recruiter workflows, public application boundaries, question management, pipeline stages, duplicate handling, decisions/onboarding, metrics, retention, security rules, and troubleshooting.
- Temporary implementation/formatting workflows were removed before acceptance.

## Protected-workflow evidence

Final technical head before product acceptance: `27c6822593d7d54bddbc360dcea1a104ba5dadba`.

- CI run `31205805622`: **passed**
  - frontend lint/format/typecheck/production build: passed
  - PostgreSQL migrations: passed
  - Pint/PHPStan/full PHPUnit suite: passed
  - production image build: passed
  - ephemeral staging deployment/validation: passed
  - backup/restore recovery drill: passed
  - production-image vulnerability scan: passed
- CodeQL run `31205806726`: **passed**
- Dependency Review run `31205805866`: **passed**
- Pull-request review check found no unresolved review comments/threads.

The product-acceptance commit is documentation-only and must also remain green under the protected workflows before merge.

## Exit criteria

- [x] Recruiters can manage candidates from application through membership without a separate spreadsheet.
- [x] Every decision and status transition is attributable.
- [x] Candidate data follows documented retention and access rules.
- [x] Public application and private reviewer surfaces are tenant-safe and permission-safe.
- [x] Duplicate detection and merge behavior are deterministic and tested.
- [x] Accepted-candidate conversion produces a controlled alliance invitation/onboarding path.
- [x] Recruitment metrics are explainable and tenant-scoped.
- [x] Security review identifies no unresolved critical or high application-security finding.
- [x] Accessibility implementation and regression evidence meet the agreed Phase 4 standard.
- [x] Phase 4 migration forward/rollback behavior is tested and documented.
- [x] Operations and user/technical documentation are updated.
- [x] Staging deployment, backup/recovery, and vulnerability scanning pass on the accepted technical head.
- [x] Product owner accepted the Phase 4 outcome and authorized continuation on 2026-08-07.

## Acceptance decision

**Phase 4 — Recruitment: ACCEPTED.**

The product owner authorized continuation through the Phase 4 gate on 2026-08-07. PR #14 may be merged once this acceptance-record commit remains green under the protected workflows.
