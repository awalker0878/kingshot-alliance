# Recruitment operations profile

[← Recruitment domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Recruitment  
**Code owner:** `app/Domain/Recruitment`  
**Primary operational boundary:** public/invitation-only intake plus private candidate lifecycle and scheduled expired-candidate anonymization

## 1. Operational purpose and runtime shape

Recruitment owns synchronous public/private application and recruiter workflows plus the daily `recruitment:purge-expired --limit=250` scheduler command that anonymizes eligible unsuccessful candidates. Accepted-candidate onboarding hands off to Memberships rather than creating membership directly.

## 2. Persistent state and ownership

Durable PostgreSQL state includes application settings/questions, invitation-access state, candidate/application identity/contact/answers, reviewer/pipeline metadata, retention eligibility and anonymization state. Invitation membership tokens remain owned by Memberships.

## 3. Configuration and runtime dependencies

Recruitment depends on PostgreSQL, scheduler continuity, tenant context for private recruiter work and shared rate limiting for public intake. Invitation-only access/mail delivery may depend on configured mail channels, but candidate data remains private PostgreSQL state.

## 4. Normal flow and background processing

Public or invitation-authorized users submit validated applications, creating Alliance-private candidate/application state. Recruiters progress private pipeline state. Daily at 03:15 the scheduler rechecks eligible past-due unsuccessful candidates under lock and anonymizes them through supported retention logic.

## 5. Health, observability and diagnostics

Inspect application mode, access status/expiry/use, candidate/application status, retention/anonymization timestamps, scheduler process/list, command errors and safe request/trace/audit correlation. Avoid copying full applicant answers into operational logs/tickets.

## 6. Failure modes and diagnosis

Typical failures are closed intake, expired/consumed access, email restriction mismatch, rate limit/validation failure, PostgreSQL failure, scheduler stoppage, candidate not yet retention-eligible, legal/process retention exception, or downstream Membership invitation problem after acceptance.

## 7. Recovery, replay and reconciliation

For intake failures, restore dependencies/config and let the applicant retry through the supported form/access flow. For retention backlog, restore scheduler/database and rerun the bounded purge command; eligibility is rechecked so already-anonymized/ineligible candidates are skipped. Do not manually null applicant fields to simulate anonymization.

## 8. Backup, restore, migration and rollback

Recruitment state is PostgreSQL-backed. After restore verify intake settings/questions, representative active/private applications, invitation-access lifecycle and anonymization eligibility. A database restore can reintroduce older personal data state, so retention reconciliation must occur before declaring privacy recovery complete.

## 9. Capacity, query and performance boundaries

Public intake is rate-limited and form/question counts are bounded by application rules. Purge defaults to 250 candidates. Larger privacy catch-up requires database capacity review. Repository fixtures are not production applicant-volume capacity claims.

## 10. External-service degradation

Mail/channel degradation can affect invitation-only access delivery or later Membership onboarding, while public intake may remain available according to mode. No third-party anti-abuse/CAPTCHA dependency is assumed unless separately implemented.

## 11. Safe operator actions and stop conditions

Safe actions are restore scheduler/database/mail, inspect safe candidate/access metadata, rerun bounded retention and use supported Recruitment/Membership flows. Stop if recovery would expose applicant answers, bypass access/email restrictions, directly create membership, anonymize a non-eligible candidate, or delete evidence required by policy/incident handling.

## 12. Evidence, focused runbooks and related documentation

Retain release SHA, safe candidate/application/access IDs, status/expiry/retention timestamps, command limit/counts, request/trace IDs and incident/change identifiers without unnecessary applicant content. See [Retention and anonymization](retention-and-anonymization.md), [background processing](../../../operations/background-processing.md), and the [Recruitment security profile](../security/README.md).
