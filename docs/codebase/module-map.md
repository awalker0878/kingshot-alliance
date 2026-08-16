# Module map

Status: Current

This map connects physical implementation to logical ownership. **The source tree does not define the business architecture; the architecture documents do.**

| Implementation | Architectural owner / role | Capability |
| --- | --- | --- |
| `app/Contexts/Accounts` | Accounts | Authentication, account/profile/security. |
| `app/Contexts/GameWorld` | GameWorld | Player/Kingdom identity, placement, Kingdom governance. |
| `app/Contexts/Alliance/Core` | Alliance | Alliance core/lifecycle/settings. |
| `app/Contexts/Alliance/Membership` | Alliance | Membership/invitations/lifecycle. |
| `app/Contexts/Alliance/Access` | Alliance | Alliance rank/role permission semantics. |
| `app/Contexts/Alliance/Recruitment` | Alliance | Recruitment. |
| `app/Contexts/Alliance/Content` | Alliance | Alliance content/media. |
| `app/Contexts/Operations/EventCore` | Operations | Event identity/scheduling/occurrences. |
| `app/Contexts/Operations/Participation` | Operations | Registration/attendance. |
| `app/Contexts/Operations/Polls` | Operations | Event polls/voting. |
| `app/Contexts/Operations/Rosters` | Operations | Event roster planning. |
| `app/Contexts/Operations/BattlePlans` | Operations | Objectives/assignments. |
| `app/Contexts/Operations/Results` | Operations | Operational results/metrics. |
| `app/Contexts/Operations/Rallies` | Operations | Rally coordination. |
| `app/Contexts/Operations/KingPerks` | Operations | King Perks planning/scheduling. |
| `app/Contexts/Operations/Reminders` | Operations | Reminder rules/scheduling policy. |
| `app/Contexts/Intelligence/Observations` | Intelligence | Observed game facts. |
| `app/Contexts/Intelligence/Ingestion` | Intelligence | Ingestion/reconciliation. |
| `app/Contexts/Intelligence/Roster` | Intelligence | Roster intelligence. |
| `app/Contexts/Intelligence/Contributions` | Intelligence | Contribution history/reporting. |
| `app/Contexts/Intelligence/EventAnalysis` | Intelligence | Event history/analysis. |
| `app/Contexts/Intelligence/Diplomacy` | Intelligence | Diplomacy analysis. |
| `app/Contexts/Intelligence/Sharing` | Intelligence | Shared intelligence/grants. |
| `app/Contexts/Communications/Reminders` | Communications | Reminder delivery state. |
| `app/Contexts/Platform/Access` | Platform | Platform administrator authority. |
| `app/Contexts/Platform/EventAdministration` | Platform | Event-type platform administration. |
| `app/Contexts/Platform/Integrations` | Platform | API credentials/webhooks. |
| `app/Contexts/Platform/Actions` | Platform | Lifecycle/retention/account/platform orchestration. |
| `app/Workflows/*` | Composition layer | Multi-context commands. |
| `app/ReadModels/*` | Composition layer | Cross-context reads. |
| `app/Shared/Infrastructure/AuditTrail` | Shared infrastructure | Audit mechanics. |
| `app/Shared/Infrastructure/Messaging/Outbox` | Shared infrastructure | Transactional messaging mechanics. |

Update this map when physical ownership materially changes. Internal class movement within one mapped capability normally does not require architecture changes.