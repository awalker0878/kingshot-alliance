# ADR-0013: Use exclusive account authentication types and durable Google subjects

Status: Superseded by [ADR-0014](0014-account-sign-in-methods.md)

Date: 2026-08-31

## Context

Kingshot Alliance is a third-party alliance-management application. Its Accounts context authenticates application Users; it does not authenticate official Kingshot game accounts.

The application supports local password authentication and Google OAuth authentication. Treating providers as generic attachable credentials would create hybrid accounts, ambiguous recovery behavior, and email-based identity-linking risk. The deployment is fresh-schema with no existing Accounts data, so the canonical data model can encode the intended design directly without compatibility/backfill layers.

## Decision

1. Every active User has exactly one explicit primary authentication type: `password` or `google`.
2. Password accounts have a local password and cannot add Google as another primary credential.
3. Google accounts authenticate exclusively with Google, have no usable local password, and cannot use local password login/change/reset flows.
4. TOTP remains an optional second factor for either primary type.
5. Google identity is persisted as an Accounts-owned provider identity. `provider=google` plus Google's stable `sub` is authoritative after creation; email is provider metadata, not the durable subject key.
6. Google email collision with an existing password User never silently links or changes authentication type.
7. OAuth access/refresh tokens are not persisted while Google is authentication-only.
8. Password recovery is enumeration resistant and emits reset credentials only for password accounts.
9. Sensitive recent-auth proof is primary-type aware; Google Users are never asked for a local password.
10. Account authentication copy uses `Kingshot Alliance` and never presents these credentials as an official Kingshot game-account system.
11. Because the schema is fresh, original create migrations are changed directly to the final schema. No account backfill, compatibility shim, dual read/write or legacy authentication-state bridge is introduced.

## Consequences

- This decision describes the completed first Accounts expansion and remains historical evidence for why unsafe email linking and casual hybridization were prohibited.
- [ADR-0014](0014-account-sign-in-methods.md) supersedes the exclusive-primary-authentication portion of this decision for the selected Sign-In Methods & Credential Evolution extension.
- The durable Google-subject, no-email-linking, no-OAuth-token-persistence, third-party product-language and fresh-schema boundaries remain in force under ADR-0014.
- Accounts remains separate from GameWorld/Players, Alliance and Kingdom/game authority.
