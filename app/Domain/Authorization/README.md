# Authorization domain

## Purpose

Owns Alliance roles, permission keys, role assignment/removal, effective role rank, and permission evaluation for active Alliance memberships.

## Owned code

Runtime code in this module owns the fixed permission vocabulary, built-in role templates, membership-role assignments, and supported authorization services/actions.

## Public contracts

- `PermissionKey` — stable Alliance permission vocabulary.
- `DefaultAllianceRole` — built-in role templates/effective ranks.
- Alliance permission evaluation for active memberships.
- Supported role assignment/removal used by Alliance administration.

## Dependencies

- `Alliances` — target tenant context.
- `Memberships` — active membership/status.
- `Identity` — authenticated User identity.
- `Audit` / Platform outbox — role-change evidence.

Platform-administrator access is deliberately not an Alliance role/permission.

## Canonical documentation

- [`docs/domains/authorization/`](../../../docs/domains/authorization/README.md)
