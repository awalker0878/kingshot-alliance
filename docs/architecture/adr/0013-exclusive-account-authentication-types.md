# ADR-0013: Use exclusive account authentication types and durable Google subjects

Status: Accepted

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

- Account type is explicit and auditable rather than inferred from password presence.
- Provider-subject persistence prevents email changes from becoming identity changes.
- Recovery and settings UX can cleanly omit impossible controls.
- Account-type conversion is not part of this program; adding it later requires a separate security decision.
- Passkeys are deferred because adding them casually as another primary credential would reopen the hybrid-authentication model.
- Accounts remains separate from GameWorld/Players, Alliance and Kingdom/game authority.