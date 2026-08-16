# Capability catalogue

Status: Current

This is the user/product view of implemented capability groups. Architectural ownership is linked where useful.

| Product capability | Outcome | Architectural owner |
| --- | --- | --- |
| Account security | Register, authenticate, verify email, manage profile/password/MFA/recovery. | Accounts |
| Player context | Own/claim Players and operate as one active game persona at a time. | GameWorld + PlayerContext workflow |
| Alliance management | Manage Alliance core/settings and tenant lifecycle. | Alliance |
| Membership and leadership | Membership, invitations, R1–R5 leadership and specialist roles. | Alliance |
| Recruitment | Intake/review/convert recruitment candidates through controlled membership handoff. | Alliance |
| Alliance content | Publish/manage Alliance-facing content and media. | Alliance |
| Kingdom governance | Manage Kingdom role/governance facts for Players. | GameWorld + workflows where cross-context |
| Events | Define/schedule recurring Events and occurrences. | Operations/EventCore |
| Participation | Registration, responses and attendance. | Operations/Participation |
| Event planning | Rosters, polls, battle objectives and assignments. | Operations |
| Rallies | Plan and coordinate rallies against Event occurrences. | Operations/Rallies |
| King Perks | Plan/schedule King Perk appointments and King Skills with occupancy/cooldown rules. | Operations/KingPerks |
| Results | Capture operational Event results and metrics. | Operations/Results |
| Intelligence | Ingest observations and maintain roster/contribution/event/diplomacy intelligence. | Intelligence |
| Shared intelligence | Control sharing/grants and compose Kingdom intelligence views. | Intelligence + ReadModels |
| Communications | Deliver reminders/notifications with preferences/retry/idempotency. | Communications |
| Platform administration | Cross-tenant admin, lifecycle/retention controls and Event-type administration. | Platform |
| Integrations | Scoped API credentials and signed/retryable webhooks. | Platform/Integrations |
| Dashboards/history | Compose cross-context user-facing views without changing source ownership. | ReadModels |

This catalogue should change when a real product outcome changes, not for internal class/file movement.