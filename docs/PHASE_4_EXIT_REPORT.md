# Phase 4 Exit Report

**Phase:** Recruitment  
**Status:** Not accepted  
**Branch:** `agent/phase-4-recruitment`

## Objective

Replace informal recruitment tracking with a tenant-safe, auditable pipeline that takes a candidate from public/invitation-based application through review, decision, onboarding invitation, and documented retention/deletion without a separate spreadsheet.

## Planned scope

- Public or invitation-based application form per alliance.
- Configurable recruitment questions, ordering, required fields, and active/inactive state.
- Candidate records with source, contact identity, pipeline stage, timestamps, and next-action date.
- Pipeline stages: new, screening, contacted, interview, accepted, declined, withdrawn, and joined.
- Reviewer assignment, private reviewer notes, tags, stage history, and attributable decisions.
- Duplicate-candidate detection and controlled merge workflow.
- Decision templates and controlled candidate communications.
- Accepted-candidate conversion into an alliance invitation plus onboarding checklist.
- Recruitment metrics covering source, response time, conversion, and stage aging.
- Retention/deletion controls for unsuccessful applications.
- Tenant-safe recruitment exports where implemented.

## Scope boundaries

- Contribution scoring/reporting remains Phase 5-owned.
- Platform-wide administration remains Phase 6-owned.
- No automated game-data collection is introduced.
- Candidate decisions remain human-controlled; no AI-generated acceptance/decline decision is authoritative.

## Required verification

- Candidate/reviewer-note privacy and active-alliance isolation.
- Public-application versus internal-review authorization boundary.
- Status-transition authorization, validation, and audit attribution.
- Duplicate detection/merge integrity and idempotency.
- Accepted-candidate invitation/onboarding conversion safety.
- Retention/deletion behavior and audit implications.
- Safe export behavior if recruitment exports are exposed.
- Migration forward/rollback behavior.
- Accessibility of public application and recruiter pipeline flows.
- Scheduler/queue/outbox, health, metrics, alerts, backup/recovery, and operational impacts documented.

## Implementation checkpoint

Phase 4 has been initiated from accepted Phase 3 mainline. Recruitment implementation evidence will be recorded here as complete vertical slices pass the protected gates.

## Acceptance evidence

Pending final-head validation.

## Exit criteria

- [ ] Recruiters can manage candidates from application through membership without a separate spreadsheet.
- [ ] Every decision and status transition is attributable.
- [ ] Candidate data follows documented retention and access rules.
- [ ] Public application and private reviewer surfaces are tenant-safe and permission-safe.
- [ ] Duplicate detection and merge behavior are deterministic and tested.
- [ ] Accepted-candidate conversion produces a controlled alliance invitation/onboarding path.
- [ ] Recruitment metrics are explainable and tenant-scoped.
- [ ] Security review identifies no unresolved critical or high application-security finding.
- [ ] Accessibility implementation and regression evidence meet the agreed Phase 4 standard.
- [ ] Phase 4 migration forward/rollback behavior is tested and documented.
- [ ] Operations and user/technical documentation are updated.
- [ ] Staging deployment, backup/recovery, and vulnerability scanning pass on the accepted final head.
- [ ] Product owner explicitly accepts the Phase 4 outcome.

## Acceptance decision

**Phase 4 — Recruitment: NOT ACCEPTED.**
