# Recruitment application intake security review

[← Recruitment security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Recruitment  
**Capability:** Public and invitation-only application intake  
**Code owner:** `app/Domain/Recruitment`

## 1. Scope and security objective

Protect the externally reachable applicant intake boundary so public or invitation-authorized users can submit only the configured application data for one Alliance, cannot discover private recruiter pipeline state, and cannot turn application submission into membership/recruiter authority.

## 2. Assets and sensitive data

Assets include public application mode/copy/questions, invitation-only access record/status/expiry/email restriction, applicant identity/contact fields, submitted answers, resulting private candidate/application record, and historical question context.

Applicant identity/answers are personal private tenant data immediately after submission. Invitation-only access values are controlled bearer-like access material and must not appear in public logs/content.

## 3. Trust boundaries

- Anonymous browser → public application surface.
- Invitation-access holder → controlled invitation-only application surface.
- Public form/input → validation/rate-limit boundary.
- Accepted submission → private Alliance-owned Recruitment persistence.
- Recruiter configuration → public-safe intake fields/questions.
- Later accepted-candidate workflow → Memberships invitation contract; intake itself never grants membership.

## 4. Threats and controls

| Threat | Security impact | Current controls |
| --- | --- | --- |
| Closed application still accepts submissions | Unwanted data intake | Recruitment settings are authoritative and rechecked. |
| Public mode exposes private pipeline/reviewer state | Candidate privacy breach | Public queries expose only approved intake copy/questions; private state has no public route. |
| Invitation-only link replay | Repeated unauthorized submissions | Access record expires and is single use; consumption is state checked. |
| Email-restricted invite used by another applicant | Unauthorized intake | Normalized email restriction validated before successful submission. |
| Cross-Alliance invitation/candidate substitution | Tenant data corruption/leak | Access/candidate state is Alliance scoped and identifiers are not accepted across tenants. |
| Spam/automated submission | Resource/privacy abuse | Named rate limiting plus bounded active fields/questions/validation. |
| Later question edit changes historical answer meaning | Integrity/evidence loss | Submitted answers preserve original question context; later edits affect future rendering only. |
| Applicant submission grants membership | Authorization bypass | Submission creates private Recruitment state only; onboarding later uses Memberships invitation contract. |
| Full answers logged for diagnostics | PII disclosure | Telemetry avoids full answer/private payloads; safe IDs/status/correlation only. |
| Automated scoring hidden in intake | Unreviewed discrimination/decision automation | Explicit non-capability: no automated applicant quality scoring. |

## 5. Authorization, tenancy and privacy

Public intake intentionally does not require Alliance membership, but the target Alliance/configuration is explicit and all created candidate/application data is private to that Alliance. Private configuration and pipeline access require `recruitment.manage` under active tenant context.

Invitation-only access is not recruiter authorization. Applicant access never exposes notes, tags, reviewers, metrics, decisions, other candidates, or general tenant data.

## 6. Integrity, replay and concurrency

Single-use invitation access must allow at most one successful consumption. Repeated requests after consumption fail closed and must not create multiple independent successful applications from the same access record.

Validation failure does not consume/partially expose private pipeline state unless the supported transaction contract explicitly records a safe intermediate. Historical answers remain immutable evidence relative to later question configuration changes.

## 7. Secret and data lifecycle

Invitation-only access values are controlled access material and are not persisted/logged as public content. Applicant answers/identity are retained according to Recruitment retention policy; declined/withdrawn candidates later become eligible for scheduled anonymization.

Application intake does not own Membership invitation tokens, passwords, MFA, API, or webhook secrets. Applicants should not be asked to submit credentials/recovery secrets as answers.

## 8. Abuse limits and failure behavior

Closed mode, invalid/expired/consumed access, email mismatch, cross-tenant state, validation errors, and rate limits all fail without exposing private candidate/reviewer information.

Operators diagnose using mode, access status/expiry/use, question configuration, validation outcome and candidate creation identifiers—not raw full application answers.

## 9. Verification and evidence

Tests cover open/closed state, public versus invitation-only mode, active question rendering/required validation, invitation expiry/single-use/email restriction, repeated consumption, historical answer preservation, private pipeline non-exposure, Alliance isolation, and no membership creation from submission.

Shared policy: [Security baseline](../../../security/security-baseline.md). Historical source: [Phase 4 threat model](../../../security/phase-4-threat-model.md).

## 10. Residual risks and external controls

Public forms cannot prevent applicants from voluntarily placing unnecessary sensitive information into free text; question design/data minimization and recruiter process remain important controls. CAPTCHA or third-party anti-abuse service is not assumed by this contract unless implemented separately.

Repository controls cannot prove applicant device privacy, email-channel privacy for invitation links, or downstream handling by authorized recruiters after data is legitimately viewed.