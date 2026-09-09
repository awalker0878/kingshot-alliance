# Alliance — Lifecycle

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Lifecycle`

Lifecycle owns Alliance creation, lifecycle transitions and Alliance settings/profile state.

## Boundary

Lifecycle may consume neutral GameWorld identifiers/reference facts without duplicating GameWorld identity ownership. Platform `AllianceAdministration` may control platform-side lifecycle/entitlement concerns but does not become owner of in-game Alliance membership or authority.

Lifecycle writes use Alliance-owned Actions and current Player-scoped authorization where required.

AllianceSettingsInput is the shared immutable input contract for creation and settings updates. Both owners trim names, normalize URL names, enforce the 120-character storage bounds after normalization, reject reserved URLs, accept only SupportedAllianceLocale values and validate IANA timezones. Controllers retain shape validation; direct owner callers receive the same field errors. Updating to identical normalized values writes no audit/outbox transition.
