# Capability map

Status: Current — Architecture V3

Capabilities are first-class modules inside the seven bounded contexts. This map is the canonical business capability inventory for V3.

| Context | Capabilities |
| --- | --- |
| Accounts | Identity, Registration, Authentication, Credentials, EmailVerification, Profile, MultiFactorAuthentication |
| GameWorld | Players, Kingdoms, Governance, KingdomTransfers, GiftCodes |
| Alliance | Lifecycle, Membership, Access, Recruitment, Content |
| Operations | Access, Events, Participation, Polls, Rosters, BattlePlans, Rallies, KingPerks, Results |
| Intelligence | Access, Observations, Ingestion, Roster, Contributions, Diplomacy, Sharing |
| Communications | Delivery |
| Platform | Administration, AllianceAdministration, DataGovernance, EventAdministration, Integrations |

## Accounts

- **Identity** — User account identity and account-owned identity state.
- **Registration** — account creation.
- **Authentication** — sign-in, sign-out, session establishment and confirmation.
- **Credentials** — password change/reset and credential lifecycle.
- **EmailVerification** — verification state and verification flows.
- **Profile** — account profile changes.
- **MultiFactorAuthentication** — TOTP, MFA challenge and recovery mechanisms.

## GameWorld

- **Players** — Player identity/claim, Player ownership references and active Player selection.
- **Kingdoms** — Kingdom identity and neutral Kingdom/Alliance placement/reference state.
- **Governance** — Kingdom roles, assignments and GameWorld-owned governance authorization.
- **KingdomTransfers** — Player/Kingdom transfer planning and transfer-domain state owned by GameWorld.
- **GiftCodes** — normalized Gift Code catalogue, provider policy, and per-Player/per-Kingdom redemption state.

## Alliance

- **Lifecycle** — Alliance creation, lifecycle and settings.
- **Membership** — Player membership, invitations and R1–R5 leadership behavior.
- **Access** — Alliance permission vocabulary, specialist roles and Alliance authorization interpretation.
- **Recruitment** — applications, recruiting and review behavior.
- **Content** — Alliance-owned content and media.

Alliance policies belong to the capability that owns the rule; `Alliance/Policies` is not a V3 capability.

## Operations

- **Access** — Operations permission vocabulary and authorization interpretation.
- **Events** — Event identity, scheduling and occurrences.
- **Participation** — registration, attendance and reminder business policy associated with participation/event timing.
- **Polls** — Event polls and voting.
- **Rosters** — Event roster planning.
- **BattlePlans** — objectives and assignments.
- **Rallies** — rally coordination.
- **KingPerks** — Kingdom of Power appointment/skill planning and scheduling.
- **Results** — authoritative operational results and metrics.

## Intelligence

- **Access** — Intelligence permission vocabulary and authorization interpretation.
- **Observations** — durable observed facts and provenance.
- **Ingestion** — import and reconciliation of external observations.
- **Roster** — roster intelligence/history projections, not authoritative Alliance membership.
- **Contributions** — contribution facts, history and reporting.
- **Diplomacy** — diplomacy intelligence.
- **Sharing** — Intelligence grants and distribution.

## Communications

- **Delivery** — notification delivery coordination, encrypted recipient endpoints, inbox state/preferences, channels, provider acknowledgement, retry/failure handling and idempotency.

Communications does not own Event, King Perk or other source-domain reminder semantics.

## Platform

- **Administration** — Platform Administrator access and platform administrative behavior.
- **AllianceAdministration** — platform-side Alliance lifecycle, entitlement, feature and usage controls.
- **DataGovernance** — retention, legal hold, export and account deletion orchestration.
- **EventAdministration** — platform Event-type administration.
- **Integrations** — API credentials, webhooks and external integration administration.

## Not capabilities or contexts

The following are implementation/composition mechanisms, not business capabilities:

- Actions, Models, Queries, Services, Policies, Http and Events folders;
- PostgreSQL tables;
- routes and frontend pages;
- `app/Workflows`;
- `app/ReadModels`;
- `app/Shared`.

Cross-context analytical views such as Event analysis are composition surfaces under `app/ReadModels`, not Intelligence capabilities unless they acquire durable owner state of their own.

The capability map is documentation, not an executable hardcoded registry. Architecture tests enforce structural invariants instead of duplicating this list in production/test code.