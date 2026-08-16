# Platform context

Status: Current  
Implementation: `app/Contexts/Platform`

## Purpose

Platform owns cross-tenant SaaS/application administration rather than in-game authority.

## Owns

- Platform Administrator grants/access;
- Alliance platform lifecycle/entitlement controls;
- feature/platform configuration orchestration;
- retention and account-deletion orchestration owned at the platform layer;
- Event-type administration over Operations configuration;
- API credentials and webhook administration/delivery coordination;
- hosted runtime validation and platform health/operational entry points.

## Authority boundary

Platform Administrator is User-scoped platform authority only. It does not confer Alliance membership, Kingdom role or Operations/Intelligence game permissions.

Generic audit/outbox infrastructure belongs to `app/Shared/Infrastructure`, not to the Platform business context.