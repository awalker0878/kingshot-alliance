# Kingdom Governance Capability Expansion

Status: Implemented on `governance-capability-expansion`; verification pending required repository quality gates.

## Outcome

Evolve Governance from fixed Kingdom roles and assignments into:

`Kingdom governance policy -> role administration -> bounded delegation -> effective-authority visibility -> history -> recovery -> health/drift assurance`

The core authority rule remains unchanged: **Kingdom game authority belongs to a Player in a concrete Kingdom. Platform administration can authorize a narrow recovery workflow, but it never becomes Kingdom game authority.**

## Product capabilities

1. **Exact owner-aware permission policy** — shared Kingdom roles carry recognized permissions with an explicit owner key. Governance and Operations reconcile only their own desired grants, preventing stale grants without cross-owner deletion.
2. **Administrator bootstrap, handoff and recovery** — first administrator bootstrap is historical-only; normal handoff is Player-authorized; Platform break-glass recovery requires Platform Administrator authority, recent authentication and a recorded reason.
3. **Effective-authority visibility** — authorized administrators can inspect roles, permission owner/context and Governors holding a selected effective permission.
4. **Governance audit history** — Kingdom-scoped bounded timeline built from durable audit evidence.
5. **Bulk role administration** — bounded to 50 Governors with preview, eligibility reasons and explicit result reporting.
6. **Custom roles** — Kingdom-scoped, stable-key custom roles with descriptions, recognized permission selection, protected system roles and archive lifecycle.
7. **Bounded delegation** — assignments support effective time, expiry, reason, delegating Player and revocation. Authorization evaluates effective time synchronously; the expiry scheduler records lifecycle evidence.
8. **Governance health** — detects missing administrators/system roles, owner policy drift, unowned permissions, archived-role assignment anomalies and administrator expiry risk; reads do not silently repair.
9. **Transfer safety** — effective Governance authority blocks Kingdom moves; revoked/expired historical assignments do not.

## Explicit exclusions

- Platform Administrator is not a Kingdom role and receives no game permission through recovery.
- Alliance rank/specialist-role authority is not owned or interpreted here.
- Operations and Intelligence retain semantic ownership of their permission vocabularies.
- Custom roles cannot invent arbitrary permission strings.
- No unsupported KingShot game mechanics are encoded.
- ReadModels do not own Governance business persistence.

## Ownership and coordination

- `GameWorld/Governance` owns roles, assignments, delegation state, governance permission interpretation and mutations.
- `Operations/Access` owns Operations permission definitions and declares desired Operations grants for default Kingdom roles.
- `Workflows/KingdomGovernance` coordinates multi-owner policy provisioning and break-glass recovery.
- `ReadModels/KingdomGovernance` composes authority/history/health projections only.
- `Platform/Administration` proves Platform Administrator authority and recent authentication before invoking recovery.

## User surfaces

- `/alliance/settings/kingdom/roles` — role assignments, search/filter, custom roles, handoff and bulk administration.
- `/alliance/settings/kingdom/governance/authority` — effective permissions and "who has this authority?".
- `/alliance/settings/kingdom/governance/history` — bounded Governance timeline.
- `/alliance/settings/kingdom/governance/health` — drift/health and explicit system-policy reconciliation.
- `/platform/kingdom-governance-recovery` — Platform break-glass recovery surface protected by `platform.admin` and recent authentication middleware.

## Operational lifecycle

`kingdom-governance:expire-delegations` runs hourly to close expired assignment lifecycle evidence. Authorization never depends on that scheduler: `effective_from`, `expires_at` and `revoked_at` are evaluated at authorization time.

## Verification

Acceptance requirements are defined in `kingdom-governance-expansion-acceptance.md`. Delivery evidence is tracked in `kingdom-governance-expansion-delivery-ledger.md` and must not be marked complete until CI/repository quality gates validate the branch.
