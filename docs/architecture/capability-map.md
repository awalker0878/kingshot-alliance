# Capability map

Status: Current — Architecture V3

Capabilities are first-class modules inside the seven bounded contexts. This map is the canonical business capability inventory for V3. New delivery adds capabilities inside those contexts; it does not create an eighth context.

| Context | Capabilities |
| --- | --- |
| Accounts | Identity, Registration, Authentication, Credentials, EmailVerification, Profile, MultiFactorAuthentication |
| GameWorld | Players, Kingdoms, KingdomMaps, Progression, Governance, KingdomTransfers, GiftCodes |
| Alliance | Lifecycle, Membership, Access, Recruitment, Content |
| Operations | Access, Events, Participation, Polls, Rosters, BattlePlans, Rallies, KingPerks, Results, TerritoryPlanning |
| Intelligence | Access, Observations, Ingestion, Evidence, Roster, Contributions, Diplomacy, Sharing |
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
- **KingdomMaps** — immutable/versioned Kingdom-map datasets, coordinate/geometry facts, provenance and sourced game placement rules.
- **Progression** — immutable/versioned KingShot progression catalogue releases, source registry, reconciliation/conflict metadata, factual Hero/gear/building/research/Pet/Master/system reference data and source-scoped community formation conventions.
- **Governance** — Kingdom roles, assignments and GameWorld-owned governance authorization.
- **KingdomTransfers** — Player/Kingdom transfer planning and transfer-domain state owned by GameWorld.
- **GiftCodes** — normalized Gift Code catalogue, provider policy, and per-Player/per-Kingdom redemption state.

`KingdomMaps` owns represented spatial world truth, not Alliance planning preferences or saved layouts. `Progression` owns game-reference catalogue truth, not a Governor's observed roster and not a saved tactical loadout.

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
- **Rallies** — rally coordination, including Governor-saved formation/loadout planning intent.
- **KingPerks** — Kingdom of Power appointment/skill planning and scheduling.
- **Results** — authoritative operational results and metrics, including accepted Bear Hunt battle-report ledgers and deterministic aggregate recomputation.
- **TerritoryPlanning** — mutable Alliance/Kingdom layouts, planned HQs/Banners/Governor cities/Bear Traps, planning preferences, deterministic coverage/march/layout analysis, generated hive arrangements and immutable published revisions.

`TerritoryPlanning` consumes `GameWorld/KingdomMaps` through explicit IDs/contracts. It does not own map truth. `Rallies` may reference immutable `GameWorld/Progression` Hero/formation identifiers for saved tactical intent without owning catalogue facts. `BattlePlans` remains objective/assignment state and may reference a published territory-plan revision without absorbing spatial persistence.

## Intelligence

- **Access** — Intelligence permission vocabulary and authorization interpretation.
- **Observations** — durable observed facts and provenance.
- **Ingestion** — import and reconciliation of external observations.
- **Evidence** — private uploaded game evidence, immutable classification/extraction provenance, field confidence, review/correction history, duplicate decisions, commit receipts and retention. It owns evidence of a fact, never the accepted foreign-domain fact itself.
- **Roster** — roster intelligence/history projections and append-only Governor progression observations. A normalized Hero observation may reference `GameWorld/Progression`, but that reference does not make catalogue truth an Intelligence write.
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

Cross-context analytical views such as Event analysis, Screenshot Intake workspaces, the Territory Command editor, Rally Roster Builder, Member Capability Profile, Transfer Campaign Workspace, Kingdom Intelligence Timeline, Alliance Command, Officer Briefs and Alliance Assistant are composition surfaces under `app/ReadModels` when they combine multiple owners; they do not become new persistence owners. `ReadModels/AllianceAssistant` composes exact authorized owner projections; its evidence/citations are response values rather than a new business truth store. Officer Brief delivery state remains Communications-owned, while the brief content/fingerprint is recomputable from owner facts.

The HTTP adapter that renders a cross-context composition surface lives with that read model; owner-context adapters must not import `app/ReadModels`.

The capability map is documentation, not an executable hardcoded registry. Architecture tests enforce structural invariants instead of duplicating this list in production/test code.
