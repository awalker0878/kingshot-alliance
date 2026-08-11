# Read-only API

[← Integrations domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Integrations

## 1. Purpose

Defines the Alliance-bound machine-to-machine read API, including credential identity, fixed scopes, tenant derivation, bounded representations, and failure behavior.

## 2. Scope and non-scope

In scope:

- Alliance API credential issuance/revocation;
- fixed read scopes;
- `/api/v1/alliance`, `/api/v1/events`, and `/api/v1/contributions`;
- credential-derived tenant context;
- rate/row bounds; and
- lifecycle/entitlement checks supplied by Platform.

Out of scope:

- write APIs;
- OAuth/user tokens;
- public Kingdoms API surfaces;
- webhook transport; and
- unbounded historical exports.

## 3. Model and state

API credentials are opaque Alliance-bound bearer credentials. Persistence retains only the non-secret lookup/verifier material required to authenticate later requests; plaintext credential material is shown only through the controlled issuance flow and is not recoverable from normal storage.

Supported scopes are:

- `alliance:read`;
- `events:read`; and
- `contributions:read`.

Credentials have lifecycle state such as active/revoked and any implemented expiry semantics, and are always bound to one Alliance.

## 4. Invariants

1. The credential establishes the Alliance; clients never submit arbitrary tenant identity.
2. Plaintext credential material is not persisted as a recoverable application value.
3. Scopes are fixed and read-only; wildcard write authority is absent.
4. Unknown, malformed, expired, revoked, or insufficient-scope credentials fail without leaking hidden tenant state.
5. Normal API access requires the bound Alliance to remain active and API availability/entitlement to permit use.
6. Collection endpoints are bounded to 250 rows.
7. Pending/reversed Contribution records are not exposed by the accepted Contributions read contract.
8. No accepted Kingdoms roster/snapshot/transfer/diplomacy scope exists.

## 5. Workflows

### Issue credential

An authorized Alliance manager uses the first-party Integrations surface with recent password confirmation. The application creates a bounded-scope credential and persists only the safe lookup/verifier state needed for future authentication.

### Authenticate request

The API parses the presented credential, verifies lifecycle/verifier/scope, derives the Alliance context, checks Alliance/API availability, then runs a tenant-bound read query.

### Revoke

Revocation prevents future API use. Compromised credentials are revoked/replaced rather than recovered from storage.

### Read endpoints

`GET /api/v1/alliance` returns the bounded Alliance representation. `GET /api/v1/events` returns bounded occurrence data. `GET /api/v1/contributions` returns bounded approved Contribution records.

## 6. Authorization, tenancy and privacy

Credential authentication is the machine authorization boundary. The credential's Alliance is authoritative and all queries begin from that tenant.

First-party credential management still requires active Alliance context, `alliance.manage`, and required Identity assurance.

## 7. Persistence and query semantics

Integrations owns credential lookup/verifier/status/scope state. Alliances, Events, and Contributions retain ownership of the business data represented by reads.

Collection queries remain bounded and tenant filtered. API representation compatibility fields do not transfer semantic ownership into Integrations.

## 8. Events, integrations and background processing

The read API is synchronous. Webhook delivery/retries are documented separately and are not part of API credential request execution.

Internal outbox events do not expand API scope automatically.

## 9. Failure, idempotency and concurrency

- Invalid credential returns authentication failure without cross-tenant discovery.
- Insufficient scope fails authorization.
- Revocation takes effect for subsequent requests.
- API request rate limits and credential-creation throttles remain bounded by the implemented contract.
- Reads are bounded; clients must not assume unlimited history.

## 10. Operations and observability

Operators should distinguish credential parsing/verifier failure, revoked/expired state, scope denial, inactive Alliance, disabled API entitlement, rate limit, and downstream query failure.

Logs must not include plaintext bearer credentials.

## 11. Tests and validation

Tests should cover:

- credential parsing/verifier behavior;
- non-recoverable plaintext handling;
- revocation/expiry;
- fixed scope enforcement;
- tenant derivation/isolation;
- inactive/disabled Alliance access;
- endpoint row/data-state bounds; and
- rate limiting.

## 12. Related documentation

- [Integrations domain](README.md)
- [Webhooks](webhooks.md)
- [Alliances](../alliances/README.md)
- [Events](../events/README.md)
- [Contributions](../contributions/README.md)
- [Platform](../platform/README.md)
- [Security baseline](../../security/security-baseline.md)
