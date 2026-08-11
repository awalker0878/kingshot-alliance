# Domain documentation coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P1` — Domain contract and code-ownership completeness  
**Inventory state:** Frozen — 100% required content implemented; protected validation pending

## 1. Purpose

This is the authoritative `DCP-P1` inventory. It records every canonical code domain, the code areas reviewed at module/category level, the material capability-document decision, and the phase completion state.

A domain is not complete merely because its root README exists. Required capability documents identified here are part of the P1 gate.

## 2. Capability decision key

- **Root** — the domain remains coherent in its canonical README; no additional P1 capability file is required.
- **Split** — one or more independently meaningful capability contracts are required by the domain-contract standard.
- **Existing split** — required capability documents already exist and remain the canonical deep contracts.

## 3. Frozen coverage inventory

| Domain | Code areas / material responsibilities reviewed | P1 capability decision | Required capability contracts | P1 status |
| --- | --- | --- | --- | --- |
| Alliances | Alliance aggregate, creation/settings actions, active-Alliance context service/middleware, tenant-context snapshot, first-party Alliance surfaces | Split | `tenant-context.md` | Candidate |
| Audit | Audit event model, recorder service, attributable tenant/actor evidence | Root | None | Candidate |
| Authorization | permission vocabulary, built-in roles/ranks, role assignment/removal, permission evaluation | Root | None | Candidate |
| Content | content/category/revision state, publication/scheduling, private media lifecycle, public/member presentation | Split | `media.md` | Candidate |
| Contributions | categories/records/calculation provenance, correction/reversal, Events reconciliation, quality/reporting/export/schedules | Split | `event-reconciliation.md` | Candidate |
| Events | Event/template/occurrence scheduling, recurrence, registration/capacity/waitlist, attendance, calendar/export boundary | Split | `registration-and-attendance.md` | Candidate |
| Identity | global User/authentication, verification/password/session assurance, TOTP MFA and recovery codes | Split | `mfa-and-recovery.md` | Candidate |
| Integrations | Alliance-bound API credentials/read API, webhook subscriptions/signing/delivery/retries | Split | `api.md`, `webhooks.md` | Candidate |
| Kingdoms | neutral Kingdom/player/game-Alliance identity, roster/snapshots/intelligence/import, transfer planning, Alliance intelligence/diplomacy | Existing split | `roster.md`, `snapshots.md`, `intelligence.md`, `csv-migration.md`, `transfer-planning.md`, `alliance-intelligence.md` | Candidate |
| Memberships | membership lifecycle, invitation bearer-token lifecycle, management/leave/Owner safety, Recruitment handoff | Split | `invitations.md` | Candidate |
| Notifications | Event reminder materialization/delivery state, scheduled Contribution-report due-time coordination | Split | `event-reminders.md`, `scheduled-report-coordination.md` | Candidate |
| Platform | platform-admin/lifecycle, plans/settings/flags, legal hold/deletion/retention, usage/export orchestration, shared outbox | Split | `lifecycle-and-retention.md`, `transactional-outbox.md` | Candidate |
| Rallies | guidance, saved/recommended formations, groups/assignments/standby, Rally participation linked to Event occurrences | Root | None | Candidate |
| Recruitment | public/invitation-only intake, questions, private candidate pipeline/decision/onboarding/retention | Split | `application-intake.md` | Candidate |

## 4. Why these splits are required

### Alliances — tenant context

`AllianceContext`, request middleware, session selection/revalidation, and `TenantContextSnapshot` are a security-critical cross-domain contract consumed by every tenant-scoped feature. This merits an independent contract even though Alliance creation/settings remain in the root.

### Content — media

Private storage, upload validation/screening, usable/archived media state, and branding attachment safety have a distinct security/storage lifecycle from authored content publication.

### Contributions — Event reconciliation

`event_attendance` reconciliation consumes Events-owned facts and has independent deterministic identity, reverse/restore behavior, correction semantics, and retry requirements.

### Events — registration and attendance

Registration/capacity/waitlisting/cancellation/promotion and attendance form a concurrency-sensitive lifecycle distinct from schedule/template/recurrence configuration.

### Identity — MFA and recovery

TOTP enrollment/challenge and one-time recovery codes are a distinct secret-bearing assurance capability with stronger handling requirements than ordinary authentication/verification.

### Integrations — API and webhooks

The read API and outbound webhooks are independently observable machine contracts with different credentials/scopes, transport, retry, signing, and compatibility behavior.

### Memberships — invitations

Invitation issue/revoke/resend/acceptance is an expiring bearer-token lifecycle with email binding and Recruitment handoff semantics distinct from ordinary membership status administration.

### Notifications — two source-domain coordinators

Event reminders and Contribution report requests are independently triggered workflows with different source facts and deterministic identities. They share scheduler/outbox infrastructure but not one business lifecycle.

### Platform — lifecycle/retention and outbox

Cross-tenant lifecycle/legal-hold/deletion/retention is independently high-risk state orchestration. The transactional outbox is shared infrastructure intentionally consumed by every feature domain and needs a stable contract independent of the platform console.

### Recruitment — application intake

Public/invitation-only intake, question rendering, single-use access, and applicant submission are a public/privacy boundary distinct from the private recruiter candidate pipeline.

## 5. Root-only decisions

### Audit

Audit has one coherent capability: record attributable evidence after the owning domain authorizes and accepts a transition. A separate file would merely duplicate the root.

### Authorization

Roles, permission vocabulary, effective rank, and role assignment form one coherent Alliance authorization capability and remain navigable in the root.

### Rallies

Guidance/formations/groups/assignments/participation are one Event-linked Rally coordination aggregate with a shared authorization/tenancy boundary; the root remains coherent at current complexity.

## 6. Existing Kingdoms split

Kingdoms already has the P1 depth required by this program:

- [Roster](../domains/kingdoms/roster.md)
- [Player snapshots](../domains/kingdoms/snapshots.md)
- [Roster intelligence](../domains/kingdoms/intelligence.md)
- [Controlled CSV migration](../domains/kingdoms/csv-migration.md)
- [Transfer planning](../domains/kingdoms/transfer-planning.md)
- [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md)

The code-local Kingdoms README now points at the domain-owned product/security/operations evidence, matching the repository ownership standard.

## 7. Later-phase inventory handoff

P1 requires each root/capability contract to state its applicable security, operations, interface, and testing boundaries at contract depth. Deeper normalization is intentionally owned by:

- `DCP-P2` — security/privacy/data protection;
- `DCP-P3` — operations/reliability/recovery;
- `DCP-P4` — interfaces/events/integrations; and
- `DCP-P5` — testing/evidence/traceability.

These later phases may add documents, but they may not be used to excuse a missing P1 ownership, lifecycle, invariant, persistence, tenancy, failure, or cross-domain contract.

## 8. P1 exit checklist

- [x] 14/14 code-local domain READMEs satisfy `domain-contract-standard.md`.
- [x] 14/14 canonical domain READMEs satisfy `domain-contract-standard.md`.
- [x] All 19 required material capability contracts exist and are complete (13 new + 6 existing Kingdoms contracts).
- [x] Kingdoms code-local evidence paths are corrected.
- [x] All domain roots index their required capability files.
- [x] P1 structural metadata/heading/inventory CI is active.
- [ ] Protected CI confirms local documentation links and P1 architecture rules on the exact candidate head.
- [ ] P1 exit validation/evidence is finalized after protected validation.

The phase remains **Candidate**, not Complete, until the final two protected-validation items pass.
