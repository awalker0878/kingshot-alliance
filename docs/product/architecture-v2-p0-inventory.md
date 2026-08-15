# Architecture V2 — P0 current-state inventory

**Phase:** ARCH-V2-P0  
**Baseline commit:** `d78a3371a2267982e9cdc278cb592228cfbb6a6a`  
**Rewrite branch:** `architecture-v2`  
**Purpose:** freeze the V1 application surface before replacement; this is not a compatibility specification.

## Current code-domain roots

The baseline contains 15 code roots under `app/Domain`:

1. `Alliances`
2. `Audit`
3. `Authorization`
4. `Content`
5. `Contributions`
6. `Events`
7. `Identity`
8. `Integrations`
9. `KingPerks`
10. `Kingdoms`
11. `Memberships`
12. `Notifications`
13. `Platform`
14. `Rallies`
15. `Recruitment`

The current canonical architecture documentation still declares 14 canonical domains and predates `KingPerks`, demonstrating that runtime/domain-document synchronization has already drifted.

## Current architecture characteristics

### Global permission catalogue

`App\Domain\Authorization\Enums\PermissionKey` centrally defines business permissions for Alliance, Membership, Content, Events, Kingdom roles, Recruitment, Contributions and Kingdoms.

This is an extension bottleneck: feature capability work must modify a global Authorization-owned business vocabulary even when the capability is otherwise independently owned.

### Proliferating authority services

Current authorization includes multiple specialized authority services, including:

- `AllianceMutationAuthority`
- `KingdomMutationAuthority`
- `PlayerMutationAuthority`
- `EventMutationAuthority`
- `EventCreationMutationAuthority`

V2 retains their useful transaction-time authority/locking semantics but removes the requirement for every feature to grow another authority wrapper.

### Cross-domain ORM graph coupling

The current `Kingdoms\Models\Player` directly exposes relationships into Memberships and Authorization and also Kingdom roster state:

- Alliance memberships;
- Kingdom role assignments; and
- roster entries.

This turns a supposedly neutral game identity into an upstream aggregate coupled to higher-level feature contexts. V2 removes such cross-context ORM navigation from foundation models.

### Large mixed-responsibility roots

`Kingdoms` currently combines at least these distinct concerns:

- neutral Kingdom/Player/game-Alliance identity;
- Alliance roster/history;
- player snapshots;
- intelligence;
- CSV migration;
- transfer planning;
- diplomacy/contact workflows;
- automated data ingestion/reconciliation/quarantine;
- directional shared intelligence, consent/grants/history; and
- retention/operational cleanup.

`Events` currently combines:

- Event type/capability catalogue;
- scope/target creation;
- scheduling/occurrences/recurrence;
- phases and polls;
- participation/registration/attendance;
- hierarchical rosters and assignments;
- battle objectives/plans;
- results and metrics;
- historical Player context;
- Event intelligence/trends/history/read models; and
- first-party Event presentation.

At the same time, `Rallies` and `KingPerks` are peer top-level domains even though both are operational capabilities attached to Event occurrences and consume Event scope/target/authorization.

This is the main structural reason for the V2 Contexts + capabilities model.

## Current route surface

Baseline route files are:

- `routes/account.php`
- `routes/api.php`
- `routes/console.php`
- `routes/contributions.php`
- `routes/event-history.php`
- `routes/integrations.php`
- `routes/king-perks.php`
- `routes/kingdoms.php`
- `routes/platform.php`
- `routes/web.php`

The rewrite may reorganize route files and names around V2 contexts/capabilities. Old route names are not compatibility requirements.

## Current persistence/state families

The fresh-schema rewrite must account for the following state families currently represented by migrations/models. The final V2 migration sequence may rename/reorganize these tables and constraints rather than preserving V1 migration history.

### Accounts / authentication

- users;
- sessions/authentication assurance;
- personal access/API token support where retained;
- MFA/recovery/account-security state.

### Game-world identity

- kingdoms;
- players;
- game-Alliance / KingdomAlliance references;
- current Player Kingdom placement.

### Alliance lifecycle

- alliances;
- Alliance memberships;
- R1-R5 rank state;
- invitations;
- specialist Alliance roles/assignments;
- recruitment settings/questions/candidates/answers/reviews;
- Alliance content/revisions/categories/media.

### Kingdom governance

- Kingdom roles;
- Kingdom role assignments;
- exact-Kingdom Player authority state.

### Operations / Events

- Event types/scopes/capabilities;
- events and occurrences;
- phases/polls/options/votes;
- registrations/responses/attendance;
- rosters/roster members;
- battle objectives/assignments;
- Event results and metric definitions/values;
- historical Event Player context;
- Rally formations/guidance/groups/assignments/participation;
- King Perk plans, requests, appointments, position blocks, King Skill plans and reminder delivery state.

### Intelligence / observations

- roster observations/history;
- Player snapshots;
- game-Alliance observations/intelligence;
- ingestion subscriptions/batches/candidates/source reconciliation/quarantine state;
- transfer plans/groups/participants/blockers;
- diplomacy/contact state;
- shared-intelligence invitations/agreements/grants/history/retention state;
- non-Event contributions, corrections/reversals, report definitions/runs/exports.

### Communications

- Event reminder rules/deliveries;
- King Perk reminder deliveries;
- scheduled report delivery/request coordination.

### Platform / shared infrastructure

- platform administrator grants;
- lifecycle/entitlement/settings/feature-flag state;
- legal hold/retention/export/usage orchestration state;
- audit events;
- transactional outbox/delivery infrastructure;
- external integration credentials/subscriptions/webhook delivery state.

## Current command/job/scheduler surface

The baseline includes scheduled/background behavior across multiple old domains, notably:

- Event reminder processing;
- Contribution scheduled-report coordination;
- King Perk reminders;
- Kingdom ingestion subscription/batch acquisition and reconciliation;
- Kingdom intelligence-sharing retention;
- Platform outbox publication and lifecycle/retention work;
- webhook/integration delivery and retry.

P7/P8/P9 must relocate these responsibilities without preserving old command classes as shims. Scheduler registration may change as long as the required business behavior remains represented and verified.

## Current frontend/read surface

V1 presentation is organized primarily under `resources/js/pages` by feature, with dedicated Event, Kingdoms, Contributions, Platform and KingPerks surfaces plus shared/dashboard pages.

The V2 rewrite will reorganize frontend modules by user workflow/context where useful, but it is not required to mirror PHP directory names exactly. Frontend imports of `App\Domain` concepts and old route contracts are rewritten directly rather than passed through compatibility wrappers.

Cross-context dashboards/history will be backed by explicit `ReadModels` rather than forcing every composite view into a write-owning context.

## Current architecture-test gap

`tests/Architecture/DomainBoundaryTest.php` currently protects only a small set of historical cases:

- Alliance does not own Content model relationships;
- Recruitment does not import Membership Invitation persistence;
- feature domains use the shared Platform outbox recorder instead of three named duplicate outbox writers.

The documentation/governance rules are therefore substantially more prescriptive than executable dependency enforcement. ARCH-V2-P1 will replace this with tests that enforce the actual V2 dependency direction and no cross-context foundation coupling.

## Old → V2 ownership map

| V1 root/capability | V2 owner |
| --- | --- |
| `Identity` | `Contexts/Accounts` |
| `Alliances` core/settings/context | `Contexts/Alliance/Core` |
| `Memberships` | `Contexts/Alliance/Membership` + `Leadership` + `Invitations` |
| Alliance specialist Authorization roles | `Contexts/Alliance/Roles` with Shared access engine |
| Kingdom roles/governance authorization | `Contexts/GameWorld/Governance` with Shared access engine |
| `Recruitment` | `Contexts/Alliance/Recruitment` |
| `Content` | `Contexts/Alliance/Content` |
| `Kingdoms` neutral Player/Kingdom/game-Alliance identity | `Contexts/GameWorld` |
| `Kingdoms` Player/Kingdom transfer primitives | `Contexts/GameWorld/Transfers` plus cross-context workflow where required |
| `Kingdoms` roster/snapshots/observations | `Contexts/Intelligence/Observations` |
| `Kingdoms` automated ingestion | `Contexts/Intelligence/Ingestion` |
| `Kingdoms` shared intelligence | `Contexts/Intelligence/Sharing` |
| `Kingdoms` diplomacy/intelligence contacts | `Contexts/Intelligence/Diplomacy` |
| `Events` core scheduling/scope/occurrence/phase | `Contexts/Operations/EventCore` |
| Events participation/attendance | `Contexts/Operations/Participation` |
| Events polls | `Contexts/Operations/Polls` |
| Events rosters | `Contexts/Operations/Rosters` |
| Events battle objectives/plans | `Contexts/Operations/BattlePlans` |
| `Rallies` | `Contexts/Operations/Rallies` |
| `KingPerks` | `Contexts/Operations/KingPerks` |
| Event results/metrics | `Contexts/Operations/Results` + `Metrics` |
| Event intelligence/history composite queries | `ReadModels` and `Contexts/Intelligence/Analytics` as appropriate |
| `Contributions` non-Event ledger | `Contexts/Intelligence/Contributions` |
| Contributions reports/exports/trends | `Contexts/Intelligence/Reporting` / `ReadModels` |
| `Notifications` | `Contexts/Communications` |
| `Integrations` | `Contexts/Platform/Integrations` |
| `Platform` business administration | `Contexts/Platform` |
| `Audit` | `Shared/Audit` |
| Platform transactional outbox | `Shared/Messaging` / infrastructure |
| cross-domain business orchestration currently embedded in feature domains | `Workflows` |

## Cross-context workflows identified for extraction

At minimum, V2 must model these as explicit orchestration rather than assigning accidental ownership to one context:

- Alliance onboarding from Recruitment → Membership/Player state;
- Alliance R5 leadership transfer;
- Player Kingdom transfer and incompatible Alliance/Kingdom-role cleanup;
- account deletion/retention/legal-hold orchestration;
- platform tenant export/lifecycle operations.

Additional workflows may be identified while moving code, but they must not become generic compatibility facades.

## V2 deletion ledger

These V1 structures are expected to disappear before completion:

- `app/Domain` in its entirety;
- central business `PermissionKey` enum;
- V1 mutation-authority class family once replaced;
- cross-domain Eloquent relationships on foundation identities;
- V1 route grouping that exists only because of old domain roots;
- old application migration history after clean-schema replacement;
- old canonical `docs/domains/*` living structure and its mandatory five-profile rule;
- architecture tests that encode only V1 noun-domain boundaries;
- feature-specific verification workflows that become redundant once whole-V2 verification is authoritative.

A V1 item is deleted in the same rewrite branch once its complete V2 replacement and rewritten call sites/tests exist. Nothing is retained as a runtime compatibility bridge.

## P0 exit criteria

ARCH-V2-P0 is complete when:

- [x] the rewrite branch is pinned to baseline `d78a3371a2267982e9cdc278cb592228cfbb6a6a`;
- [x] the V1 code-domain set and major state/workflow surfaces are inventoried;
- [x] structural drift/high-risk coupling is recorded;
- [x] the old→new ownership map is recorded;
- [x] the no-shim/no-compatibility/fresh-schema rules are explicit; and
- [x] the documentation-restructure requirement is part of the rewrite completion contract.

Next phase: **ARCH-V2-P1 — create the V2 skeleton and executable dependency enforcement.**