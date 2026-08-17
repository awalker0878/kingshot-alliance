# FRONTEND-V3 Capability Map

This document defines the Governor-facing screen map from the application capabilities that actually exist in the codebase.

It is intentionally narrower than a generic Kingshot companion app. A screen or label is not added because it exists somewhere in the game; it must be backed by this repository's current capabilities.

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
      └── Kingdom
          ├── Kingdom Roles
          └── Kingdom Transfer
```

## 1. Command Overview

Backed by:

- `AllianceOverviewController`
- current Governor membership rank and specialist roles
- Alliance notices/member content
- upcoming Alliance event occurrences
- Alliance invitation/member/recruitment/content/connection entry points when allowed

Do not show unsupported generic Alliance statistics such as territory, donation totals, leaderboard rank, alliance gift level or alliance power unless a concrete query is added to the page contract.

## 2. Alliance Hall

Backed by Alliance Membership and Access:

- active memberships
- R1–R5 Alliance rank
- specialist Alliance roles
- Governor-specific invitations
- invitation resend/revoke
- membership status
- rank changes
- specialist-role assignment/removal
- R5 leadership transfer
- leaving the Alliance

The Alliance roster used for intelligence is a different screen and must not be confused with application membership.

## 3. Recruitment Hall

Backed by Alliance Recruitment:

- public/invitation/closed application modes
- recruitment questions
- Governor applications
- stages: New, Screening, Contacted, Interview, Accepted, Declined, Withdrawn, Joined
- assigned reviewers
- officer notes
- tags
- duplicate-candidate merge
- decision messages
- acceptance conversion to an Alliance invitation
- onboarding items with Pending / Completed / Waived state

## 4. Noticeboard

Backed by Alliance Content:

- Alliance public profile
- categories
- member content
- announcements
- drafts/publishing
- revisions and restore
- media library
- public content pages

## 5. Alliance Connections

Backed by Platform Integrations but presented as an Alliance officer utility:

- Alliance access keys
- revoking access keys
- event dispatch subscriptions
- revoking dispatch subscriptions

This is not presented as a game feature. It remains visibly separate from the core Kingshot rooms.

## 6. Event Command

Backed by Operations / Events / Participation and the repository's `KingShotEventTypeCatalog`.

The current event catalogue includes:

- Bear Hunt
- Viking Vengeance
- Alliance Mobilization
- Alliance Championship
- Alliance Brawl
- Swordland Showdown
- Tri-Alliance Clash
- Flamedragon Tyrant
- Swordland Summit League
- Cesares Fury
- Outpost Battle
- Sanctuary Battle
- Castle Battle
- Kingdom of Power
- Hall of Governors
- Armament Competition
- Hero Roulette
- Fishing Tournament
- Treasure Raiders
- Merchant Empire
- Eternity's Reach
- Custom events

The UI must render only the rooms enabled for the selected event scope.

Supported event rooms from the code are:

- Responses
- Registration / Waitlist
- Attendance
- Phases
- Polls
- Rosters
- Substitutes
- Teams
- Legions
- Rally Guidance
- Formations
- Objectives
- King's Court
- Scoring
- Results

## 7. Bear Hunt Rally Command

The Bear Hunt Alliance scope currently enables:

- Responses
- Registration
- Attendance
- Rally Guidance
- Formations
- Results

The code also stores Alliance rally-guidance rules, player formations, event recommended formations, rally groups, rally assignments and rally participation.

Do not show generic battle objectives or event rosters on Bear Hunt unless the event capability definition changes.

## 8. Swordland Showdown War Room

The Swordland Showdown Alliance scope currently enables:

- Responses
- Polls
- Phases
- Combatant roster
- Substitute roster
- Objectives
- Attendance
- Scoring
- Results

Default phases in the catalogue are Voting, Registration, Matchmaking and Battle.

Default roster capacities are 30 combatants and 10 substitutes.

## 9. King's Court — Kingdom of Power

This room appears only when the Kingdom of Power Kingdom event has the `KingPerks` capability.

Implemented appointment types:

- Noble Advisor
- Chief Minister
- Field Commander
- Marshal
- Minister of Interior

Current catalogue rules:

- each appointment occupies 30 minutes
- Governor appointment cooldown is 60 minutes
- cancelled-position lockout is 30 minutes

Implemented Governor request categories:

- Construction
- Research
- Training
- Healing
- Combat

Implemented King Skills:

- Groundworks
- Fresh Ideas
- Mobilize
- Community Healing

The UI supports plan creation/publishing, Governor requests, assignment, confirmation/decline, activation, outcome/no-show handling, replacement, auto-scheduling, position blocks and skill planning/scheduling/activation.

## 10. Intel Room — Alliance Roster

Backed by Alliance roster and Intelligence roster snapshots:

- observed Governor identity
- roster state: Active / Tracked / Left
- optional application-membership linkage
- latest recorded power
- scout-reading freshness
- 7-day / 30-day recorded-power comparison
- total / average / median recorded power
- recent joins/departures
- linkage coverage
- manual scout readings
- CSV preview/commit
- CSV export
- historical readings

The UI must make clear that missing observations are not estimated.

## 11. Intel Room — Kingdom Alliances

Backed by Intelligence Observations / Diplomacy / Sharing / Ingestion:

- tracked Kingdom Alliances
- active/archived tracking state
- timestamped observations
- observation invalidation
- diplomacy state: Unknown, Neutral, Friendly, NAP, Ally, Rival
- diplomacy contacts
- contact channels: in-game, Discord, other handle
- shared Kingdom intelligence invitations and targets
- ingestion subscriptions/batches/candidates for officer tooling

Ingestion mechanics are an officer utility and should not dominate the normal Intel Room presentation.

## 12. Glory Ledger

Backed by Intelligence Contributions:

- contribution categories
- Governor self-reports
- officer-entered records
- approval
- correction
- reversal
- source and status
- data-quality flags and resolution
- report schedules
- CSV / spreadsheet export
- Governor contribution history

Do not present this as an official in-game leaderboard unless such a feature is explicitly implemented.

## 13. Kingdom Transfer

Backed by GameWorld Kingdom Transfers:

- transfer cycle
- Draft / Open / Locked / Closed / Cancelled states
- Incoming / Outgoing / Staying Governors
- transfer groups
- group coordinator
- Not Started / Preparing / Ready / Blocked / Confirmed / Withdrawn readiness
- blockers
- actual transfer completion
- roster handoff for incoming/outgoing completion

The UI can use official Kingshot transfer terminology where it maps to the implemented planning model, but it must not invent missing game rules or automatically infer eligibility.

## 14. Kingdom Roles

Backed by GameWorld Governance:

- Kingdom Administrator
- Kingdom Event Coordinator
- Kingdom Viewer
- Kingdom-role permission assignment/removal

These are application governance roles. They must not be visually misrepresented as official Kingshot royal appointments. King's Court appointments are a separate Operations capability.

## Explicitly excluded from the current primary UI baseline

The following were present in earlier concept art but are not current application capabilities and must not be presented as real screens/data without new backend work:

- generic Alliance Territory management
- Alliance donation totals
- Alliance Gift Level
- a global Hall-of-Glory / Alliance leaderboard
- Governor inventory
- Governor stamina/EXP
- skins/titles/mail
- generic kingdom map interaction
- generic Alliance power ranking
- arbitrary game resource balances
- invented events such as Foundry Battle or Canyon Clash
