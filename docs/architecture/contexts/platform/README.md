# Platform context

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform`

Platform owns cross-tenant SaaS/application administration rather than in-game authority.

## Capabilities

```text
Platform/
├── Administration/
├── AllianceAdministration/
├── DataGovernance/
├── EventAdministration/
└── Integrations/
```

- **Administration** owns Platform Administrator grants/access and platform administrative behavior.
- **AllianceAdministration** owns platform-side Alliance lifecycle, entitlements, feature controls and usage administration.
- **DataGovernance** owns retention, legal hold, export and account-deletion orchestration.
- **EventAdministration** owns system-wide Event-type administration.
- **Integrations** owns API credentials, webhooks and external integration administration.

## Authority boundary

Platform Administrator is User-scoped Platform authority only. It does not confer Alliance membership, Kingdom governance authority or Operations/Intelligence game permissions.

## Infrastructure boundary

Generic runtime/readiness, metrics, security-header and other business-neutral mechanics belong in `app/Shared/Infrastructure` when they do not express Platform business policy.

Root `Platform/Actions`, `Platform/Models`, `Platform/Services` and `Platform/Http` buckets are not V3 modules; their classes belong under the capability that owns the behavior.