# Cross-domain dependency map

[← Product and program documentation](README.md)

**Document type:** Current architecture map  
**Status:** Current  
**Phase owner:** `DCP-P6`

This document is the repository-wide supported collaboration map. Dependency notation is **consumer → owning contract**. It summarizes intentional business/runtime dependencies from the code-local domain maps and living contracts; it is not a generated import graph and does not transfer ownership between domains.

See [Architecture and program-governance standard](architecture-governance-standard.md), [ADR current architecture view](../adr/README.md), and [Domain boundary audit](domain-boundary-audit.md).

## System dependency shape

```text
                         Identity
                            |
              +-------------+--------------+
              |                            |
          Alliances ------------------ Memberships
              |  \                         |
              |   \                        |
              |    +---- Authorization ----+
              |              |
              |              v
              |            Audit
              |
              +---------------------------- Platform
              |                                 |
              |                                 +---- transactional outbox / lifecycle
              |
     +--------+--------+----------------------+-------------------+
     |                 |                      |                   |
   Content           Events               Recruitment          Kingdoms
     |               / | \                    |                   |
     |              /  |  \                   +-> Memberships     +-> Memberships
     |             v   v   v                                      +-> Integrations boundary
     +-> Recruitment Rallies Notifications
                           ^        |
                           |        v
                           +--- Contributions

Integrations consumes approved read/event contracts from owning domains and is
the externally observable machine boundary; internal outbox events do not become
public contracts automatically.
```

The diagram is directional guidance, not a prohibition on documented bidirectional workflow collaboration.

## Canonical domain dependency inventory

| Consumer domain | Supported owning dependencies | Architectural meaning |
| --- | --- | --- |
| **Alliances** | Identity, Memberships, Authorization, Platform, Audit, Kingdoms | Authenticated user + active membership establish tenant context; Platform lifecycle/defaults constrain Alliance state; Kingdoms owns the canonical Kingdom reference. |
| **Audit** | Identity, Alliances; bounded metadata from producer domains | Audit records attributable evidence but does not authorize or own the transition being recorded. |
| **Authorization** | Alliances, Memberships, Identity, Audit, Platform outbox | Permissions apply to active memberships inside an Alliance; Platform-admin grants remain separate. |
| **Content** | Alliances, Authorization, Recruitment, Audit, Platform outbox | Content owns authored/published state; Recruitment alone owns application availability. |
| **Contributions** | Events, Memberships, Notifications, Authorization, Audit, Platform outbox | Events owns attendance truth; Notifications coordinates due report requests; Contributions owns calculations/reports/exports. |
| **Events** | Alliances, Memberships, Authorization, Notifications, Rallies, Audit, Platform outbox | Events owns schedules/occurrences/registration/attendance; Notifications and Rallies own their specialized coordination state. |
| **Identity** | Platform | Identity is global; Platform legal-hold/account-deletion orchestration may constrain destructive account processing. |
| **Integrations** | Alliances, Events, Contributions, Platform, Authorization, Identity, explicitly eligible producer event contracts | Integrations owns API credentials/read boundary and webhook subscription/signing/delivery; producer domains own business payload semantics. |
| **Kingdoms** | Alliances, Memberships, Authorization, Identity, Audit, Platform, Integrations exposure boundary | Neutral Kingdom/player/game-Alliance references coexist with tenant-owned observations/workflows; Integrations currently exposes no Kingdoms public API/webhook contract. |
| **Memberships** | Identity, Alliances, Authorization, Platform, Audit, Platform outbox | Membership/invitation lifecycle establishes normal tenant access and consumes role/entitlement safety. |
| **Notifications** | Events, Contributions, Platform, Alliances, Memberships | Source domains own trigger facts; Notifications owns durable reminder/report-request coordination; Platform owns outbox infrastructure. |
| **Platform** | Identity, Memberships, Authorization, Audit, supported feature-domain projections | Platform owns cross-tenant lifecycle/admin/outbox infrastructure without absorbing feature-domain persistence. |
| **Rallies** | Events, Alliances, Memberships, Authorization, Audit, Platform outbox | Rallies owns Rally-specific coordination linked to Events-owned occurrence identity. |
| **Recruitment** | Alliances, Authorization, Memberships, Identity, Audit, Platform outbox | Recruitment owns candidate workflow and hands accepted candidates to a supported Memberships invitation contract. |

All 14 canonical code domains are represented exactly once as consumers.

## Foundational authority flows

### Identity → tenant access

Identity establishes a global authenticated User. It does **not** establish Alliance access. Normal Alliance access additionally requires:

1. Alliances-owned active tenant context;
2. Memberships-owned active membership; and
3. Authorization-owned permission evaluation for the requested action.

Platform-administrator authority is a distinct cross-tenant grant and is never inferred from Alliance roles.

### Alliance tenant identity

Alliances owns the platform tenant aggregate and active tenant context. Alliance-scoped domains consume tenant identity; they do not fabricate or silently infer it from unrelated rows.

The Alliance→Kingdom association references Kingdoms-owned neutral `Kingdom` identity. The association does not transfer ownership of Kingdoms roster/history/intelligence state to Alliances.

## Workflow collaboration clusters

### Events / Rallies / Notifications / Contributions

This cluster intentionally contains collaboration in more than one direction:

- Events owns schedules, occurrences, registration, waitlists, cancellation, and Event attendance.
- Rallies owns Rally guidance, formations, groups, assignments, and Rally participation linked to Event occurrences.
- Notifications owns durable Event reminder and scheduled Contribution-report due-time coordination.
- Contributions owns contribution facts/calculations/reports/exports and may reconcile from Events-owned attendance.

A collaboration cycle here is not permission for persistence reach-through. Each state transition still goes through the owning contract.

### Recruitment / Memberships / Content

- Recruitment owns recruitment availability and candidate/application state.
- Content may display Recruitment-owned availability but cannot persist a duplicate writable recruitment switch.
- Recruitment hands an accepted candidate to Memberships through the supported invitation/onboarding contract.
- Memberships remains the owner of membership and invitation persistence.

### Kingdoms / Alliances / Memberships / Integrations

- Kingdoms owns neutral game reference identities and Alliance-owned Kingdoms observations/workflows.
- Alliances owns platform Alliance state and its canonical `kingdom_id` association.
- Memberships owns application membership even when Kingdoms stores an optional same-Alliance membership reference.
- Integrations owns the external API/webhook boundary; current Kingdoms API scopes/routes and generic `kingdoms.*` webhook fan-out remain unapproved/unimplemented.

## Shared infrastructure boundaries

### Audit

Audit is evidence infrastructure. Owning domains decide when a successful transition requires audit evidence and supply bounded safe metadata. Audit records do not grant authorization and must not become a dumping ground for private notes or secrets.

### Platform

Platform owns cross-tenant administration/lifecycle/entitlements and transactional-outbox infrastructure. This shared ownership does not make Platform the owner of feature-domain business rows.

### Integrations

Integrations is the external machine boundary. A producer's internal outbox event becomes externally deliverable only through an explicitly approved Integrations contract. Existence in the outbox is insufficient.

## Forbidden dependency patterns

The following remain architecture defects unless explicitly changed by an accepted ADR/product scope:

- another domain mutating persistence it does not own instead of calling an owning action/service;
- duplicate writable ownership of one business fact;
- hidden global tenant inference replacing explicit active-Alliance context;
- role-name checks replacing Authorization's permission/effective-rank contract;
- feature domains implementing parallel generic transactional-outbox writers;
- Recruitment treating Memberships `Invitation` persistence as Recruitment-owned state;
- Content owning Recruitment availability;
- Contributions editing Events attendance truth;
- coordinator/contact/game identity implying application authorization;
- internal outbox event existence automatically approving public API/webhook exposure; or
- shared neutral Kingdoms reference identity granting access to another Alliance's tenant-owned state.

## Maintenance rule

Update this map when a domain's `app/Domain/<Domain>/README.md` dependency/public-contract section changes materially, a persistence/business owner moves, a new cross-domain contract is introduced, or an ADR changes system direction.

Do not update it for harmless internal class refactors that leave ownership/contracts unchanged.
