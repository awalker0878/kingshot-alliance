# Platform administration and lifecycle

Status: Current  
Context: Platform  
Implementation: `app/Contexts/Platform/Access`, `Actions`, `Models`, `Queries`, `Services`

Platform administration owns cross-tenant application controls such as Platform Administrator grants and implemented lifecycle/entitlement/retention/account orchestration.

## Authority

Platform Administrator is evaluated from the authenticated User and required account assurance. It is deliberately separate from active Player authority and cannot be used to perform Alliance/Kingdom/Event actions that the selected Player is not allowed to perform.

## Lifecycle boundary

Platform may suspend/control SaaS access, entitlement, retention/legal-hold or account lifecycle behavior that is genuinely cross-tenant/platform-owned. It does not become owner of Alliance membership, Player identity or Event aggregates simply because it can administratively orchestrate lifecycle actions.

Hosted runtime validation is a Platform operational entry point but the underlying PostgreSQL/Redis/outbox mechanisms remain technical infrastructure.