# Data ownership

Status: Current

A shared PostgreSQL database does not imply shared business ownership. Every writable fact has one logical owner.

| Data/fact | Owner |
| --- | --- |
| User account, credentials, MFA/profile state | Accounts |
| Player identity and claim relationship, Kingdom identity, current placement | GameWorld |
| Kingdom roles/governance assignments | GameWorld |
| Alliance lifecycle, settings, membership, rank, specialist roles | Alliance |
| Recruitment and Alliance-authored content | Alliance |
| Event schedule/occurrence and execution state | Operations |
| Participation, polls, event rosters, battle plans, rallies, operational results | Operations |
| King Perks plans/appointments/skills | Operations |
| Observations, contribution ledger, analytical Event history, diplomacy/sharing | Intelligence |
| Delivery attempts/preferences/channel state | Communications |
| Platform administrator grants, platform lifecycle controls, API/webhook administration | Platform |
| Audit trail and transactional outbox mechanics | Shared infrastructure |
| Cross-context UI/report projections | ReadModels; read-only, no source ownership |

## Operational versus analytical ownership

Operations owns operational Event state: scheduling, occurrences, execution, participation, planning and results captured as part of live coordination. Intelligence owns observations/analytical history: contribution evidence, event analysis, trends and other observational projections. Neither creates a second writable copy of the other's source facts.

## Historical identity

Historical facts should retain durable Player/Alliance/Kingdom/Event identifiers appropriate to the fact at the time it occurred. Later membership or placement changes must not silently rewrite history.

## Cross-context data access

A context may receive IDs, immutable snapshots, query results or explicit contracts from another owner. It must not mutate another context's tables through its models or duplicate a second writable source of truth.

Where a user-facing screen needs several owners, compose a ReadModel rather than weakening write ownership.