# Identity domain

## Purpose

Owns global User identity, authentication, verified-email state, password/session security, profile/account lifecycle, TOTP MFA, and recovery-code assurance.

## Owned code

Runtime code in this module owns global account/authentication/security behavior rather than Alliance tenancy or role permission state.

## Public contracts

- authenticated User identity;
- verified-email assurance;
- recent password-confirmation assurance; and
- MFA/recovery-code assurance used by privileged domain workflows.

## Dependencies

- `Platform` — legal-hold/account-deletion orchestration may affect destructive account processing.
- shared framework/session infrastructure for authenticated identity state.

Identity does not grant Alliance access by itself; Alliances, Memberships, and Authorization establish tenant/permission context.

## Canonical documentation

- [`docs/domains/identity/`](../../../docs/domains/identity/README.md)
