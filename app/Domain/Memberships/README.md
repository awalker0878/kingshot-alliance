# Memberships domain

## Purpose

Owns Alliance membership and invitation lifecycle, including active/suspended/left/removed state, invitation issue/revoke/resend/acceptance, leave/removal behavior, and membership-administration safety rules.

## Owned code

Runtime code in this module owns membership/invitation persistence and the supported membership/invitation actions consumed by Alliance administration and Recruitment onboarding.

## Public contracts

- active membership used to establish normal Alliance tenant access;
- controlled invitation create/revoke/resend/acceptance;
- dedicated self-service leave workflow; and
- supported accepted-candidate invitation handoff consumed by Recruitment.

## Dependencies

- `Identity` — global User/verified email/password assurance.
- `Alliances` — active tenant context.
- `Authorization` — permissions, role assignment/removal, effective rank/Owner safety.
- `Platform` — member-capacity entitlement/lifecycle state.
- `Audit` / Platform outbox — attributable/durable evidence.

## Canonical documentation

- [`docs/domains/memberships/`](../../../docs/domains/memberships/README.md)
