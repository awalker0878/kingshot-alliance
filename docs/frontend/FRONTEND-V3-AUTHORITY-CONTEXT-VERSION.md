# Frontend V3 — Authority Context Version

## Status

Implemented as the mutation-safety companion to Active Governor context isolation.

## Invariant

```text
THE PLATFORM USER AUTHENTICATES.
THE ACTIVE GOVERNOR ACTS.

A MUTATION MAY EXECUTE ONLY WHEN
THE SERVER-RESOLVED AUTHORITY CONTEXT
STILL MATCHES THE CONTEXT THE USER REVIEWED.
```

The authority context version is a **staleness precondition**, not authorization. Every protected write must continue to perform its normal server-side authorization and transaction-time authority checks.

## Server-issued version

The active Governor's shared Inertia context includes an opaque value such as:

```text
authctx:v1:<sha256>
```

The server derives it from authority-relevant facts, including:

- active Player reference
- owning Platform User reference
- current Kingdom reference
- active Alliance reference
- active membership reference
- current Alliance rank
- current specialist role keys
- effective Alliance capabilities
- current Kingdom permission keys

Ordering of roles and permissions does not affect the version. A material authority change does.

The value is safe to expose to the browser because it is not a bearer credential and cannot grant authority.

## Mutation transport

Inertia v3 requests receive the version globally through the application's `visitOptions` defaults:

```text
X-Game-Context-Version: authctx:v1:...
```

The application also installs a same-origin `fetch()` interceptor so direct browser mutations receive the same header. New frontend features should not implement their own authority-version transport.

Safe methods (`GET`, `HEAD`, `OPTIONS`) do not require the precondition.

## Server enforcement

`RequireCurrentPlayerContextVersion` runs after `ResolvePlayerContext` in the web middleware stack.

For a game-domain mutation it:

1. resolves the authenticated Platform User;
2. resolves the request's active Governor;
3. re-reads that Player and verifies current ownership;
4. re-resolves current Alliance membership, rank, roles, and capabilities;
5. re-resolves current Kingdom permissions;
6. issues the current authority context version;
7. compares it to `X-Game-Context-Version` using a timing-safe comparison;
8. rejects a missing or mismatched version before the controller mutation executes.

A stale request returns:

```http
HTTP/1.1 409 Conflict
X-Game-Context-Error: stale
Content-Type: application/json
```

with:

```json
{
  "code": "CONTEXT_STALE",
  "reason": "authority_context_changed",
  "message": "The active Governor or authority context changed. Reload the current context and try again."
}
```

The version comparison never replaces the domain's own authorization, aggregate locking, invariant validation, or transaction boundaries.

## Deliberate exemptions

Platform/account operations are not governed by the active Governor and therefore do not require the game authority version.

Examples include:

- profile and account management
- login/logout and password flows
- email verification
- MFA
- Platform Administration
- public routes

`players.activate` is also exempt because it is the operation that deliberately establishes a new active Governor context. It still validates that the requested Player belongs to the authenticated Platform User.

The default policy is otherwise fail-closed for authenticated non-safe web mutations, so newly added game mutations inherit the precondition unless deliberately classified as Platform-scoped.

## Frontend stale-context recovery

The frontend centrally recognizes:

```text
409 + X-Game-Context-Error: stale
```

from both Inertia and direct same-origin `fetch()` requests.

Recovery is:

```text
STALE MUTATION REJECTED
        ↓
OLD CONTEXT FROZEN
        ↓
REGISTERED OLD-CONTEXT ASYNC WORK DISPOSED
        ↓
ACCESSIBLE STALE-CONTEXT NOTICE SHOWN
        ↓
COMMAND OVERVIEW RELOADED
        ↓
NEW SERVER AUTHORITY CONTEXT INSTALLED
        ↓
OLD CONTEXT INVALIDATED
        ↓
USER REVIEWS CURRENT CONTEXT AND RETRIES
```

The failed mutation is **never replayed automatically**. Retrying a privileged action after authority changed must always be an explicit user decision based on the newly rendered context.

## Multi-tab behavior

### Governor switch in another tab

```text
Tab A: Governor A + version A → form opened
Tab B: switches to Governor B
Tab A: submits version A
Server: current Governor is B
Result: 409 CONTEXT_STALE
```

### Alliance rank or role changes

```text
Tab A: Governor A is R5 → form opened with version A
Authority changes: Governor A becomes R4
Tab A: submits version A
Server: current authority version differs
Result: 409 CONTEXT_STALE
```

### Kingdom permission changes

Kingdom permissions are included in the active authority version. A grant or revocation therefore invalidates mutations created under the earlier permission set even when the Player and Alliance stay unchanged.

## Relationship to the context fingerprint

`contextFingerprint` remains the presentation/read-side description attached to each available Governor.

`authorityContextVersion` describes the **currently active** Governor's mutation authority snapshot and includes Kingdom permissions in addition to Alliance authority.

`activeContextKey()` prefers `authorityContextVersion` when available. Therefore context-scoped caches, polling, subscriptions, and disposers are invalidated not only when the Governor or Alliance changes, but also when authority changes while the identity remains the same.

## Transaction-time authorization remains mandatory

This precondition protects the UX and request boundary against stale intent. It is not the final write authority.

Protected domain actions must still follow the repository's existing write pattern:

```text
receive immutable Player reference
        ↓
begin owning-domain transaction
        ↓
lock/reload current aggregate and authority facts
        ↓
authorize current Player
        ↓
validate invariants
        ↓
mutate
        ↓
commit
```

The request header must never be used to skip those checks.

## Test requirements

Mandatory coverage includes:

- equivalent authority facts produce the same version regardless of ordering;
- Player or Kingdom change changes the version;
- Alliance membership change changes the version;
- rank change changes the version;
- specialist-role change changes the version;
- Alliance capability change changes the version;
- Kingdom permission change changes the version;
- missing version rejects a game mutation;
- valid current version passes the staleness guard;
- an old-tab mutation is rejected after another tab selects a different Governor;
- an old mutation is rejected after the active Governor's rank changes;
- Player activation remains possible without the old authority version;
- Inertia mutations automatically receive the header;
- direct same-origin fetch mutations automatically receive the header;
- `409 CONTEXT_STALE` triggers centralized recovery;
- stale mutations are never automatically replayed.

## Completion rule

A new game-domain frontend mutation is incomplete if it can bypass the shared authority-version transport or if its backend route is incorrectly classified as Platform-scoped.

A new backend write is incomplete if it treats `X-Game-Context-Version` as authorization instead of retaining its owning-domain transaction-time authorization and locking rules.
