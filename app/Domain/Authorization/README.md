# Authorization domain

## Purpose

Owns permission vocabulary, Alliance rank-derived permission policy, additive Alliance specialist roles, Kingdom-scoped roles/assignments, and contextual permission evaluation.

## Owned code

Runtime code in this module owns `PermissionKey`, Alliance/Kingdom role templates and persistence models, role provisioning/assignment actions, `AllianceRankPermissions`, `AllianceAuthorization`, and `KingdomAuthorization`.

## Public contracts

- `AllianceMembership.rank` is Memberships-owned but consumed as the authoritative R1–R5 hierarchy input.
- `DefaultAllianceRole` defines additive Recruiter, Event Coordinator, and Content Manager responsibilities.
- `DefaultKingdomRole` defines Kingdom Admin, Kingdom Event Coordinator, and Kingdom Viewer.
- Alliance rank/roles never imply Kingdom Event authority; Kingdom permission requires an exact-Kingdom assignment.
- Platform administrators may bootstrap/recover Kingdom assignments but are not implicit Kingdom Event administrators.

## Dependencies

- `Alliances` / `Memberships` — Alliance target, active membership and rank.
- `Kingdoms` — exact Kingdom target identity.
- `Identity` — authenticated User identity.
- `Platform` / `Audit` — bootstrap status and attributable durable evidence.

## Canonical documentation

- [`docs/domains/authorization/`](../../../docs/domains/authorization/README.md)
- [Kingdom-scoped roles](../../../docs/domains/authorization/kingdom-scoped-roles.md)
