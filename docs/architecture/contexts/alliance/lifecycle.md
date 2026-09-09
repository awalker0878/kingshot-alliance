# Alliance — Lifecycle

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Lifecycle`

Lifecycle owns Alliance creation, lifecycle transitions and Alliance settings/profile state.

## Boundary

Lifecycle may consume neutral GameWorld identifiers/reference facts without duplicating GameWorld identity ownership. Platform `AllianceAdministration` may control platform-side lifecycle/entitlement concerns but does not become owner of in-game Alliance membership or authority.

Lifecycle writes use Alliance-owned Actions and current Player-scoped authorization where required.

AllianceSettingsInput is the shared immutable input contract for creation and settings updates. Both owners trim names, normalize URL names, enforce the 120-character storage bounds after normalization, reject reserved URLs, accept only SupportedAllianceLocale values and validate IANA timezones. Controllers retain shape validation; direct owner callers receive the same field errors. Updating to identical normalized values writes no audit/outbox transition.

CreateAlliance requires the authenticated account ID and locks current active account, active Kingdom and canonical Player ownership before establishing R5 membership. It rechecks placement after locking the Player. Account ownership changes and same-Player creation serialize through the account owner; competing membership activation is resolved by the active-membership constraint with savepoint recovery. Creation and settings updates translate exact competing URL claims into field validation while preserving the winner and enclosing transaction. Unrelated database failures propagate. See [ADR-0027](../../adr/0027-current-alliance-creation-authority-and-url-claims.md).

Platform plan/settings initialization is composed through InitializeAlliancePlatform using the scalar Alliance ID within the creation transaction. Lifecycle writes no Platform-owned tables directly; initialization failure rolls back the whole creation. See [ADR-0028](../../adr/0028-platform-owned-initialization-and-entitlement-facts.md).
