# Memberships domain

## Purpose

Owns Alliance membership and invitation lifecycle plus the authoritative KingShot Alliance rank (`R1`–`R5`).

## Core rank invariants

- Every active Alliance membership has exactly one rank.
- A new accepted member starts at R1.
- Exactly one active membership per Alliance is R5.
- R5 is the Alliance owner/leader.
- R4 is officer rank.
- Specialist RBAC roles are additive and do not affect rank.
- R5 cannot leave, be suspended, removed, or demoted through ordinary membership administration; leadership must be transferred.
- Leadership transfer promotes the target to R5 and demotes the previous R5 to R4 atomically.

## Public contracts

- active membership used to establish normal Alliance tenant access;
- `AllianceRank` and rank lifecycle;
- controlled invitation create/revoke/resend/acceptance;
- `UpdateAllianceRank` for R1–R4 administration; and
- dedicated self-service leave workflow.

## Dependencies

- `Identity` — global User/verified email/password assurance.
- `Alliances` — active tenant context.
- `Authorization` — permission evaluation and additive specialist roles.
- `Platform` — member-capacity/lifecycle state and leadership-transfer administration surface.
- `Audit` / Platform outbox — attributable/durable evidence.

## Canonical documentation

- [`docs/domains/memberships/`](../../../docs/domains/memberships/README.md)
