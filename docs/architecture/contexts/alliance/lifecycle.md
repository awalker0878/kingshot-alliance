# Alliance — Lifecycle

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Lifecycle`

Lifecycle owns Alliance creation, lifecycle transitions and Alliance settings/profile state.

## Boundary

Lifecycle may consume neutral GameWorld identifiers/reference facts without duplicating GameWorld identity ownership. Platform `AllianceAdministration` may control platform-side lifecycle/entitlement concerns but does not become owner of in-game Alliance membership or authority.

Lifecycle writes use Alliance-owned Actions and current Player-scoped authorization where required.