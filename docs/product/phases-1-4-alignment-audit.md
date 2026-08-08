# Phases 1–4 Integration Alignment Audit

**Audit date:** 2026-08-07  
**Scope:** Integrated implementation on `main` after acceptance of Phases 1–4  
**Reference:** `docs/product/IMPLEMENTATION_PLAN.md` plus all architecture decisions, security/definition-of-done standards, runbooks, phase exit reports, threat models, accessibility reviews, migration/rollback notes, operations guides, and user guides under `docs/`.

## Purpose

The individual phase exit reports are acceptance records for the state of the product when each phase closed. This audit checks the combined Phase 1–4 product for cross-phase drift after later domains were integrated. Where a later phase supersedes an earlier temporary ownership boundary, the integrated implementation must have one authoritative owner rather than compatibility shims or duplicated state.

No Phase 5 contribution/reporting capability is introduced by this alignment work.

## Integrated ownership model

### Phase 1 — Identity and multi-tenancy

Phase 1 remains authoritative for:

- global user identity, authentication, verification, MFA, password confirmation, and session controls;
- alliances and active-alliance context;
- memberships and fixed alliance roles;
- implemented permission vocabulary and authorization;
- membership invitations;
- audit attribution and the shared transactional-outbox foundation.

The integrated permission catalog contains only permissions required by implemented Phases 1–4. The previously pre-provisioned Phase 5 `contributions.manage` permission is removed. Phase 5 will add its permission when that product capability is implemented.

### Phase 2 — Content and public presence

Phase 2 remains authoritative for:

- public profile description and branding;
- public/member content, categories, revisions, publication, scheduling, search/filtering;
- media upload, screening, lifecycle, and branding attachment.

Recruitment availability is no longer stored in the Phase 2 `alliance_profiles` table. Phase 4 owns that state. The public alliance page composes the authoritative Phase 4 recruitment status through a Recruitment application query.

The temporary Phase 2 public “upcoming activities arrive in Phase 3” placeholder is removed after Phase 3 integration. Phase 3 event data remains member-scoped unless a future phase explicitly introduces a public-event visibility contract.

### Phase 3 — Events and rallies

Phase 3 remains authoritative for:

- event definitions, occurrences, registration, capacity/waitlist, reminders, attendance;
- member formations and configurable rally guidance;
- rally groups, assignments, participation facts;
- authenticated event exports and member event views.

Member self-service registration/cancellation and saved formations do not require password reconfirmation. Privileged coordinator mutations require recent password confirmation in addition to verified identity, active-alliance context, `events.manage`, tenant-safe object resolution, and audit attribution.

### Phase 4 — Recruitment

Phase 4 remains authoritative for:

- application mode/open state and application questions;
- public/invitation-only intake;
- candidate pipeline, reviewers, notes, tags, stage history, duplicates/merge;
- decision communication records;
- accepted-candidate conversion to Phase 1 membership invitation/onboarding;
- recruitment metrics and unsuccessful-candidate retention/anonymization.

Recruiter read surfaces remain available to authorized recruiters without forced reconfirmation. Privileged recruiter mutations require recent password confirmation in addition to verified identity, active-alliance context, `recruitment.manage`, tenant-safe object resolution, and audit attribution.

## Resolved integration findings

### 1. Future Phase 5 permission was provisioned early

**Finding:** Identity provisioned `contributions.manage`, and the Leader role received it, even though Phase 5 contribution/reporting functionality does not exist.

**Resolution:** Remove the permission from `PermissionKey` and the Leader template. Owner permission expansion now covers implemented permissions only.

**Reason:** The implementation plan explicitly prohibits partially introducing future-phase capabilities as placeholders.

### 2. Recruitment status had two writable sources of truth

**Finding:** Phase 2 stored `alliance_profiles.recruitment_status` while Phase 4 separately stored `recruitment_settings.application_mode` and `is_open`. The public page displayed the Phase 2 value but generated the application link from the Phase 4 value, allowing contradictory UI.

**Resolution:** Remove the Phase 2 recruitment-status enum, column, model field, content-management input, and content-profile mutation metadata. Add `PublicRecruitmentQuery` in the Recruitment application layer and derive the public status as:

- no settings or `is_open = false` → `closed`;
- open + invitation mode → `invitation_only`;
- open + public mode → `open` with the public application URL.

**Reason:** Later-domain state should be composed through its application boundary, not copied into another domain.

### 3. Phase 2 public event placeholder remained after Phase 3

**Finding:** The public alliance page still rendered text saying event schedules would arrive in Phase 3, even though Phase 3 was already accepted.

**Resolution:** Remove the placeholder props and sidebar. Do not expose Phase 3 events publicly because Phase 3 has no public-event visibility contract. Authenticated alliance/member pages remain the event surface.

### 4. Phase 3/4 privileged mutations did not use the common confirmation boundary

**Finding:** Phase 1 identity and Phase 2 content privileged mutations were protected by recent password confirmation, but Phase 3 coordinator and Phase 4 recruiter mutation routes were not.

**Resolution:** Protect coordinator and recruiter mutation routes with `password.confirm`. Keep read-only management pages and member self-service event actions outside that middleware. Add HTTP regression tests for both the required confirmation and the existing tenant-isolation behavior behind a confirmed session.

**Reason:** `docs/security/SECURITY_BASELINE.md` requires privileged changes to have authorization, confirmation, and audit. The integrated product should enforce that rule consistently across implemented domains.

## Deliberate non-changes

- Fixed role templates remain exactly the Phase 1 role vocabulary: Owner, Leader, Officer, Member, Recruiter, Event Coordinator, and Content Manager.
- Core tenant models (`Alliance`, membership/user context) remain shared foundations used by later phases; this audit does not replace the accepted tenant-boundary architecture with a second abstraction layer.
- Phase 3 participation facts remain event/rally operational data. Phase 5 scoring, weighted contribution rules, snapshots, reports, leaderboards, and contribution exports are not introduced.
- Phase 4 candidate conversion continues to use the Phase 1 invitation application action; no parallel membership system is introduced.
- Canonical pre-production migrations are cleaned directly instead of adding compatibility migrations/shims because the application has not been production-released.

## Regression expectations

The alignment branch must pass the repository's normal protected gates plus focused checks covering:

- fixed role/permission provisioning without `contributions.manage`;
- Phase 2 content profile/media behavior without a recruitment-status column;
- authoritative public recruitment status for closed/public/invitation modes;
- event coordinator mutations requiring recent password confirmation;
- recruitment mutations requiring recent password confirmation;
- member event registration/cancellation remaining usable without privileged confirmation;
- cross-alliance event and recruitment object isolation after confirmation;
- frontend lint/format/typecheck/build;
- PostgreSQL migrations and the full PHP test/static-analysis suite;
- staging deployment, backup/restore, image scan, CodeQL, and Dependency Review.

## Audit decision

After the changes above pass the protected gates, the integrated Phase 1–4 product is aligned with the documented architecture, security baseline, phase ownership boundaries, and the rule against partially implementing future phases. Phase 5 remains the next unimplemented product phase.
