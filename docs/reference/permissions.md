# Permission reference

Status: Current

Permission semantics remain owned by the context/workflow that defines the vocabulary. This table is a lookup index, not a replacement for authorization services.

## Alliance

Source: `app/Contexts/Alliance/Access/Enums/AlliancePermission.php`

| Key | Meaning |
| --- | --- |
| `alliance.view` | View Alliance capability. |
| `alliance.manage` | Manage Alliance core behavior. |
| `membership.manage` | Manage membership. |
| `roles.manage` | Manage Alliance roles. |
| `invitations.manage` | Manage invitations. |
| `content.manage` | Manage Alliance content. |
| `recruitment.manage` | Manage recruitment. |

## GameWorld / Kingdom

Source: `app/Contexts/GameWorld/Governance/Enums/KingdomPermission.php`

| Key | Meaning |
| --- | --- |
| `kingdom.roles.manage` | Manage roles/role assignments for a concrete Kingdom. |

`GameWorld/KingdomMaps` currently exposes map truth through owner queries and does not grant a separate user-managed map permission merely to consume immutable dataset facts.

## GameWorld / Kingdom Transfers

Source: `app/Contexts/GameWorld/KingdomTransfers/Access/Enums/TransferPermission.php`

| Key | Meaning |
| --- | --- |
| `kingdom_transfer.view` | View the active Alliance's Kingdom Transfer plans, participants, readiness, sourced game facts, observations, and server-authoritative eligibility assessments. |
| `kingdom_transfer.manage` | Manage the active Alliance's Transfer Windows, official Transfer Group observations, target conditions, participant observations, planning cohorts, readiness, blockers, and outcomes. |

Transfer permissions are always interpreted against the active Player and concrete Alliance-owned transfer scope. Possessing a transfer permission in one Alliance does not authorize another Alliance's Transfer Window, plan, participant, observation, blocker, or cohort. Mutating HTTP routes additionally require password confirmation and reauthorize the concrete owner-scoped records at commit time.

## Operations

Source: `app/Contexts/Operations/Access/Enums/OperationsPermission.php`

| Key | Meaning |
| --- | --- |
| `events.player.view` | View permitted player-scoped Events. |
| `events.player.create` | Create permitted player-scoped Events. |
| `events.player.manage` | Manage permitted player-scoped Events. |
| `events.alliance.view` | View Alliance-scoped Events. |
| `events.alliance.create` | Create Alliance-scoped Events. |
| `events.alliance.manage` | Manage Alliance-scoped Events and Event operations. |
| `events.kingdom.view` | View permitted Kingdom-scoped Events. |
| `events.kingdom.create` | Create permitted Kingdom-scoped Events. |
| `events.kingdom.manage` | Manage permitted Kingdom-scoped Events. |
| `events.types.manage` | Manage Event type catalogue/capability configuration. |
| `territory.alliance.view` | View territory/hive plans available to the active Player in an Alliance scope. |
| `territory.alliance.manage` | Create/edit/publish/import/archive permitted Alliance-scoped territory/hive plans. |
| `territory.kingdom.view` | View permitted Kingdom-scoped multi-Alliance territory plans. |
| `territory.kingdom.manage` | Create/edit/publish/import/archive permitted Kingdom-scoped multi-Alliance territory plans. |

The permission family encodes both action and scope. Operations interprets these permissions using current Player/scope facts. Frontend flags control affordances only; every write is reauthorized at commit time.

## Intelligence

Source: `app/Contexts/Intelligence/Access/Enums/IntelligencePermission.php`

| Key | Meaning |
| --- | --- |
| `intelligence.view` | View Intelligence for the active Player/Alliance context. |
| `contributions.manage` | Manage contribution records/reporting/exports/schedules. |
| `kingdoms.manage` | Manage observed Kingdom/roster/transfer/intelligence state owned by Intelligence. |

## Platform

Platform administration is based on an active Platform Administrator grant plus account-assurance requirements rather than treating platform access as a game permission family.

## Rule

Never grant a User a game permission merely because the same User owns another privileged Player. Use active Player and concrete scope.
