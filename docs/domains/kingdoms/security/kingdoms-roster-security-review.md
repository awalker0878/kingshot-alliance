# Kingdoms roster security review

[← Security documentation](README.md)

**Scope:** `KINGDOMS-001` Slice B / `K1-P2`  
**Status:** Implementation candidate  
**Parent foundation:** [Kingdoms foundation security review](kingdoms-foundation-security-review.md)

This review covers game-player identity and manual alliance-roster management. Historical power snapshots, analytics, CSV import/export, transfer planning, diplomacy and automated ingestion remain outside this review and must receive their owning security review before acceptance.

## Trust model

`Player` is global neutral game identity scoped to a Kingdom. An `AllianceRosterEntry` is alliance-owned observation/state.

A shared neutral player identity does not create a shared tenant record. The following remain alliance-scoped:

- observed player name used by that alliance;
- game role/rank;
- roster state;
- optional application-membership link;
- joined/left dates;
- private manager notes;
- last-observed timestamp and provenance; and
- all future snapshots, imports, exports and derived metrics.

Display names are not stable identity keys. Two same-name players without a stable game identifier must remain separate identities.

## Authorization

Slice B introduces `kingdoms.manage`.

Built-in defaults:

- Owner — yes;
- Leader — yes;
- Officer — yes;
- Recruiter — no;
- Event Coordinator — no;
- Content Manager — no; and
- Member — no.

Custom-role permission union remains authoritative. Controllers/actions must never branch on role names.

Roster read requires `alliance.view`. Management views and mutations require `kingdoms.manage`. Mutations additionally require recent password confirmation through the HTTP route boundary.

## Threats and controls

| Threat / abuse case | Control |
| --- | --- |
| Submitted roster-entry ID targets another alliance | Mutation actions re-resolve the entry with the active `alliance_id`; foreign IDs fail closed. |
| Submitted membership ID links another alliance's account | Membership resolution requires the active alliance and active membership state. |
| One membership is attached to multiple roster identities | Database uniqueness plus action validation permit only one roster link per alliance membership. Updates exclude the current roster entry so retaining its own valid link is allowed. |
| One game player is duplicated on the same alliance roster | Database uniqueness plus action validation permit one alliance roster entry per Player. |
| Same display name causes accidental merge | Resolver never deduplicates by player name. |
| Stable game ID from another Kingdom causes collision | Stable identifier reuse is scoped by `kingdom_id`. |
| Shared game identity leaks another alliance's private observations | Mutable names, role/state, membership links and manager notes live on `AllianceRosterEntry`, not the global Player. Read queries begin from active `alliance_id`. |
| Search/filter query discloses another alliance in the same Kingdom | Every roster search/filter predicate is applied to a query already constrained by the active Alliance. Kingdom/player identity is never an authorization key. |
| Ordinary member sees private membership/contact or manager data | Member-facing roster serialization emits linked member display name only; membership IDs/email and manager notes are excluded. Management serialization is gated by `kingdoms.manage`. |
| Privileged roster mutation is unattributable | Create/update/leave actions emit alliance-scoped audit records and matching transactional-outbox messages. |
| Repeated leave request duplicates business events | Mark-left is a no-op when already left. |
| Membership is deleted/left | The optional FK is nulled on physical membership deletion; game-player/roster identity remains. Membership lifecycle must not erase roster history. |
| HTML/script content in notes/name is executed | Values are rendered through Vue text bindings and are not interpreted as HTML. Future rich-text rendering requires separate sanitization design. |
| Platform admin bypasses tenant authorization | Platform administration does not implicitly gain `kingdoms.manage`; any cross-tenant support capability requires an explicit Platform-domain workflow. |

## Member-data minimization

Ordinary roster readers need the linked member's display identity, not account linkage metadata. The member-facing payload includes only the linked member display name and omits membership ID, membership email and private manager notes.

Manager pages may use member ID/email to disambiguate membership linking because they already operate behind `kingdoms.manage`; those values remain management-only.

## Manual observation freshness

Slice B may classify a manual roster row as current, stale or missing using `last_observed_at`. The candidate threshold is 30 days for the roster-maintenance surface.

This is not a power/snapshot freshness signal and must not be presented as one. Historical observations and their stale/missing semantics require the `K1-P3` security/data review.

## Events and outbox

Current durable events are:

- `kingdoms.roster_entry_created`;
- `kingdoms.roster_entry_updated`; and
- `kingdoms.roster_entry_left`.

They are recorded through the existing transactional-outbox mechanism in the same transaction as the business mutation. Slice B does not add a scheduler, Horizon queue, webhook event catalog, or public API exposure for roster data.

## Residual risks / later phases

- Snapshot data will materially increase longitudinal privacy and requires `K1-P3` review.
- Comparative growth/decline views require abuse/punitive-metric review in `K1-P4`.
- CSV files create formula-injection, file-size, ambiguity and replay risks in `K1-P5`.
- Automated ingestion remains unapproved and must not be inferred from the stable game-player identifier field.

Real production approval remains governed by the existing production security/launch records; Slice B CI does not prove external production controls.
