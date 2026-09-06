# Frontend — Kingdom Governance

Status: Current implementation; verification pending quality gates.

## Surfaces

- `Kingdom/PositionPerks/Roles` — primary Kingdom Governance administration workspace.
- `Kingdom/Governance/Authority` — effective-authority and holders projection.
- `Kingdom/Governance/History` — bounded audit timeline.
- `Kingdom/Governance/Health` — health/drift projection and explicit reconciliation action.
- `Platform/GovernanceRecovery` — break-glass administrator recovery for recently authenticated Platform Administrators.

## Context isolation

Every Alliance-facing Governance surface derives Player/Kingdom scope from the server-side active Alliance context. Client-provided Kingdom IDs are not accepted as ordinary Governance authority. Mutation routes use `password.confirm`, and owner actions re-check current Player authority at commit time.

Platform recovery is deliberately separate from the Alliance/Kingdom interface. `platform.admin` and recent authentication authorize invocation of the recovery workflow; the Platform account does not receive game authority.

## UX rules

- Show Alliance rank/specialist roles and Kingdom roles as separate concepts.
- Show role permission owner/context so Governance does not appear to own Operations semantics.
- Show scheduled/effective/expired assignment state and expiry where present.
- Bulk mutations require a preview and are bounded to 50 Governors.
- Authority views are observation-time facts only.
- Health reads do not silently repair state; reconciliation is an explicit protected action.
- Custom roles never accept arbitrary permission strings from the UI.

Detailed labels use the Governance expansion localization overlay for all supported locales with complete English fallback text.
