# ADR-0056: Current integration runtime availability

Status: Accepted

## Problem and alternatives

Platform settings disabled API credential or webhook creation while existing credentials and queued deliveries continued operating. The UI nevertheless stated that API access or webhooks were disabled. Relying only on creation-time checks leaves delayed work authorized by stale settings. Revoking every credential or subscription would make a temporary administrative disablement destructive and require unnecessary credential replacement.

## Decision

Integrations owns `IntegrationRuntimePolicy`, which reads current Alliance and Kingdom lifecycle through their owners and current AllianceAdministration settings. Missing settings preserve the fresh application's existing enabled defaults. API middleware checks availability before recording accepted credential use; direct actor-link and external-action entry points check it as well.

Webhook delivery checks availability inside its claim before DNS or opening an attempt, then again with the exact subscription and attempt immediately before provider handoff. A disabled or unavailable source becomes a safe terminal delivery failure. Re-enabling does not silently replay failed work; a manager may use the existing authorized manual-retry path. That path and test-delivery creation also require current availability after their existing Alliance authority lock.

Configuration and revocation remain available to their authorized owners. There is no new credential, queue, lifecycle or mutation authority. No HTTP or DNS IO is added inside a transaction. A disablement observed before the final handoff prevents a new request; already admitted API operations and requests handed to a remote provider cannot be recalled.

## Verification

HARD-112 records executed evidence. Regression cases cover an existing API credential across disable/re-enable without false usage recording, disablement after queueing and during DNS, and source-Kingdom archival. Provider rejection must leave no HTTP call and no claim token. The existing authorized API, pairing, receipt, webhook and recovery suites remain required.
