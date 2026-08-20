# Source layout

Status: Current — Architecture V3

The source tree is capability-first inside bounded contexts so the business architecture is visible directly from `app/Contexts`.

```text
app/
├── Contexts/
│   ├── Accounts/
│   │   ├── Identity/
│   │   ├── Registration/
│   │   ├── Authentication/
│   │   ├── Credentials/
│   │   ├── EmailVerification/
│   │   ├── Profile/
│   │   └── MultiFactorAuthentication/
│   │
│   ├── GameWorld/
│   │   ├── Players/
│   │   ├── Kingdoms/
│   │   ├── Governance/
│   │   └── KingdomTransfers/
│   │
│   ├── Alliance/
│   │   ├── Lifecycle/
│   │   ├── Membership/
│   │   ├── Access/
│   │   ├── Recruitment/
│   │   └── Content/
│   │
│   ├── Operations/
│   │   ├── Access/
│   │   ├── Events/
│   │   ├── Participation/
│   │   ├── Polls/
│   │   ├── Rosters/
│   │   ├── BattlePlans/
│   │   ├── Rallies/
│   │   ├── KingPerks/
│   │   └── Results/
│   │
│   ├── Intelligence/
│   │   ├── Access/
│   │   ├── Observations/
│   │   ├── Ingestion/
│   │   ├── Roster/
│   │   ├── Contributions/
│   │   ├── Diplomacy/
│   │   └── Sharing/
│   │
│   ├── Communications/
│   │   └── Delivery/
│   │
│   └── Platform/
│       ├── Administration/
│       ├── AllianceAdministration/
│       ├── DataGovernance/
│       ├── EventAdministration/
│       └── Integrations/
│
├── Workflows/
│   ├── AccountOnboarding/
│   └── KingdomGovernance/
│
├── ReadModels/
│
└── Shared/
    └── Infrastructure/
```

Other top-level application areas remain conventional Laravel/application structure:

```text
resources/js/       # Vue/Inertia frontend
routes/             # route registration only
database/           # migrations, factories and seeders
config/             # configuration
bootstrap/          # application/provider bootstrapping
tests/              # architecture, feature and unit tests
bin/                # operational helpers
.github/workflows/  # CI and verification
```

## Capability internals

A capability may contain the technical folders it needs, for example:

```text
<Capability>/
├── Actions/
├── Models/
├── Queries/
├── Services/
├── Policies/
├── Access/
├── Http/
└── Events/
```

There is no requirement that every capability contain every technical layer.

## Prohibited context-root technical buckets

Architecture V3 does not organize a bounded context primarily by framework/technical layer. These paths are prohibited:

```text
app/Contexts/<Context>/Actions
app/Contexts/<Context>/Models
app/Contexts/<Context>/Queries
app/Contexts/<Context>/Services
app/Contexts/<Context>/Policies
app/Contexts/<Context>/Http
```

Code belongs to the capability that owns its behavior.

## Workflows

Only genuine multi-context command processes belong in `app/Workflows`. V3 has three intended workflow packages:

- `AccountOnboarding`
- `ExternalEventParticipation`
- `KingdomGovernance`

Player activation/context belongs to `GameWorld/Players`. Kingdom transfers belong to `GameWorld/KingdomTransfers`.

## ReadModels

`app/ReadModels` composes reads across context boundaries. It performs no business writes.

## Shared

`app/Shared/Infrastructure` contains business-neutral infrastructure such as audit, messaging/outbox, runtime/health, security and metrics mechanics where appropriate. Shared does not import business contexts or encode game policy.
