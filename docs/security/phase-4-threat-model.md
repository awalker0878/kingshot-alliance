# Phase 4 Threat Model — Recruitment

## Scope

This review covers Phase 4 recruitment settings, public and invitation-only applications, configurable questions, candidate review, private notes, reviewer assignment, tags, stage changes, duplicate merge, decision communications, membership conversion/onboarding, metrics, and retention anonymization. It extends the accepted Phase 1 tenancy and authorization boundary.

## Protected assets

- candidate identity and contact information
- application answers and source information
- private recruiter notes, reviewer assignments, and tags
- stage history, next-action dates, decisions, and communications
- application invitation tokens and membership invitation linkage
- onboarding state
- recruitment metrics and retention state
- privileged audit attribution and outbox events

## Trust boundaries

1. Anonymous applicant browser to public recruitment routes.
2. Invitation token to invitation-only application access.
3. Authenticated recruiter browser to active-alliance context.
4. Active-alliance context to recruitment queries and actions.
5. Application transaction to PostgreSQL constraints and locks.
6. Scheduler to candidate-retention anonymization.
7. Transactional outbox to asynchronous side effects.

## Threats and controls

### Cross-alliance candidate or configuration access

**Threat:** A recruiter supplies candidate, question, reviewer, tag, template, communication, onboarding, or merge-target identifiers from another alliance.

**Controls:**

- private recruitment routes require authenticated, verified, active-alliance context;
- recruitment management requires `recruitment.manage`;
- controller and application actions re-resolve mutable objects under the active `alliance_id`;
- recruitment question edits query by both active alliance and question ID;
- same-alliance database relationships constrain recruitment records;
- feature tests cover cross-alliance candidate access and question-edit denial.

**Residual risk:** Any future recruitment endpoint using an unscoped model lookup is a release blocker.

### Public/private boundary failure

**Threat:** Public applicants can discover private candidate records, reviewer notes, metrics, or internal workflow state.

**Controls:**

- public routes expose only alliance application settings and active questions needed to submit;
- private candidate/reviewer data is never included in public Inertia props;
- tests assert private recruiter notes and application answers do not leak through the public application response;
- invitation-only mode fails closed without a valid token.

### Invitation token replay or disclosure

**Threat:** An invitation-only application link is guessed, reused, or used by an unintended applicant.

**Controls:**

- application links are high-entropy tokens handled by the recruitment token service;
- tokens expire and are single use;
- invitations can be email-restricted;
- an accepted submission consumes the invitation;
- invalid, expired, or already-used tokens return not found rather than revealing token state.

**Residual risk:** Operators must avoid copying invitation links into public channels.

### Unauthorized stage change or decision

**Threat:** A normal member or unauthorized recruiter changes candidate stage, records a decision, assigns reviewers, or converts a candidate.

**Controls:**

- privileged recruitment actions enforce `recruitment.manage`;
- significant transitions are audit-attributed and recorded to the recruitment outbox;
- stage history preserves transition provenance;
- candidate decisions remain human-controlled; Phase 4 contains no authoritative AI acceptance/decline path.

### Private reviewer-note disclosure

**Threat:** Candidate-facing or public surfaces expose recruiter-only notes.

**Controls:**

- notes live only on authenticated recruiter surfaces;
- public application responses omit notes and private candidate data;
- candidate data is scoped to the active alliance before notes are loaded.

### Duplicate merge corruption

**Threat:** A duplicate merge crosses tenants, loses authoritative history, or is repeated inconsistently.

**Controls:**

- source and target candidates must belong to the same active alliance;
- merge behavior is implemented as an application action rather than ad hoc controller updates;
- merge state is explicit through `merged_into_id` and merged candidates are excluded from the active pipeline;
- merge behavior is covered by Phase 4 coordination tests.

### Retention bypass or excessive retention

**Threat:** Declined/withdrawn candidates remain identifiable after the alliance retention period.

**Controls:**

- unsuccessful candidates receive `retention_due_at`;
- `recruitment:purge-expired` runs daily and is bounded/overlap-protected;
- expired candidates are anonymized transactionally;
- application answers, private notes, communications, reviewer/tag links, and onboarding rows are removed;
- identifying candidate fields are replaced or nulled while minimal audit history remains explainable;
- purge emits audit/outbox events.

**Residual risk:** Backup retention is governed by platform recovery policy and must not be treated as an active application lookup surface.

### Stored-content injection

**Threat:** Candidate names, answers, notes, templates, or recruitment configuration execute script in recruiter/public views.

**Controls:**

- Phase 4 Vue pages render authored text through normal interpolation rather than `v-html`;
- request validation bounds field types and sizes;
- accessibility/source guards reject raw `v-html` on Phase 4 pages.

### Metrics inference or leakage

**Threat:** Recruitment metrics disclose another alliance's candidate volume or decision outcomes.

**Controls:**

- metrics are queried under active alliance context;
- recruiter metrics remain on the private management surface;
- public application pages do not receive metrics props.

## Privacy considerations

Recruitment contains personal information that is more sensitive than ordinary alliance content. Logs and metrics should prefer stable identifiers over duplicated names/emails. Reviewer notes must be factual and operationally necessary. Retention settings should be no longer than the alliance requires for legitimate recruitment operations.

## Security gate

Phase 4 acceptance requires the PostgreSQL authorization/tenant-isolation suite, static analysis, frontend checks, CodeQL, dependency review/audits, immutable-image vulnerability scan, staging smoke validation, and backup/restore drill to pass on the accepted final head. No unresolved critical or high application-security exception is accepted.
