# Platform context

Status: Current  
Implementation: `app/Contexts/Platform`

Platform owns cross-tenant SaaS/application administration rather than in-game authority.

## Capabilities

- [Administration and lifecycle](administration-and-lifecycle.md)
- [Event administration](event-administration.md)
- [API and webhook integrations](integrations.md)

## Authority boundary

Platform Administrator is User-scoped platform authority only. It does not confer Alliance membership, Kingdom role or Operations/Intelligence game permissions.

Generic audit/outbox infrastructure belongs to `app/Shared/Infrastructure`, not to the Platform business context.