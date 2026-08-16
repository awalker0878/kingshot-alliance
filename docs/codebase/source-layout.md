# Source layout

Status: Current

```text
app/
├── Contexts/       # write-owning business contexts
├── Workflows/      # cross-context command orchestration
├── ReadModels/     # read-only cross-context composition
└── Shared/         # business-neutral technical contracts/infrastructure

resources/js/       # Vue/Inertia frontend
routes/             # HTTP/API/console route registration
database/           # migrations, factories, seeders
config/             # Laravel and application configuration
bootstrap/          # application/provider bootstrapping
tests/              # architecture, feature and unit tests
bin/                # setup/check/deploy/backup/restore/rollback helpers
.github/workflows/  # CI and targeted verification workflows
```

## `app/Contexts`

Current top-level contexts:

```text
Accounts
GameWorld
Alliance
Operations
Intelligence
Communications
Platform
```

Within a context, capability folders may contain Actions, Models, Queries, Services, Access, HTTP adapters and value objects as appropriate. There is no requirement that every capability use every folder type.

## `app/Workflows`

Current workflow packages include Kingdom governance/transfer, Player context and registration orchestration. Workflows may coordinate context contracts but do not own the context aggregates.

## `app/ReadModels`

Contains cross-context projections such as Alliance dashboard, Event calendar/history/management and Kingdom intelligence/settings views. These are read-only composition boundaries.

## `app/Shared`

Contains small technical access/HTTP contracts, providers and infrastructure such as AuditTrail and Messaging/Outbox. Shared must stay business-neutral and must not import a business context.

## V1 hard cut

Architecture V2 does not use `app/Domain`. New V2 code must not reintroduce `App\Domain\*` imports or compatibility facades.