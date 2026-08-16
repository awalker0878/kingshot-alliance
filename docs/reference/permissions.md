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

## Operations

Source: `app/Contexts/Operations/Access/Enums/OperationsPermission.php`

| Key |
| --- |
| `events.player.view` |
| `events.player.create` |
| `events.player.manage` |
| `events.alliance.view` |
| `events.alliance.create` |
| `events.alliance.manage` |
| `events.kingdom.view` |
| `events.kingdom.create` |
| `events.kingdom.manage` |
| `events.types.manage` |

The permission family encodes both action and Event scope. Operations interprets these permissions using current Player/scope facts.

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