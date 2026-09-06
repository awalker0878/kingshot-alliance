# Kingdom Governance Capability Expansion — Acceptance

Status: Implementation complete; quality-gate verification pending.

## KGE-01 Ownership and authority

- Governance remains the owner of Kingdom governance persistence.
- Active Player + concrete Kingdom is required for ordinary Governance authority.
- Platform Administrator status alone never satisfies `kingdom.roles.manage`.
- Protected mutations acquire and revalidate mutable authority in the owner transaction.
- Operations/Intelligence permission semantics remain with their defining contexts.

## KGE-02 Owner-aware permission reconciliation

- Permission rows identify a recognized owner namespace.
- An owner declares the complete desired permission set for each target Kingdom role.
- Missing owner permissions are attached and stale owner permissions are removed.
- Permissions owned by other contexts are preserved.
- Reconciliation is idempotent and rejects unknown/wrong-owner permissions and cross-Kingdom roles.

## KGE-03 Bootstrap, handoff and recovery

- Initial bootstrap is idempotent only for the same first administrator and cannot replace historical administration.
- Normal handoff is performed by an effective Kingdom Admin and cannot produce zero effective administrators.
- Break-glass recovery requires Platform Administrator authority, recent authentication, a target Player in the Kingdom and an explicit reason.
- Recovery produces audit/outbox evidence and does not grant the Platform account a Kingdom role.

## KGE-04 Effective authority

- Authorized administrators can inspect roles, permissions and permission owners.
- "Who has this authority?" reports only currently effective assignments and identifies granting roles.
- Read projections are observation-time facts; writes revalidate authority.

## KGE-05 History

- Governance history is Kingdom-scoped, bounded, cursor-paginated and authorization protected.
- Player, Platform and system actors are distinguishable.
- Relevant bootstrap/assignment/removal/handoff/recovery/delegation/expiry/reconciliation events are derived from durable audit evidence.

## KGE-06 Bulk administration

- Bulk role assignment/removal is limited to 50 Governors.
- Preview reports eligible/ineligible selections with reasons.
- Cross-Kingdom targets are ineligible.
- Final-admin protection applies to bulk removal.
- Commit reports applied/skipped results and executes ordinary owner-authorized mutations.

## KGE-07 Custom roles

- System roles are protected from custom edit/archive semantics.
- Custom roles are Kingdom-scoped with stable key, name, description and archive lifecycle.
- Permission selection is limited to recognized provisioned permissions held by the acting Player.
- Permission changes record added/removed permission impact and affected assignment counts.
- A role with effective assignments cannot be archived.

## KGE-08 Bounded delegation

- Assignments support effective time, expiry, reason, delegating Player and revocation evidence.
- Future assignments do not authorize early; expired/revoked assignments do not authorize.
- Authorization correctness does not depend on the expiry worker.
- Temporary administrator delegation is rejected unless another administrator survives the expiry.
- Expiry processing is bounded/idempotent and records audit/outbox lifecycle evidence.

## KGE-09 Health and drift

- Health detects no effective administrator, missing/archived system roles, Governance/Operations policy drift, unowned permissions, archived-role assignments and near-term administrator expiry risk.
- Health reads never silently mutate Governance state.
- Explicit reconciliation restores declared system-role policy without overwriting custom roles.

## KGE-10 Transfer integration

- Effective Governance assignments block Player Kingdom movement.
- Revoked/expired historical Governance assignments do not permanently block transfer.
- Transfer/Player capabilities consume Governance effective-authority queries rather than mutating Governance persistence.

## KGE-11 Frontend/security

- Governance mutations require recent authentication via existing `password.confirm` middleware.
- Platform recovery additionally requires `platform.admin`.
- UI exposes role search/filter, assignment state, authority, history, health, handoff, bulk operations and recovery.
- New Governance UI labels are available across all supported locales, with the complete detailed English fallback.

## KGE-12 Quality gates

Before merge, all applicable required checks must pass: PHP formatting/lint/static analysis, backend/V3 tests, architecture verification, frontend lint/typecheck/unit tests, production build, relevant browser tests and repository security/dependency checks.
