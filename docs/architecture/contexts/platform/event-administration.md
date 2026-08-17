# Platform — EventAdministration

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/EventAdministration`

EventAdministration owns cross-tenant/catalogue administration over Event-type configuration where the product needs a platform-level operator surface.

## Boundary

Operations remains owner of Event runtime semantics, scopes, occurrences, participation and `events.*` authorization.

Platform EventAdministration invokes supported Operations contracts when configuration must affect operational behavior; it does not reach into Operations persistence or redefine Event execution semantics.