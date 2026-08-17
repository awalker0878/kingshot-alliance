# Module map

Status: Current — Architecture V3

This map connects the V3 physical source tree to architectural ownership. Capability directories are the primary modules inside each bounded context.

| Implementation | Owner | Capability |
| --- | --- | --- |
| `app/Contexts/Accounts/Identity` | Accounts | User identity |
| `app/Contexts/Accounts/Registration` | Accounts | Registration |
| `app/Contexts/Accounts/Authentication` | Accounts | Authentication/session establishment |
| `app/Contexts/Accounts/Credentials` | Accounts | Password/credential lifecycle |
| `app/Contexts/Accounts/EmailVerification` | Accounts | Email verification |
| `app/Contexts/Accounts/Profile` | Accounts | Profile |
| `app/Contexts/Accounts/MultiFactorAuthentication` | Accounts | MFA/TOTP/recovery |
| `app/Contexts/GameWorld/Players` | GameWorld | Player identity/claim/active Player |
| `app/Contexts/GameWorld/Kingdoms` | GameWorld | Kingdom/reference placement |
| `app/Contexts/GameWorld/Governance` | GameWorld | Kingdom governance |
| `app/Contexts/GameWorld/KingdomTransfers` | GameWorld | Kingdom transfers |
| `app/Contexts/Alliance/Lifecycle` | Alliance | Alliance lifecycle/settings |
| `app/Contexts/Alliance/Membership` | Alliance | Membership/invitations/leadership |
| `app/Contexts/Alliance/Access` | Alliance | Roles/permissions/authorization |
| `app/Contexts/Alliance/Recruitment` | Alliance | Recruitment |
| `app/Contexts/Alliance/Content` | Alliance | Content/media |
| `app/Contexts/Operations/Access` | Operations | Operations authorization |
| `app/Contexts/Operations/Events` | Operations | Event identity/scheduling/occurrences |
| `app/Contexts/Operations/Participation` | Operations | Participation/attendance/reminder policy |
| `app/Contexts/Operations/Polls` | Operations | Polls/voting |
| `app/Contexts/Operations/Rosters` | Operations | Event roster planning |
| `app/Contexts/Operations/BattlePlans` | Operations | Objectives/assignments |
| `app/Contexts/Operations/Rallies` | Operations | Rally coordination |
| `app/Contexts/Operations/KingPerks` | Operations | King Perks planning/scheduling |
| `app/Contexts/Operations/Results` | Operations | Operational results |
| `app/Contexts/Intelligence/Access` | Intelligence | Intelligence authorization |
| `app/Contexts/Intelligence/Observations` | Intelligence | Observed facts/provenance |
| `app/Contexts/Intelligence/Ingestion` | Intelligence | Ingestion/reconciliation |
| `app/Contexts/Intelligence/Roster` | Intelligence | Roster intelligence/history |
| `app/Contexts/Intelligence/Contributions` | Intelligence | Contributions/history/reporting |
| `app/Contexts/Intelligence/EventAnalysis` | Intelligence | Event analysis/history |
| `app/Contexts/Intelligence/Diplomacy` | Intelligence | Diplomacy intelligence |
| `app/Contexts/Intelligence/Sharing` | Intelligence | Intelligence sharing/grants |
| `app/Contexts/Communications/Delivery` | Communications | Generic notification delivery |
| `app/Contexts/Platform/Administration` | Platform | Platform administration |
| `app/Contexts/Platform/AllianceAdministration` | Platform | Alliance platform lifecycle/entitlements/usage |
| `app/Contexts/Platform/DataGovernance` | Platform | Retention/legal hold/export/account deletion |
| `app/Contexts/Platform/EventAdministration` | Platform | Event-type administration |
| `app/Contexts/Platform/Integrations` | Platform | API credentials/webhooks/integrations |
| `app/Workflows/AccountOnboarding` | Composition | Cross-context onboarding command process |
| `app/Workflows/KingdomGovernance` | Composition | Cross-context Kingdom governance process |
| `app/ReadModels/*` | Composition | Read-only cross-context projections |
| `app/Shared/Infrastructure/*` | Shared | Business-neutral infrastructure |

## Structural rule

Context-root `Actions`, `Models`, `Queries`, `Services`, `Policies` and `Http` directories are not V3 modules. Their contents must be placed under the capability that owns the behavior.

This document maps intended V3 implementation. It does not retain aliases for removed module names.