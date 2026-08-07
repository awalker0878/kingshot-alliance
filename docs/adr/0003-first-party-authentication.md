# ADR 0003 — Laravel first-party authentication

- **Status:** Accepted
- **Date:** 2026-08-06
- **Related phase:** Phase 0 design; Phase 1 implementation

## Context

The responsive web application and future API require secure session and token authentication without outsourcing the core alliance identity model.

## Decision

Use Laravel's authentication services with Sanctum for first-party web and API access. Phase 1 will implement registration policy, verification, password recovery, session management, privileged-action confirmation, MFA, and audit.

External identity providers may be added through adapters after the local identity lifecycle and account-linking rules are approved.

## Consequences

The team owns authentication flows and security maintenance, while retaining framework-native authorization and a clear migration path to federated identity.

## Validation

Security tests will cover account enumeration, recovery, session fixation, CSRF, token scope, rate limits, MFA, and role escalation.
