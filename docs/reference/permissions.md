# Permission reference

Status: Current

Permission semantics remain owned by the context/workflow that defines the vocabulary. This table is a lookup index, not a replacement for authorization services.

Provisioned permissions additionally carry an `owner_key`. That owner identity exists so shared Kingdom roles can reconcile one owner's grants exactly without deleting another owner's grants. It does not create a global authorization engine or transfer permission meaning to Governance.

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
| `gift_codes.coverage` | View explicitly delegated Alliance Gift Code coverage; R4/R5 rank alone does not grant this access. |

## GameWorld / Kingdom Governance

Source: `app/Contexts/GameWorld/Governance/Enums/KingdomPermission.php`

Permission owner: `game-world.governance`

| Key | Meaning |
| --- | --- |
| `kingdom.roles.manage` | Manage Kingdom roles, assignments, bounded delegations and governance policy for a concrete Kingdom. |

Holding this permission is always an active-Player, concrete-Kingdom fact. Platform Administrator status is not equivalent. Custom Kingdom roles may include recognized permissions only when the assigning actor can currently delegate them.

`GameWorld/KingdomMaps` exposes map truth through owner queries and does not grant a separate user-managed map permission merely to consume immutable dataset facts.

## GameWorld / Kingdom Transfers

Source: `app/Contexts/GameWorld/KingdomTransfers/Access/Enums/TransferPermission.php`

| Key | Meaning |
| --- | --- |
| `kingdom_transfer.view` | View the active Alliance's Kingdom Transfer plans, participants, readiness, sourced game facts, observations, and server-authoritative eligibility assessments. |
| `kingdom_transfer.manage` | Manage the active Alliance's Transfer Windows, official Transfer Group observations, target conditions, participant observations, planning cohorts, readiness, blockers, and outcomes. |

Transfer permissions are interpreted against the active Player and concrete Alliance-owned transfer scope. Possessing a transfer permission in one Alliance does not authorize another Alliance's Transfer Window, plan, participant, observation, blocker or cohort. Mutating HTTP routes additionally require password confirmation and reauthorize concrete owner-scoped records at commit time.

A Player can change Kingdom only after effective Governance assignments are revoked/expired. Historical revoked or expired Governance assignments do not permanently block transfer.

## Operations

Source: `app/Contexts/Operations/Access/Enums/OperationsPermission.php`

Permission owner: `operations`

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

The permission family encodes action and scope. Operations interprets these permissions using current Player/scope facts. Operations may provision its recognized permissions onto Governance-owned Kingdom roles through exact owner-scoped reconciliation; Governance does not interpret their meaning.

## Intelligence

Source: `app/Contexts/Intelligence/Access/Enums/IntelligencePermission.php`

| Key | Meaning |
| --- | --- |
| `intelligence.view` | View Intelligence for the active Player/Alliance context. |
| `contributions.manage` | Manage contribution records/reporting/exports/schedules. |
| `kingdoms.manage` | Manage observed Kingdom/roster/transfer/intelligence state owned by Intelligence. |

## Platform

Platform administration is based on an active Platform Administrator grant plus account-assurance requirements rather than a game permission family. The Kingdom Administrator recovery workflow is a narrow repair process: Platform authority can repair a Player's Kingdom administrator assignment but does not receive `kingdom.roles.manage` or any Operations permission.

## Rule

Never grant a User a game permission merely because the same User owns another privileged Player. Use the active Player and concrete scope.
