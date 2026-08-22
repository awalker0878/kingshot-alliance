# FRONTEND-V3 Capability Map

This document defines the Governor-facing screen map from application capabilities. It includes the active Territory & Hive Planner delivery contract so implementation cannot ship UI without its backing owner capabilities.

## Presentation rule

The frontend is organized by what a Governor or Alliance officer is doing. Backend context/read-model names stay behind Inertia.

```text
Governor Account
      │
      ▼
Active Governor
      │
      ├── Alliance Command
      │   ├── Alliance Hall
      │   ├── Recruitment Hall
      │   ├── Noticeboard
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
      │   ├── Diplomacy / Contacts
      │   ├── Shared Kingdom Intelligence
      │   └── Glory Ledger
      │
      ├── Governor Utilities
      │   ├── Gift Codes
      │   └── Notification Center
      │
      └── Kingdom
          ├── Kingdom Roles
          └── Kingdom Transfer
```

## Command Overview

Backed by current Governor membership/rank/specialist roles, Alliance notices/content, upcoming Events and permission-aware entry points. Territory Command may appear as an entry point only when the current Player has an eligible planning scope.

Do not show unsupported donation totals, leaderboard rank, Alliance Gift Level or arbitrary Alliance power merely because the game has those concepts.

## Alliance Hall

Backed by Alliance Membership and Access: active memberships, R1–R5 rank, specialist roles, invitations, membership status, rank/role changes, leadership transfer and leaving the Alliance.

## Recruitment Hall

Backed by Alliance Recruitment: modes/questions, Governor applications, stages, assigned reviewers, notes, tags, duplicate merge, decisions, invitation conversion and onboarding items.

## Noticeboard

Backed by Alliance Content: public profile, categories, member content, announcements, drafts/publishing, revisions/restore, media and public pages.

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

Backed by Alliance roster and Intelligence snapshots: observed Governor identity, roster state, optional membership linkage, recorded power/freshness/trends, summary metrics, joins/departures, manual scout readings, CSV preview/commit/export and history. Missing observations are never estimated.

## Intel Room — Kingdom Alliances

Backed by Intelligence Observations/Diplomacy/Sharing/Ingestion: tracked Alliances, timestamped/invalidation state, diplomacy, contacts/channels, shared Kingdom intelligence and officer ingestion tooling.

## Glory Ledger

Backed by Intelligence Contributions: categories, self/officer reports, approval/correction/reversal, source/status/data-quality flags, report schedules, export and Governor history. It is not presented as an official in-game leaderboard.

## Kingdom Transfer

Backed by GameWorld KingdomTransfers: cycles/states, incoming/outgoing/staying Governors, groups/coordinators, readiness/blockers and actual completion. The UI must not invent transfer eligibility rules.

## Kingdom Roles

Backed by GameWorld Governance application roles. They must not be misrepresented as official Kingshot royal appointments.

## Gift Codes

Backed by GameWorld GiftCodes: normalized sourced catalogue, per-Governor/Kingdom redemption state, official Century Games handoff, multi-Governor preparation and retryable/permanent outcomes. No undocumented redemption endpoint automation.

## Notification Center

Backed by Communications Delivery: in-app inbox, Governor-scoped Discord/Telegram endpoints, preferences, provider acknowledgement/health/retries and Event/KingPerk fan-out. Provider credentials remain encrypted and undisclosed after save.

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
