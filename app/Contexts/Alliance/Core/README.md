# Alliances domain

## Purpose

Owns the Alliance tenant aggregate, Alliance creation/settings, active-Alliance selection/context, and the canonical Alliance→Kingdom association.

## Owned code

Runtime code in this module owns Alliance persistence, Alliance creation/settings actions, and request-scoped active tenant-context behavior.

## Public contracts

- Alliance tenant identity used by all Alliance-scoped domains.
- Active-Alliance context and serializable tenant snapshot.
- Alliance creation/settings actions.
- Canonical `kingdom_id` association consumed by Kingdoms workflows.

## Dependencies

- `Identity` — authenticated/verified global User.
- `Memberships` — active membership required for normal tenant context.
- `Authorization` — Alliance view/manage permission evaluation.
- `Platform` — lifecycle state and platform defaults.
- `Audit` / Platform outbox — attributable/durable change evidence.
- `Kingdoms` — canonical Kingdom reference.

## Canonical documentation

- [`docs/domains/alliances/`](../../../docs/domains/alliances/README.md)
