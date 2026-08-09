# Kingdoms alliance intelligence and diplomacy

[← Domain documentation](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** Slice A / `K3-P1` candidate — neutral game-side alliance identity and tenant tracking only

## Purpose

`KINGDOMS-003` extends the Kingdoms domain with alliance-owned intelligence and diplomacy workflows for other game-side alliances. Slice A introduces only the identity/tracking foundation required by later slices.

No observation, diplomacy, contact, scoring, recommendation, ingestion, sharing, public API or webhook capability is part of Slice A.

## Identity model

`Alliance` is the platform tenant and authorization principal.

`KingdomAlliance` is a global neutral game-side alliance reference belonging to one `Kingdom`. It is not a tenant, user, membership, role or permission principal.

`TrackedKingdomAlliance` is the tenant-owned relationship between one platform Alliance and one neutral `KingdomAlliance`. It captures the Kingdom context in which tracking began and owns private manager notes.

The only automatic neutral identity key is an approved stable `game_alliance_id` scoped to one Kingdom. Name and tag are display/reference fields and never auto-merge or auto-link identity.

When no stable ID exists, starting tracking creates a distinct unresolved neutral reference even when another reference has the same name/tag. A stable ID may later be assigned explicitly to an unresolved reference only when no other neutral reference in that Kingdom already owns it. Once assigned, it cannot be cleared or replaced in place.

## Tenant boundary

Tracking rows are always re-resolved under the active Alliance. Two platform Alliances may reference the same neutral `KingdomAlliance` without sharing tracking state or manager notes.

Ordinary member serialization exposes only:

- current neutral name/tag;
- captured Kingdom number;
- tracking state; and
- whether the captured Kingdom still matches the platform Alliance current Kingdom.

Manager serialization additionally exposes the tracking/reference IDs, stable game alliance ID, reference status, private tracking notes and archive timestamp required by the management workflow.

## Kingdom drift

A tracking row captures `kingdom_id` when created.

If the platform Alliance later changes Kingdom:

- historical tracking remains readable;
- normal identity/tracking edits fail closed;
- the captured Kingdom is never rewritten;
- archive remains allowed as the explicit stale-context recovery action; and
- new tracking uses the Alliance current Kingdom.

## Lifecycle

Neutral references have `active` / `archived` lifecycle state. Slice A creates active references and refuses to start new tracking against an archived neutral reference.

Tenant tracking has `active` / `archived` lifecycle state. The database permits historical archived tracking while enforcing one active tracking row per Alliance + neutral reference.

Archival is idempotent. A previously archived neutral reference may be tracked again through a new tenant tracking row only when the neutral reference itself remains active.

## Authorization

- safe member read: `alliance.view`;
- create/update/archive tracking: `kingdoms.manage`;
- all mutations require recent password confirmation;
- no role-name controller checks are introduced.

Platform administrators do not implicitly receive tenant Kingdoms management authority.

## Audit and outbox

Material tracking changes emit attributable audit records and internal transactional-outbox messages:

- `kingdoms.alliance_intelligence_tracking_started`;
- `kingdoms.alliance_intelligence_tracking_updated`; and
- `kingdoms.alliance_intelligence_tracking_archived`.

Private tracking-note text is excluded from audit/outbox payload metadata. Existing Integration rules keep every `kingdoms.*` event ineligible for generic outbound webhook fan-out.

## Slice A persistence

`kingdom_alliances` contains only neutral reference identity required now:

- Kingdom;
- optional stable game alliance ID;
- current name/tag;
- lifecycle state; and
- timestamps.

`tracked_kingdom_alliances` contains only tenant tracking state required now:

- Alliance;
- neutral KingdomAlliance reference;
- captured Kingdom context;
- active/archived state;
- private manager notes;
- archive timestamp; and
- timestamps.

There are deliberately no observation, power/member-count, diplomacy/NAP, contact, threat/score, recommendation, automated-ingestion or public-integration fields.

## Deferred slices

- `K3-P2` — append-oriented alliance observations;
- `K3-P3` — explicit diplomacy/NAP lifecycle;
- `K3-P4` — manager-private diplomacy contacts;
- `K3-P5` — descriptive intelligence views/trends;
- `K3-P6` — whole-increment hardening and acceptance.

Until those slices are implemented and validated, do not infer those capabilities from the Slice A foundation.
