# FRONTEND-V3 Capability Map

This document defines the Governor-facing screen map from application capabilities. It includes the current Accounts Sign-In Methods, Communications Recipient Delivery, Territory & Hive Planner and Intelligence Change Detection delivery contracts so implementation cannot ship UI without its backing owner capabilities.

## Presentation rule

The frontend is organized by what a Governor or Alliance officer is doing. Backend context/read-model names stay behind Inertia.

```text
Governor Account
      ├── Account Security
      │   ├── Profile / verified account email
      │   ├── Sign-in methods (Password / Google / Passkeys)
      │   ├── MFA / recovery
      │   ├── Sessions
      │   └── Security Activity / account lifecycle
      │
      └── Active Governor
          │
          ├── Alliance Command
          │   ├── Alliance Hall
          │   ├── Alliance Settings / Specialist Roles
          │   ├── Governance History
          │   ├── Roster Screenshots / Reconciliation
          │   ├── Recruitment Hall
          │   ├── Noticeboard
          │   ├── Alliance Rules
          │   └── Alliance Connections
          │
          ├── Event Command
          │   ├── Event Calendar / Agenda
          │   ├── Responses / Registration / Attendance
          │   ├── Phases / Polls
          │   ├── Event Rosters / Teams / Legions / Substitutes
          │   ├── Rally Guidance / Formations / Rally Groups
          │   ├── Battle Objectives
          │   ├── King's Court (Kingdom of Power only)
          │   └── War Reports / Event History
          │
          ├── Territory Command
          │   ├── Alliance Hive Plans
          │   ├── Hive Builder
          │   ├── Layout Analysis / Compare
          │   ├── Multi-Alliance Kingdom Plans
          │   └── Revisions / Import / Export
          │
          ├── Intel Room
          │   ├── Alliance Roster
          │   ├── Alliance Strength
          │   ├── Scout History / CSV Import
          │   ├── Kingdom Alliances
          │   ├── Alliance Observations
          │   ├── Recent Intelligence Changes
          │   ├── Diplomacy / Contacts
          │   ├── Shared Kingdom Intelligence
          │   └── Glory Ledger
          │
          ├── Governor Utilities
          │   ├── Gift Codes
          │   └── Notification Center
          │       ├── Logical inbox / filters / archive
          │       ├── Delivery details
          │       ├── Delivery preferences / routing policy
          │       └── Named destinations / Web Push
          │
          └── Kingdom
              ├── Kingdom Roles
              └── Kingdom Transfer
```

## Account Security

Backed by Accounts and scoped to the permanent Kingshot Alliance User rather than any active Governor. The Security Center presents real credential state instead of a primary account type: Password may be added, changed or removed; Google may be explicitly connected or disconnected by stable provider subject; and Passkeys may be registered, listed, renamed and removed. Server-side policy prevents removal of the final usable sign-in method.

Sensitive mutations use one generic **Confirm it's you** boundary that may be satisfied by an allowed attached Password, Google or Passkey method. Provider email remains metadata and never silently replaces the verified Kingshot Alliance account email or links another User. User-verifying passkey authentication does not add a redundant TOTP prompt; Password and Google continue through TOTP when configured.

The same account surface owns profile/account-email presentation, MFA and TOTP recovery codes, privacy-conscious session inventory/revocation, Security Activity and account-lifecycle controls. Communications remains the outbound security-notification delivery owner, while Platform/DataGovernance remains the deletion-orchestration owner.

## Command Overview

Backed by current Governor membership/rank/specialist roles, Alliance notices/content, upcoming Events, permission-aware entry points and bounded Intelligence Change Detection composition. Territory Command may appear as an entry point only when the current Player has an eligible planning scope.

When the active Governor has a concrete active Alliance and applicable Intelligence view authority, Command Overview may show a compact **Recent intelligence changes** feed. The feed is informational and does not automatically increase the global action count. An authorized scoped feed may show its localized empty state when no signals exist. Before a concrete active Alliance scope exists, the feed is not rendered; unscoped state must not be presented as “no changes.”

Do not show unsupported donation totals, leaderboard rank, Alliance Gift Level or arbitrary Alliance power merely because the game has those concepts.

## Alliance Hall

Backed by Alliance Membership and Access: active memberships, R1–R5 rank, specialist-role assignments, invitations, membership status, bounded rank/role changes, leadership transfer and leaving the Alliance. Permission-aware links expose settings, role administration, factual governance history and roster reconciliation without moving their ownership into the Hall.

### Alliance Settings and Specialist Roles

Alliance Settings is backed by `Alliance/Lifecycle` for application-owned name, slug, language and timezone only. Kingdom association and Platform suspension/closure/retention/deletion are not officer settings. Specialist-role definition is backed by `Alliance/Access`: system roles are protected, custom role keys are stable, and an actor cannot delegate permissions they do not currently possess or use role administration for self-escalation. R1–R5 remains Membership-owned.

### Alliance roster screenshots and reconciliation

Roster screenshots are private `Intelligence/Evidence` artifacts. Officers review/correct every visible row and explicitly state whether the image represents a complete roster before commit. Accepted facts append exactly-once `Intelligence/Roster` observations. The reconciliation page is a read model comparing those observations with current Membership/Roster facts; it never adds, removes, promotes or demotes a member automatically. A partial screenshot cannot imply that a member left.

### Alliance governance history

Governance History is an officer-authorized `ReadModels/AllianceGovernance` view over existing owner audit facts. It supports bounded filtering/cursor navigation and owner-workflow links, but owns no domain truth and performs no writes.

## Recruitment Hall

Backed by Alliance Recruitment: modes/questions, Governor applications, stages, assigned reviewers, notes, tags, duplicate merge, decisions, invitation conversion, onboarding items and private Alliance-local re-entry controls. Re-entry restrictions are recruiter-private and never presented as a global blacklist.

## Noticeboard

Backed by Alliance Content: public profile, categories, member content, announcements, drafts/publishing, revisions/restore, media and public pages.

Published Alliance Notices may expose lightweight member Like/Dislike reactions on both cards and detail pages. Reactions show only Like/Dislike counts and the active Governor's selected state. They are informational only: the UI must not derive or expose scores, approval ratios, popularity, trending, recommendations, ranking, moderation signals or reaction-based ordering/pinning.

Reaction controls are available to eligible active members independently of Content publishing authority. A Governor does not gain create/edit/publish/archive/broadcast authority by reacting, and a Content manager can react only because they are also an eligible active member.

## Alliance Rules

Backed by Alliance Content as one canonical member-visible `alliance-rules` document, not as a special Notice and not as a separate Rules domain/store.

Every active Alliance member may read the first-class Rules surface. Only Governors with current Content-management authority see and may use the edit form. The surface covers empty, published, editable, submitting, validation and saved states; it remains localized, keyboard-operable and mobile-safe. Rules updates use the existing Content revision, audit and outbox contracts and do not notify/broadcast members merely because Rules changed.

## Alliance Connections

Backed by Platform Integrations but presented as an officer utility: Alliance access keys and event-dispatch subscriptions with revoke behavior.

## Event Command

Backed by Operations Events/Participation and the repository's KingShot event catalogue. Supported rooms remain Responses, Registration/Waitlist, Attendance, Phases, Polls, Rosters/Substitutes/Teams/Legions, Rally Guidance/Formations, Objectives, King's Court, Scoring and Results according to selected event capabilities.

### Bear Hunt Rally Command

Bear Hunt uses Responses, Registration, Attendance, Rally Guidance, Formations and Results. When a published Territory Plan revision is associated with the relevant operation, Rally Command may link to that immutable hive layout. Bear Hunt does not gain generic Battle objectives merely because territory planning exists.

### Swordland Showdown War Room

Swordland continues to expose its existing Polls, Phases, combatant/substitute rosters, Objectives, Attendance, Scoring and Results. Spatial planning is linked only when an explicit supported revision reference exists.

### King's Court — Kingdom of Power

King's Court remains the KingPerks appointment/King Skill workflow. Kingdom-wide Territory Command is a separate planning surface and is not presented as a royal appointment feature.

## Territory Command

Backed by `GameWorld/KingdomMaps`, `Operations/TerritoryPlanning` and composed read contracts.

### Plans

- Alliance-scoped and Kingdom-scoped plan lists;
- create/open/save/publish/archive/clone/restore;
- optimistic revision/conflict feedback;
- map dataset/version/provenance visible to the user;
- application-linked and plan-local external Alliance/Governor references.

### Hive Builder

- HQ, Banner, Governor city and Bear Trap placement;
- exact coordinate editing;
- pan/zoom, select/move/delete/duplicate;
- box selection, grouping/ungrouping, 90-degree rotation and undo/redo;
- generated Bear-hive layouts and TC block placement;
- territory coverage and map/reference layers;
- blocking violations, planning warnings and suggestions displayed separately.

### Analysis

- territory connectivity and covered/uncovered Governors;
- invalid/warning counts and banner usage/efficiency;
- deterministic distance metrics and estimated march time with visible assumption/calibration;
- average/median/max metrics where supported;
- immutable layout/revision comparison.

### Multi-Alliance Kingdom planning

- multiple application-linked or external Alliances on one plan;
- independent labels, presentation colors and visibility;
- shared map collision/placement validation;
- per-Alliance TC/Banner/object counts;
- access remains Player/scope authorized and does not derive from a different inactive Governor.

### Revisions/import/export

- immutable published revisions pinned to map dataset/checksum;
- explicit clone/restore rather than mutation of history;
- schema-versioned JSON import with preview before commit;
- JSON export plus shareable PNG/SVG rendering.

### Accessibility

The canvas is not the only control surface. Every material object is represented in synchronized DOM controls with exact coordinates, keyboard-operable actions and non-color validation messages.

## Intel Room — Alliance Roster

Backed by Alliance roster and Intelligence snapshots: observed Governor identity, roster state, optional membership linkage, recorded power/freshness/trends, summary metrics, joins/departures, manual scout readings, CSV preview/commit/export, accepted human-reviewed roster-screenshot observations and history. Missing observations are never estimated; screenshot absence is meaningful only when its reviewed source explicitly represents the complete roster.

## Intel Room — Kingdom Alliances

Backed by Intelligence Observations/Diplomacy/Sharing/Ingestion plus `ReadModels/IntelligenceSignals`: tracked Alliances, timestamped/invalidation state, diplomacy, contacts/channels, shared Kingdom intelligence and officer ingestion tooling.

Kingdom Intelligence may show typed recent changes and stale-intelligence signals alongside the underlying latest/prior/7-day/30-day observation comparisons. Every signal retains neutral wording, source record/timestamp provenance and a canonical source link. Missing is not zero, stale is not missing, and ordinary source absence is not disappearance unless a complete-source contract explicitly proves it. The read model never turns power/member changes into strategic intent.

## Glory Ledger

Backed by Intelligence Contributions: categories, self/officer reports, approval/correction/reversal, source/status/data-quality flags, report schedules, export and Governor history. It is not presented as an official in-game leaderboard.

## Kingdom Transfer

Backed by GameWorld KingdomTransfers: cycles/states, incoming/outgoing/staying Governors, groups/coordinators, readiness/blockers and actual completion. The UI must not invent transfer eligibility rules. Intelligence change presentation may surface that an accepted Transfer observation is expiring/expired based on its canonical `valid_until`; that signal does not independently decide eligibility.

## Gift Codes

Backed by GameWorld GiftCodes: normalized sourced catalogue, per-Governor/Kingdom redemption state, official Century Games handoff, multi-Governor preparation and retryable/permanent outcomes. No undocumented redemption endpoint automation.

## Notification Center

Backed by Communications Delivery and built around **logical notifications**, not provider-route rows. One source notification appears once in the inbox even when it fans out to multiple channels or named endpoints. The inbox supports bounded cursor pagination and filters for unread/all/archived state, notification type, account/Governor scope, date and delivery status.

A logical notification exposes its title/body/action, read/archive state and an expandable list of concrete routes. Route details show channel, named target where applicable, queued/sent/failed/cancelled state, attempt counts, retry timing and safe routing/provider diagnostics without revealing credentials or raw provider payloads.

### Delivery preferences and routing

The account owns default notification-type/channel preferences. When an active Governor exists, the Governor may override a preference and may reset it to the account default. The routing-policy surface controls timezone, quiet hours, whether urgent notifications may bypass quiet hours, temporary mute, and immediate/hourly/daily digest cadence. In-app notifications remain visible when an external route is deferred.

### Destinations

The destination surface supports multiple named Discord webhook, Telegram and Web Push endpoints. Stored credentials/subscription configuration is encrypted and never redisplayed after save. Endpoints expose generic `Never tested`, `Healthy`, `Degraded` and `Paused` health, plus test/reverify, pause/resume and delete behavior. A failed or rate-limited endpoint does not affect another endpoint.

Web Push may be enabled per supported browser/device and uses the same preference/routing/retry contract as other external routes. Email is not a configurable Communications endpoint: it is available only through the Accounts-owned verified account email and is rechecked before send.

### Inbox actions

Read/unread/archive/restore operate on logical message IDs. Bounded bulk operations select up to 50 visible messages, preview against current ownership/state, confirm the eligible count and recheck ownership at commit. Provider-route recovery remains a separate bounded delivery operation and does not make failed routes look like duplicate inbox notifications.

When a Gift Code, Event, King Perk, Alliance announcement, Officer Brief, Account Security event or Intelligence signal is delivered, the source owner retains semantic truth. Communications owns only recipient routing, inbox state, concrete provider delivery, retry and endpoint health.

## Kingdom Roles

Backed by GameWorld Governance application roles. They must not be misrepresented as official Kingshot royal appointments.

## Explicitly excluded from the primary UI baseline

Territory planning and Kingdom map interaction are no longer generically excluded; they are supported only through the concrete Territory Command contracts above. The following remain unsupported unless new backend capability work is delivered:

- Alliance donation totals;
- Alliance Gift Level;
- a global Hall-of-Glory / Alliance leaderboard;
- Governor inventory;
- Governor stamina/EXP;
- skins/titles/mail;
- arbitrary game resource balances;
- generic Alliance power ranking;
- invented events such as Foundry Battle or Canyon Clash.

## Gift Code Redemption Workspace

- `/gift-codes/workspace` is the account-personal many-code/many-Governor action surface and is separate from the global catalogue/detail views.
- Workspace views are New, Ready to redeem, Expiring soon, Retry ready, In progress, Snoozed and Completed.
- Persistent redemption runs expose progress, current Governor/Gift Code, structured qualified reward display, copy controls, official-provider handoff, observed-result recording, skip/abandon and privacy-gated recent redemption signals.
- Personal pin/snooze/dismiss/reminder actions affect only the account workflow projection.
- `/gift-codes/workspace/alliance/{alliance}/coverage` is an authorized aggregate-only Alliance view and does not expose individual member histories.
- Desktop and mobile Playwright coverage verifies workspace creation, persisted resume, skip/result progression, accessible controls and horizontal-overflow safety.

