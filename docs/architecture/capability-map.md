# Capability map

Status: Current

Capabilities live **inside** bounded contexts. The map below is architectural; physical implementation paths are documented separately in [Codebase module map](../codebase/module-map.md).

| Context | Current capabilities |
| --- | --- |
| Accounts | Registration, authentication, sessions, profile, email verification, password reset/change, MFA/TOTP and recovery. |
| GameWorld | Player identity/claim, Kingdom resolution, Alliance reference placement, active Player context, Kingdom roles and Kingdom mutation authority. |
| Alliance | Core Alliance lifecycle/settings, membership, R1–R5 leadership, specialist roles/permissions, recruitment, Alliance content/media and Alliance policies. |
| Operations | Event core/scheduling, participation, polls, rosters, battle plans, results, rallies, King Perks, reminder rules and Operations permission semantics. |
| Intelligence | Observations, ingestion/reconciliation, roster intelligence, contributions, Event analysis/history, diplomacy, sharing/grants and Intelligence access. |
| Communications | Reminder/notification delivery coordination, recipient preferences, channel behavior, retries and idempotency. |
| Platform | Platform administrator access, Alliance lifecycle/entitlement controls, retention/account deletion orchestration, Event-type administration, API credentials and webhooks. |

## What is not a bounded context

The following are architectural mechanisms, not additional business contexts:

- controllers, models, actions, queries and services;
- PostgreSQL tables;
- `app/ReadModels`;
- `app/Workflows`;
- `app/Shared`;
- routes or frontend pages;
- individual features such as King Perks, Rallies, Contributions or Recruitment.

A capability should become a peer context only when it has meaningfully different language, ownership/policy, consistency boundaries and independent evolution—not simply because it is large.